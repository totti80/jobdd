<?php

namespace App\Console\Commands;

use App\Support\JobEyecatchResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEyecatch extends Command
{
    protected $signature = 'jobdd:backfill-eyecatch {--input= : Saved importer JSON file} {--provider= : Provider key} {--limit=10 : Maximum eligible null rows, 1-1000} {--dry-run : Report without updates}';

    protected $description = 'Backfill null eyecatch URLs from saved metadata only (no network requests)';

    public function handle(): int
    {
        // Production execution is intentionally unavailable in this local-validation revision.
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Only local/testing is supported. Production requires separate approval and implementation review.');

            return self::FAILURE;
        }
        $provider = $this->option('provider');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $path = $this->option('input');
        if (! in_array($provider, ['meitec_next', 'careerjet'], true) || ! $limit || ! is_string($path) || ! is_file($path) || filesize($path) > 20 * 1024 * 1024) {
            $this->error('Specify provider, limit (1-1000), and a saved JSON input (max 20MB).');

            return self::FAILURE;
        }
        $payload = json_decode(file_get_contents($path), true);
        if (! is_array($payload) || ! is_array($payload['jobs'] ?? null)) {
            $this->error('Invalid jobs payload.');

            return self::FAILURE;
        }
        $scope = fn () => DB::table('job_postings')->where('provider_key', $provider)->whereNull('eyecatch_image_url')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('job_published_profiles')->whereColumn('job_published_profiles.job_posting_id', 'job_postings.id'));
        $before = $scope()->count();
        $eligible = $updated = 0;
        $seen = [];
        foreach ($payload['jobs'] as $raw) {
            if ($eligible >= $limit) {
                break;
            }
            if (! is_array($raw) || ! ($image = JobEyecatchResolver::importedUrl($provider, $raw))) {
                continue;
            }
            $url = $raw[$provider === 'careerjet' ? 'url' : 'source_url'] ?? null;
            $id = $raw['external_id'] ?? $raw['id'] ?? ($provider === 'careerjet' && is_string($url) ? hash('sha256', $url) : $url);
            if (! is_scalar($id) || ! is_string($url) || isset($seen[(string) $id])) {
                continue;
            }
            $seen[(string) $id] = true;
            $target = $scope()->where('external_id', (string) $id)->where('source_url', $url);
            if ($target->count() !== 1) {
                continue;
            }
            $eligible++;
            if (! $this->option('dry-run')) {
                // Conditional update remains null-only under concurrent import; no timestamps/status changes.
                $updated += $target->update(['eyecatch_image_url' => $image]);
            }
        }
        $this->line(json_encode(['null_before' => $before, 'eligible' => $eligible, 'updated' => $updated,
            'null_after' => $scope()->count(), 'dry_run' => (bool) $this->option('dry-run'),
            'external_requests' => 0, 'retries' => 0], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
