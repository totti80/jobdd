<?php

namespace App\Services\DirectLookup;

use App\Models\Agency;
use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\Platform;
use App\Models\Source;
use Illuminate\Support\Facades\File;

/** Offline extraction only. A stored URL is evidence of an observation, not official verification. */
class CompanyWebsiteResolver
{
    private array $blockedHosts = [];

    public function __construct()
    {
        $urls = Agency::pluck('website_url')->merge(Platform::pluck('website_url'))
            ->merge(ApplicationRoute::whereIn('route_type', ['agent', 'platform'])->pluck('application_url'));
        foreach ($urls as $url) {
            if (is_string($url) && ($host = parse_url($url, PHP_URL_HOST))) {
                $this->blockedHosts[] = preg_replace('/^www\./', '', strtolower($host));
            }
        }
    }

    public function resolve(array $record): array
    {
        $company = Company::findOrFail($record['company_id']);
        if ($company->name !== $record['company_name']) {
            throw new \InvalidArgumentException('Queue company ID/name mismatch.');
        }
        $found = [];
        $checkedAt = now()->toIso8601String();
        $add = function ($raw, array $evidence) use (&$found, $checkedAt) {
            $url = $this->websiteUrl($raw);
            if (! $url) {
                return;
            }
            $evidence['observed_url'] = $raw;
            if (! isset($found[$url])) {
                $found[$url] = [
                    'url' => $url, ...$evidence,
                    'confidence_reason' => 'URL explicitly present in stored data; employer ownership and official identity require human review.',
                    'review_status' => 'needs_review', 'checked_at' => $checkedAt, 'evidence' => [],
                ];
            }
            if (! in_array($evidence, $found[$url]['evidence'], true)) {
                $found[$url]['evidence'][] = $evidence;
            }
        };
        $add($company->website_url, $this->evidence('company_record', $company->website_url, "companies:{$company->id}", 'website_url'));
        foreach (DirectReverseLookupCandidate::where('company_id', $company->id)->orderBy('id')->get() as $candidate) {
            $add($candidate->website_url, $this->evidence('candidate_record', $candidate->website_url, "direct_reverse_lookup_candidates:{$candidate->id}", 'website_url'));
        }
        $jobs = $company->jobPostings()->orderBy('id')->get();
        // sources has no company FK or HTML column. Match publisher exactly for official-site records.
        $sources = Source::where('publisher', $company->name)->orderBy('id')->get();
        foreach ($sources->whereIn('source_type', ['official', 'official_site']) as $source) {
            $add($source->url, $this->evidence('official_source', $source->url, "sources:{$source->id}", 'url'));
        }
        foreach ($jobs as $job) {
            if (is_string($job->description) && is_string($job->source_url) && $job->source_url !== '') {
                $this->extractText($job->description, $company->name, $this->evidence('job_description', $job->source_url, "job_postings:{$job->id}", 'description'), $add);
            }
        }
        $sourceUrls = $jobs->pluck('source_url')->merge($sources->pluck('url'))->filter()->all();
        $warnings = [];
        foreach ($this->snapshotFiles() as $file) {
            if (is_link($file) || filesize($file) > 32 * 1024 * 1024) {
                $warnings[] = basename($file).': skipped symlink or file over 32MiB';

                continue;
            }
            try {
                $payload = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $warnings[] = basename($file).': invalid JSON';

                continue;
            }
            if (! is_array($payload)) {
                continue;
            }
            $rows = array_is_list($payload) ? $payload : ($payload['jobs'] ?? [$payload]);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sourceUrl = $row['source_url'] ?? $row['url'] ?? null;
                $name = $row['company_name'] ?? $row['company'] ?? null;
                // Conflicting names/IDs never inherit another company's source identity.
                if (isset($row['company_id']) && $row['company_id'] !== $company->id) {
                    continue;
                }
                if (is_string($name) && trim($name) !== $company->name) {
                    continue;
                }
                if ($name !== $company->name && ! in_array($sourceUrl, $sourceUrls, true)) {
                    continue;
                }
                if (! is_string($sourceUrl) || ! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $locator = str_replace(base_path().'/', '', $file).'#row='.$index;
                $evidence = $this->evidence('crawler_json', $sourceUrl, $locator, '');
                $evidence['snapshot_sha256'] = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                foreach (['company_website_url', 'company_url', 'employer_url', 'official_site_url', 'website_url'] as $field) {
                    if (isset($row[$field])) {
                        $add($row[$field], [...$evidence, 'evidence_field' => $field, 'found_via' => 'explicit_company_field']);
                    }
                }
                $this->extractStructured($row, $company->name, $evidence, $add, '$');
                foreach (['description', 'html', 'source_html'] as $field) {
                    if (is_string($row[$field] ?? null)) {
                        $type = $field === 'description' ? 'crawler_json' : 'source_html';
                        if ($field !== 'description' && $sources->where('source_type', 'official_recruiting')->contains('url', $sourceUrl)) {
                            $type = 'ats_html';
                        }
                        $this->extractText($row[$field], $company->name, [...$evidence, 'source_type' => $type, 'evidence_field' => $field], $add);
                    }
                }
            }
        }

        return [
            'company_id' => $company->id, 'company_name' => $company->name,
            'source_candidate_ids' => array_values(array_unique(array_filter(array_merge([$record['candidate_id'] ?? null], array_column($record['discovery_candidates'], 'candidate_id'))))),
            'discovery_candidates' => $record['discovery_candidates'],
            'status' => 'unverified', 'review_status' => 'needs_review',
            'resolution_status' => $found ? 'candidates_found' : 'not_resolved',
            'checked_at' => $checkedAt, 'website_candidates' => array_values($found), 'warnings' => $warnings,
        ];
    }

