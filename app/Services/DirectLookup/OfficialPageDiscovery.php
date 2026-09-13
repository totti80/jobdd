<?php

namespace App\Services\DirectLookup;

use App\Models\Company;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class OfficialPageDiscovery
{
    private const GROUPS = ['company_website_candidates', 'recruitment_page_candidates', 'job_page_candidates', 'ats_candidates'];

    private const MAX_PAGES = 5;

    private const MAX_CANDIDATES = 30;

    private const MAX_DEPTH = 2;

    public function __construct(private DiscoveryHttpClient $http) {}

    public function discover(array $record): array
    {
        $checkedAt = now()->toIso8601String();
        $candidates = [];
        $pending = [];
        $add = function (?string $url, string $group, ?string $source, string $via, int $depth) use (&$candidates, &$pending, $checkedAt) {
            if (! $url) {
                return;
            }
            if (isset($candidates[$url])) {
                $provenance = ['source_url' => $source, 'found_via' => $via];
                if (! in_array($provenance, $candidates[$url]['provenance'], true)) {
                    $candidates[$url]['provenance'][] = $provenance;
                }

                return;
            }
            if (count($candidates) >= self::MAX_CANDIDATES) {
                return;
            }
            $candidates[$url] = [
                'url' => $url, 'group' => $group, 'source_url' => $source,
                'found_via' => $via, 'provenance' => [['source_url' => $source, 'found_via' => $via]],
                'page_title' => null, 'http_status' => null, 'checked_at' => $checkedAt,
                'confidence' => 'low', 'reason' => 'Observed URL only; official identity and current jobs require human review.',
                'status' => 'candidate', 'review_status' => 'needs_review',
                'fetch_status' => 'not_fetched', 'fetch_reason' => 'page_or_depth_limit',
            ];
            if ($depth <= self::MAX_DEPTH) {
                $pending[] = [$url, $depth];
            }
        };

        $company = Company::find($record['company_id']);
        $seeds = [[$company?->website_url, 'companies.website_url'], [$record['official_site_url'] ?? null, 'queue.official_site_url'], [$record['official_recruit_url'] ?? null, 'queue.official_recruit_url']];
        foreach ($record['discovery_candidates'] ?? [] as $candidate) {
            $seeds[] = [$candidate['website_url'] ?? null, 'discovery_candidates.website_url'];
            $seeds[] = [$candidate['official_recruit_url'] ?? null, 'discovery_candidates.official_recruit_url'];
        }
        foreach ($seeds as [$raw, $via]) {
            $url = self::normalizeUrl($raw);
            $add($url, $this->classify($url ?? '', '') ?? 'company_website_candidates', null, $via, 0);
        }

        $attempts = [];
        $terms = [];
        $visited = [];
        while ($pending && count($attempts) < self::MAX_PAGES) {
            [$requested, $depth] = array_shift($pending);
            if (isset($visited[$requested])) {
                continue;
            }
            $visited[$requested] = true;
            $result = $this->http->fetch($requested);
            $url = $result['url'];
            $attempts[] = ['requested_url' => $requested, 'final_url' => $url, 'status' => $result['state'], 'reason' => $result['reason'], 'http_status' => $result['http_status'], 'checked_at' => $checkedAt];
            if ($url !== $requested) {
                $entry = $candidates[$requested];
                unset($candidates[$requested]);
                if (isset($candidates[$url])) {
                    $entry['provenance'] = array_values(array_unique(array_merge($entry['provenance'], $candidates[$url]['provenance']), SORT_REGULAR));
                }
                $entry['url'] = $url;
                $entry['provenance'][] = ['source_url' => $requested, 'found_via' => 'http_redirect'];
                $candidates[$url] = $entry;
            }
            $visited[$url] = true;
            $candidates[$url]['http_status'] = $result['http_status'];
            $candidates[$url]['fetch_status'] = $result['state'];
            $candidates[$url]['fetch_reason'] = $result['reason'];
            if ($result['state'] !== 'fetched') {
                continue;
            }
            $doc = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $doc->loadHTML('<?xml encoding="UTF-8">'.$result['html'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new \DOMXPath($doc);
            $title = trim($xpath->evaluate('string(//title)'));
            $candidates[$url]['page_title'] = mb_substr($title, 0, 300);
            $hint = $this->classify($url, $title);
            if ($hint) {
                $candidates[$url]['group'] = $hint;
            }
            if ($depth >= self::MAX_DEPTH) {
                continue;
            }
            $scanned = 0;
            foreach ($xpath->query('//a[@href] | //link[@rel="canonical"] | //meta[@property="og:url"]') as $node) {
                if (++$scanned > 300) {
                    break;
                }
                $link = self::normalizeUrl($node->getAttribute($node->nodeName === 'meta' ? 'content' : 'href'), $url);
                if (! $link) {
                    continue;
                }
                $label = trim($node->textContent);
                if (preg_match('/terms|利用規約|利用条件/i', $link.' '.$label)) {
                    $terms[$link] = ['url' => $link, 'source_url' => $url, 'review_status' => 'needs_review'];

                    continue;
                }
                $group = $this->classify($link, $label);
                // Metadata may identify a canonical top page, but is still only a candidate.
                if (! $group && $node->nodeName !== 'a' && parse_url($link, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST)) {
                    $group = 'company_website_candidates';
                }
                if ($group) {
                    $add($link, $group, $url, $node->nodeName === 'a' ? 'html_link' : 'canonical_or_meta', $depth + 1);
                }
            }
        }

        $states = array_column($attempts, 'status');
        $state = in_array('fetched', $states, true) ? 'discovered'
            : (in_array('blocked', $states, true) ? 'blocked'
                : ($attempts ? 'crawl_failed' : 'not_resolved'));
        $output = [
            'company_id' => $record['company_id'], 'company_name' => $record['company_name'],
            'source_candidate_ids' => array_values(array_unique(array_filter(array_merge([$record['candidate_id'] ?? null], array_column($record['discovery_candidates'] ?? [], 'candidate_id'))))),
            'discovery_candidates' => $record['discovery_candidates'] ?? [],
            'candidate_id' => $record['candidate_id'] ?? null, 'region' => $record['region'] ?? null,
            'occupation' => $record['occupation'] ?? null,
            // Safe if accidentally supplied to the legacy processor: it skips unverified.
            'status' => 'unverified', 'discovery_status' => $state, 'review_status' => 'needs_review',
            'official_site_url' => null, 'official_recruit_url' => null,
            'checked_at' => $checkedAt, 'terms_review_status' => 'needs_review',
            'terms_candidates' => array_values($terms), 'fetch_attempts' => $attempts,
        ];
        foreach (self::GROUPS as $group) {
            $output[$group] = array_values(array_map(function ($entry) {
                unset($entry['group']);

                return $entry;
            }, array_filter($candidates, fn ($entry) => $entry['group'] === $group)));
        }

        return $output;
    }

    private function classify(string $url, string $label): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        foreach (['hrmos.co', 'herp.careers', 'greenhouse.io', 'greenhouse.com', 'lever.co', 'myworkdayjobs.com', 'myworkdaysite.com', 'smarthr.jp', 'talentio.com', 'talentio.co.jp', 'jobcan.jp'] as $ats) {
            if ($host === $ats || str_ends_with($host, '.'.$ats)) {
                return 'ats_candidates';
            }
        }
        if (preg_match('/jobs|vacanc|求人|募集職種|募集一覧|職種一覧/iu', $url.' '.$label)) {
            return 'job_page_candidates';
        }
        if (preg_match('/recruit|career|採用/iu', $url.' '.$label)) {
            return 'recruitment_page_candidates';
        }

        return null;
    }

    public static function normalizeUrl(?string $raw, ?string $base = null): ?string
    {
        if (! $raw || preg_match('/[\x00-\x20\x7f\\\\]/', $raw)) {
            return null;
        }
        try {
            $uri = new Uri($raw);
            if ($base) {
                $uri = UriResolver::resolve(new Uri($base), $uri);
            }
            $host = strtolower($uri->getHost());
            if (! in_array(strtolower($uri->getScheme()), ['http', 'https'], true) || ! $host || $uri->getUserInfo() || $uri->getPort() !== null) {
                return null;
            }
            if (! preg_match('/^[a-z0-9.-]+$/', $host) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
                return null;
            }
            foreach (['localhost', 'local', 'internal', 'recruit-agent.co.jp', 'r-agent.com', 'careerjet.jp', 'careerjet.com', 'm-next.jp', 'doda.jp', 'indeed.com', 'indeed.jp', 'bizreach.jp', 'rikunabi.com', 'mynavi.jp'] as $denied) {
                if ($host === $denied || str_ends_with($host, '.'.$denied)) {
                    return null;
                }
            }
            $path = rawurldecode($uri->getPath());
            if (preg_match('~(?:^|/)(?:login|signin|sign-in|auth|oauth|account|mypage)(?:/|$)|\.(?:pdf|zip|png|jpe?g|gif|svg|mp4|docx?|xlsx?)$~i', $path)) {
                return null;
            }
            $query = [];
            foreach (explode('&', $uri->getQuery()) as $part) {
                if ($part !== '' && ! preg_match('/^(utm_[^=]*|gclid|fbclid|msclkid)=/i', $part)) {
                    $query[] = $part;
                }
            }

            // Preserve functional queries, path case, scheme and trailing slash: these may identify distinct jobs.
            return (string) $uri->withHost($host)->withFragment('')->withQuery(implode('&', $query))->withPath($uri->getPath() ?: '/');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
