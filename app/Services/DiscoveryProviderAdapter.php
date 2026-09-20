<?php

namespace App\Services;

use App\Support\ProviderCapabilities;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Thin boundary around existing Python fetchers and opt-in importer command paths. */
class DiscoveryProviderAdapter
{
    public const COMMANDS = ['careerjet' => 'crawler:import-careerjet-job',
        'recruit_agent' => 'crawler:import-recruit-agent-jobs', 'meitec_next' => 'crawler:import-meitec-next-jobs'];

    public function fetch(string $provider, array $cell): array
    {
        if (! in_array($provider, config('discovery.approved_providers'), true)) {
            throw new RuntimeException('ProviderNotApproved');
        }
        $url = config('discovery.search_urls')[$provider][$cell['occupation']][$cell['region']] ?? '';
        if ($provider !== 'careerjet' && $url === '') {
            throw new RuntimeException('SearchUrlNotConfigured');
        }
        $process = new Process([config('crawler.python'), base_path('crawler/fetch_daily_cell.py'),
            $provider, $cell['occupation'], $cell['region'], $url], base_path(),
            ['CAREERJET_API_KEY' => config('discovery.careerjet_api_key')]);
        $process->setTimeout(config('discovery.fetch_timeout'));
        $process->run();
        if (! $process->isSuccessful()) {
            // Never forward process output: exceptions/HTML may contain credentials.
            throw new RuntimeException('FetchFailed');
        }
        $payload = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload['jobs'] ?? null) || ($payload['completed'] ?? false) !== true) {
            throw new RuntimeException('IncompleteFetch');
        }

        return $payload;
    }

    public function import(string $provider, array $cell, array $payload, bool $dryRun): array
    {
        if (! ProviderCapabilities::persistent($provider)) {
            return app(DailyDiscoveryImporter::class)->run($provider, $cell, $payload['jobs'], true, $payload['metadata'] ?? []);
        }
        $directory = config('discovery.report_directory').'/inputs';
        File::ensureDirectoryExists($directory, 0700);
        $name = (string) Str::uuid().'.json';
        $path = $directory.'/'.$name;
        File::put($path, json_encode(['cell' => $cell, ...$payload], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        chmod($path, 0600);
        try {
            $code = Artisan::call(self::COMMANDS[$provider], ['--daily-input' => $name, '--dry-run' => $dryRun]);
            $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            if ($code !== 0 || ! is_array($result)) {
                throw new RuntimeException('ImportFailed');
            }

            return $result;
        } finally {
            File::delete($path);
        }
    }
}