    private function extractStructured(array $data, string $companyName, array $evidence, callable $add, string $path, int $depth = 0): void
    {
        if ($depth > 12) {
            return;
        }
        $types = (array) ($data['@type'] ?? []);
        $isOrganization = count(array_intersect($types, ['Organization', 'Corporation', 'LocalBusiness'])) > 0 || str_ends_with($path, '.hiringOrganization');
        if ($isOrganization && ($data['name'] ?? null) === $companyName) {
            foreach (['url', 'sameAs'] as $field) {
                foreach ((array) ($data[$field] ?? []) as $url) {
                    $add($url, [...$evidence, 'found_via' => 'organization_identity', 'evidence_field' => $path.'.'.$field, 'evidence_excerpt' => 'name='.$companyName]);
                }
            }
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->extractStructured($value, $companyName, $evidence, $add, $path.'.'.$key, $depth + 1);
            }
        }
    }

    private function extractText(string $text, string $companyName, array $evidence, callable $add): void
    {
        if (strlen($text) > 2 * 1024 * 1024) {
            return;
        }
        $json = json_decode($text, true);
        if (is_array($json)) {
            $this->extractStructured($json, $companyName, $evidence, $add, '$');

            return; // Do not reinterpret unrelated structured URLs as free-text employer links.
        }
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8">'.$text, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $data = json_decode($node->textContent, true);
            if (is_array($data)) {
                $this->extractStructured($data, $companyName, $evidence, $add, $evidence['evidence_field'].'.json_ld');
            }
        }
        foreach ($xpath->query('//a[@href]') as $node) {
            $label = trim($node->textContent);
            if (preg_match('/企業公式|会社公式|公式サイト|企業ホームページ|会社ホームページ|コーポレートサイト|corporate\s*(site|website)|company\s*website|employer\s*website/iu', $label)) {
                $url = OfficialPageDiscovery::normalizeUrl($node->getAttribute('href'), $evidence['source_url']);
                $add($url, [...$evidence, 'found_via' => 'explicit_corporate_link', 'evidence_excerpt' => mb_substr($doc->saveHTML($node), 0, 600)]);
            }
        }
        // Plain text importers discard anchors. Accept only literally observed root URLs;
        // never derive a root domain from a job, product, blog or PDF URL.
        $plain = strip_tags(preg_replace('~<script\b[^>]*>.*?</script>~is', '', $text));
        preg_match_all('~https?://[^\s<>"\x{3000}]+~u', $plain, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$raw, $offset]) {
            if ((parse_url($raw, PHP_URL_PATH) ?: '/') !== '/' || parse_url($raw, PHP_URL_QUERY)) {
                continue;
            }
            $add($raw, [...$evidence, 'found_via' => 'literal_root_url_in_employer_record', 'evidence_excerpt' => mb_strcut($plain, max(0, $offset - 120), strlen($raw) + 240)]);
        }
    }

    private function evidence(string $type, ?string $sourceUrl, string $locator, string $field): array
    {
        return ['source_type' => $type, 'source_url' => $sourceUrl, 'found_via' => 'stored_field', 'evidence_locator' => $locator, 'evidence_field' => $field];
    }

    private function snapshotFiles(): array
    {
        // Read only crawler inputs, never generated queues or review files (no evidence feedback loop).
        $files = [];
        foreach ([storage_path('app/private/crawler'), base_path('crawler/data')] as $root) {
            foreach (['careerjet*.json', 'recruit_agent*.json', 'meitec_next*.json', 'mhi_job.json', 'direct_reverse_lookup_slice.json'] as $pattern) {
                $files = array_merge($files, glob($root.'/'.$pattern) ?: []);
            }
        }
        sort($files);

        return array_values(array_unique($files));
    }

    private function websiteUrl($raw): ?string
    {
        if (! is_string($raw) || ! preg_match('~^https?://~i', $raw)) {
            return null;
        }
        $url = OfficialPageDiscovery::normalizeUrl($raw);
        if (! $url) {
            return null;
        }
        $host = strtolower(parse_url($url, PHP_URL_HOST));
        foreach (array_merge($this->blockedHosts, ['jobviewtrack.com', 'careerjet.jp', 'ee-ties.com', 'linkedin.com', 'facebook.com', 'instagram.com', 'x.com', 'twitter.com', 'youtube.com', 'youtu.be', 'tiktok.com', 'google.com', 'google.co.jp', 'bing.com', 'yahoo.co.jp', 'hrmos.co', 'herp.careers', 'greenhouse.io', 'greenhouse.com', 'lever.co', 'myworkdayjobs.com', 'myworkdaysite.com', 'talentio.com', 'talentio.co.jp', 'jobcan.jp']) as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return null;
            }
        }
        if (preg_match('~/(jobs?|recruit|careers?|apply)(/|$)|[?&](job_?id|job|q|query)=~i', $url)) {
            return null;
        }

        return $url;
    }
}
