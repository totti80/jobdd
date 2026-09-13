<?php

namespace App\Services\DirectLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** Bounded, public-Internet-only fetching. No automatic redirects or retries. */
class DiscoveryHttpClient
{
    private array $robots = [];

    private array $lastRequest = [];

    public function fetch(string $url): array
    {
        $visited = [];
        for ($hop = 0; $hop < 4; $hop++) {
            if (isset($visited[$url])) {
                return $this->failure($url, 'crawl_failed', 'redirect_loop');
            }
            $visited[$url] = true;
            try {
                $host = parse_url($url, PHP_URL_HOST);
                $addresses = $this->publicAddresses($host);
                if (! $addresses) {
                    return $this->failure($url, 'blocked', 'non_public_or_unresolved_host');
                }
                $origin = parse_url($url, PHP_URL_SCHEME).'://'.$host;
                if (! isset($this->robots[$origin])) {
                    $response = $this->request($origin.'/robots.txt', $addresses);
                    // Unknown robots policy (including redirects) fails closed.
                    $this->robots[$origin] = match (true) {
                        $response->status() === 404 => ['body' => '', 'known' => true],
                        $response->successful() && ! str_contains(strtolower($response->header('Content-Type')), 'html') => ['body' => $response->body(), 'known' => true],
                        default => ['body' => '', 'known' => false],
                    };
                }
                $policy = $this->robots[$origin];
                if (! $policy['known'] || ! $this->robotsAllow($policy['body'], $url)) {
                    return $this->failure($url, 'blocked', 'robots_denied_or_unknown');
                }
                $response = $this->request($url, $addresses);
                $status = $response->status();
                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    $next = OfficialPageDiscovery::normalizeUrl($response->header('Location'), $url);
                    if (! $next) {
                        return $this->failure($url, 'blocked', 'unsafe_redirect', $status);
                    }
                    $url = $next;

                    continue;
                }
                if (! $response->successful()) {
                    return $this->failure($url, in_array($status, [401, 403, 429]) ? 'blocked' : 'crawl_failed', 'http_error', $status);
                }
                if (! preg_match('~^(text/html|application/xhtml\+xml)\b~i', $response->header('Content-Type'))) {
                    return $this->failure($url, 'blocked', 'non_html', $status);
                }

                return ['url' => $url, 'state' => 'fetched', 'reason' => 'html_fetched', 'http_status' => $status, 'html' => $response->body()];
            } catch (\Throwable $e) {
                return $this->failure($url, 'crawl_failed', 'request_failed');
            }
        }

        return $this->failure($url, 'blocked', 'redirect_limit');
    }

    /** Resolve once and pin the vetted IPv4 to cURL, including on each redirect. */
    public function publicAddresses(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [];
            }
        }

        return $addresses;
    }

    private function request(string $url, array $addresses)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $elapsed = microtime(true) - ($this->lastRequest[$host] ?? 0);
        if ($elapsed < 2) {
            Sleep::usleep((int) ((2 - $elapsed) * 1000000));
        }
        $this->lastRequest[$host] = microtime(true);
        $port = parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80;

        return Http::withUserAgent('JobDDDiscovery/0.1 (candidate discovery; human review required)')
            ->connectTimeout(5)->timeout(15)->withoutRedirecting()
            ->withOptions([
                'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$addresses[0]}"]],
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > 2097152) {
                        throw new \RuntimeException('Response too large');
                    }
                },
                'progress' => function ($total, $downloaded) {
                    if ($downloaded > 2097152) {
                        throw new \RuntimeException('Response too large');
                    }
                },
            ])->get($url);
    }

    private function robotsAllow(string $body, string $url): bool
    {
        // Conservative MVP policy: honor Disallow from ALL groups, without Allow overrides.
        // This can skip permitted pages, but cannot broaden a robots permission.
        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (preg_match('/^crawl-delay\s*:\s*(.*)$/i', $line, $match)) {
                if (! is_numeric($match[1]) || (float) $match[1] > 2) {
                    return false;
                }
            }
            if (preg_match('/^disallow\s*:\s*(.+)$/i', $line, $match)) {
                $pattern = trim($match[1]);
                $end = str_ends_with($pattern, '$');
                if ($end) {
                    $pattern = substr($pattern, 0, -1);
                }
                $regex = '~^'.str_replace('\*', '.*', preg_quote(rawurldecode($pattern), '~')).($end ? '$' : '').'~';
                if (preg_match($regex, rawurldecode($path))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function failure(string $url, string $state, string $reason, ?int $status = null): array
    {
        return ['url' => $url, 'state' => $state, 'reason' => $reason, 'http_status' => $status, 'html' => null];
    }
}
