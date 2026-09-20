<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DailyDiscoveryService
{
    public const OCCUPATIONS = ['機械設計', '電気設計'];

    public const REGIONS = ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'];

    public function __construct(private DiscoveryProviderAdapter $adapter, private DiscoveryCoverageService $coverage) {}

    public function cells(?string $occupation = null, ?string $region = null): array
    {
        if (($occupation !== null && ! in_array($occupation, self::OCCUPATIONS, true))
            || ($region !== null && ! in_array($region, self::REGIONS, true))) {
            throw new InvalidArgumentException('InvalidCell');
        }
        $cells = [];
        foreach (self::OCCUPATIONS as $job) {
            foreach (self::REGIONS as $area) {
                if (($occupation === null || $occupation === $job) && ($region === null || $region === $area)) {
                    $cells[] = ['occupation' => $job, 'region' => $area];
                }
            }
        }

        return $cells;
    }

    public function run(bool $dryRun = false, ?string $provider = null, ?string $occupation = null, ?string $region = null): array
    {
        $cells = $this->cells($occupation, $region);
        if ($provider !== null && ! array_key_exists($provider, DiscoveryProviderAdapter::COMMANDS)) {
            throw new InvalidArgumentException('InvalidProvider');
        }
        $providers = $provider === null ? array_keys(DiscoveryProviderAdapter::COMMANDS) : [$provider];
        $directory = config('discovery.report_directory');
        File::ensureDirectoryExists($directory, 0700);
        // OS-managed lock has no TTL race, covers manual and scheduled invocation, and writes no DB cache rows.
        $lock = fopen($directory.'/.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('AlreadyRunning');
        }
        try {
            return $this->execute($providers, $cells, $dryRun, $directory);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function execute(array $providers, array $cells, bool $dryRun, string $directory): array
    {
        $started = microtime(true);
        $report = ['run_id' => (string) Str::uuid(), 'run_started_at' => now()->toIso8601String(), 'dry_run' => $dryRun,
            'provider_count' => count($providers), 'cell_count' => count($cells), 'by_provider' => [], 'by_cell' => [], 'errors' => []];
        $changes = [];
        $successful = 0;
        // Platform before Agents; each provider visits cells in the same canonical order.
        foreach ($providers as $provider) {
            $totals = array_fill_keys(['fetched', 'normalized', 'imported', 'new', 'updated', 'unchanged', 'missing', 'skipped'], 0);
            $totals['errors'] = 0;
            foreach ($cells as $cell) {
                try {
                    $payload = $this->adapter->fetch($provider, $cell);
                    $result = $this->adapter->import($provider, $cell, $payload, $dryRun);
                    foreach ($totals as $key => $value) {
                        $totals[$key] += $key === 'errors' ? count($result['errors']) : $result[$key];
                    }
                    foreach ($result['errors'] as $error) {
                        $report['errors'][] = ['provider' => $provider, ...$cell, ...$error];
                    }
                    $successful += $result['errors'] === [] ? 1 : 0;
                    $changes = [...$changes, ...$result['changes']];
                    $report['by_cell'][] = ['provider' => $provider, ...$cell, 'status' => $result['errors'] === [] ? 'success' : 'partial',
                        'scope' => $payload['scope'] ?? 'bounded_search_results', 'discovered' => $result['normalized'], ...$result];
                } catch (Throwable $e) {
                    $type = in_array($e->getMessage(), ['ProviderNotApproved', 'SearchUrlNotConfigured', 'FetchFailed', 'IncompleteFetch'], true)
                        ? $e->getMessage() : class_basename($e);
                    $report['errors'][] = ['provider' => $provider, ...$cell, 'stage' => 'fetch_or_import', 'error_type' => $type];
                    $totals['errors']++;
                    $report['by_cell'][] = ['provider' => $provider, ...$cell, 'status' => 'failed', 'discovered' => null, 'missing' => null];
                }
            }
            $report['by_provider'][$provider] = $totals;
        }
        $report['status'] = $report['errors'] === [] ? 'success' : ($successful > 0 || count($changes) > 0 ? 'partial' : 'failed');
        $report['coverage'] = $this->coverage->snapshot();
        foreach ($cells as $cell) {
            $rows = collect($report['coverage']['by_cell'])->filter(fn ($row) => $row['occupation'] === $cell['occupation'] && $row['region'] === $cell['region']);
            $report['cells'][] = [...$cell, 'after_import' => $rows->sum('jobs'), 'provider_breakdown' => $rows->values()->all()];
        }
        $report['direct_lookup_candidates'] = $this->coverage->directCandidates($changes);
        $report['run_finished_at'] = now()->toIso8601String();
        $report['duration_seconds'] = round(microtime(true) - $started, 3);
        $report['definitions'] = ['missing' => 'Absent from this successful bounded provider/cell result, not unavailable; failed cells yield no observation.',
            'dry_run' => 'Classification preview; imported=0, coverage is current DB, new IDs may be null.',
            'direct' => 'Company candidates only; stored company website/route presence is not same-job official confirmation.'];
        $name = now()->format('Y-m-d').'-'.$report['run_id'].($dryRun ? '-preview' : '');
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        File::put($directory.'/'.$name.'.json', $json);
        $markdown = '# JobDD Daily Discovery'."\n\n".$report['run_started_at']."\n\nStatus: ".$report['status']."\n\nProviders: ".count($providers).' / Cells: '.count($cells)."\n\n";
        $markdown .= "| Provider | Fetched | New | Updated | Unchanged | Missing | Errors |\n|---|---:|---:|---:|---:|---:|---:|\n";
        foreach ($report['by_provider'] as $key => $totals) {
            $markdown .= '|'.$key.'|'.implode('|', array_intersect_key($totals, array_flip(['fetched', 'new', 'updated', 'unchanged', 'missing', 'errors'])))."|\n";
        }
        $markdown .= "\n## Cells\n\n| Occupation | Region | Current jobs |\n|---|---|---:|\n";
        foreach ($report['cells'] as $cell) {
            $markdown .= '|'.$cell['occupation'].'|'.$cell['region'].'|'.$cell['after_import']."|\n";
        }
        foreach (['current_db_coverage' => 'Coverage', 'evidence_depth' => 'Evidence Depth', 'agent_fact_metrics' => 'Agent Fact Presence'] as $key => $heading) {
            $markdown .= "\n## ".$heading."\n\n";
            foreach ($report['coverage'][$key] as $metric => $value) {
                $markdown .= '- '.$metric.': '.($value ?? 'undefined')."\n";
            }
        }
        $markdown .= "\n## Direct Reverse Lookup\n\nCandidate companies: ".count($report['direct_lookup_candidates'])."\n\n## Errors\n\n";
        foreach ($report['errors'] as $error) {
            $markdown .= '- '.$error['provider'].' / '.$error['occupation'].' / '.$error['region'].': '.$error['error_type']."\n";
        }
        $markdown .= "\n## Full structured report\n\n```json\n".$json."\n```\n";
        File::put($directory.'/'.$name.'.md', $markdown);
        chmod($directory.'/'.$name.'.json', 0600);
        chmod($directory.'/'.$name.'.md', 0600);

        return [...$report, 'report_path' => $directory.'/'.$name.'.json'];
    }
}
