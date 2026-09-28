<?php

namespace App\Services;

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\Source;
use App\Services\DirectLookup\CompanyUrlEvidence;
use App\Support\AnonymousCompany;
use App\Support\JobDecisionPresenter;
use App\Support\JobEyecatchResolver;
use App\Support\ProviderCapabilities;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/** Opt-in daily import; legacy bulk command behavior remains untouched. */
class DailyDiscoveryImporter
{
    public function command(Command $command, string $provider): int
    {
        $name = $command->option('daily-input');
        if (! is_string($name) || ! preg_match('/^[a-f0-9-]{36}\.json$/D', $name)) {
            $command->error('InvalidDailyInput');

            return Command::FAILURE;
        }
        $payload = json_decode(file_get_contents(config('discovery.report_directory').'/inputs/'.$name), true, 512, JSON_THROW_ON_ERROR);
        $result = $this->run($provider, $payload['cell'], $payload['jobs'], (bool) $command->option('dry-run'));
        $command->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }

    public function normalize(string $provider, array $raw): array
    {
        $string = fn ($value) => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        $name = $string($raw[$provider === 'careerjet' ? 'company' : 'company_name'] ?? null);
        if (AnonymousCompany::isAnonymous($name)) {
            throw new InvalidArgumentException('ReservedCompanyName');
        }
        if ($name === null && $provider === 'careerjet') {
            $name = AnonymousCompany::name($provider);
        }
        $title = $string($raw['title'] ?? null);
        $url = JobDecisionPresenter::safeUrl($raw[$provider === 'careerjet' ? 'url' : 'source_url'] ?? null);
        $id = $string($raw['external_id'] ?? $raw['id'] ?? null)
            ?? ($provider === 'careerjet' ? hash('sha256', $url ?? '') : $url);
        if (! $name || ! $title || ! $url || ! $id || strlen($id) > 255) {
            throw new InvalidArgumentException('InvalidJob');
        }
        $description = $string($raw['description'] ?? null);
        $salary = function ($value) use ($provider, $raw) {
            if (! is_numeric($value) || $value <= 0) {
                return null;
            }

            return $provider === 'careerjet' ? match ($raw['salary_type'] ?? null) {
                'M' => (int) round($value * 12 / 10000),
                'Y' => (int) round($value / 10000),
                default => null,
            } : (int) $value;
        };
        $region = $provider === 'careerjet'
            ? $this->careerjetRegion($string($raw['locations'] ?? null))
            : $string($raw['region'] ?? $raw['locations'] ?? null);
        // Do not collapse ambiguous multiple locations into a single prefecture.
        if ($region && ! str_contains($region, '/') && ! str_contains($region, '、')) {
            foreach (DailyDiscoveryService::REGIONS as $prefecture) {
                if (str_starts_with($region, $prefecture)) {
                    $region = $prefecture;
                    break;
                }
            }
        }
        $data = ['company_name' => $name, 'title' => $title,
            'occupation' => app(OccupationNormalizer::class)->normalize($title, $description), 'region' => $region,
            'salary_min' => $salary($raw['salary_min'] ?? null), 'salary_max' => $salary($raw['salary_max'] ?? null),
            'description' => $description, 'employment_type' => $string($raw['employment_type'] ?? null) ?? ($provider === 'careerjet' ? '正社員' : null),
            'source_url' => $url, 'provider_key' => $provider, 'external_id' => $id, 'unavailable_at' => null];
        if ($image = JobEyecatchResolver::importedUrl($provider, $raw)) {
            $data['eyecatch_image_url'] = $image;
        }
        $raw['published_at'] ??= $raw['date_posted'] ?? null;
        foreach (['published_at', 'provider_updated_at'] as $field) {
            if (isset($raw[$field])) {
                $data[$field] = Carbon::parse($raw[$field])->utc()->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    public function run(string $provider, array $cell, array $rows, bool $dryRun, array $metadata = []): array
    {
        if (! ProviderCapabilities::persistent($provider)) {
            return $this->observe($provider, $cell, $rows, $metadata);
        }
        $result = ['fetched' => count($rows), 'normalized' => 0, 'imported' => 0, 'new' => 0, 'updated' => 0,
            'unchanged' => 0, 'missing' => 0, 'skipped' => 0, 'anonymous_company_jobs' => 0, 'not_observed_in_window' => 0, 'errors' => [], 'changes' => [], 'missing_job_ids' => [], 'not_observed_in_window_job_ids' => [], 'absence_status' => 'not_evaluated'];
        $seen = [];
        $processed = [];
        foreach ($rows as $row) {
            try {
                $data = $this->normalize($provider, $row);
                $seen[] = $data['external_id']; // Includes successfully observed out-of-cell identities.
                if ($data['occupation'] !== $cell['occupation'] || $data['region'] !== $cell['region']) {
                    $result['skipped']++;

                    continue;
                }
                if (isset($processed[$data['external_id']])) {
                    continue;
                }
                $processed[$data['external_id']] = true;
                $result['normalized']++;
                $result['anonymous_company_jobs'] += AnonymousCompany::isAnonymous($data['company_name']) ? 1 : 0;
                $operation = fn () => $this->upsert($data, $row, $dryRun);
                // No transaction ever spans external I/O. Dry-run never enters a write path.
                $change = $dryRun ? $operation() : DB::transaction($operation);
                $result[$change['state']]++;
                $result['imported'] += $dryRun ? 0 : 1;
                $result['changes'][] = $change;
            } catch (Throwable $e) {
                $result['errors'][] = ['stage' => 'import', 'error_type' => class_basename($e)];
            }
        }
        // Failed/incomplete cells do not produce absence observations.
        if ($result['errors'] === []) {
            $capability = ProviderCapabilities::get($provider);
            $complete = $capability['supports_complete_snapshot'] && $capability['supports_missing_detection'];
            $field = $complete ? 'missing' : 'not_observed_in_window';
            $result['absence_status'] = $complete ? 'missing_observation' : 'bounded_window';
            $result[$field.'_job_ids'] = JobPosting::query()->where('provider_key', $provider)
                ->where('occupation', $cell['occupation'])->where('region', $cell['region'])
                ->whereNull('unavailable_at')->whereNotNull('external_id')->whereNotIn('external_id', $seen)
                ->orderBy('id')->pluck('id')->all();
            $result[$field] = count($result[$field.'_job_ids']);
        }

        return $result;
    }

    private function observe(string $provider, array $cell, array $rows, array $metadata): array
    {
        $result = ['mode' => 'read_only', 'fetched' => count($rows), 'observed_in_window' => count($rows),
            'window_total' => count($rows), 'hits' => is_int($metadata['hits'] ?? null) ? $metadata['hits'] : null,
            'observation_time' => now()->toIso8601String(), 'normalized' => 0, 'accepted' => 0, 'skipped' => 0,
            'anonymous' => 0, 'anonymous_company_jobs' => 0, 'imported' => 0,
            'new' => null, 'updated' => null, 'unchanged' => null, 'missing' => null, 'not_observed_in_window' => null,
            'absence_status' => 'not_applicable', 'changes' => [], 'missing_job_ids' => [],
            'not_observed_in_window_job_ids' => [], 'current_discovery_candidates' => [], 'errors' => []];
        foreach ($rows as $row) {
            try {
                $data = $this->normalize($provider, $row);
                if ($data['occupation'] !== $cell['occupation'] || $data['region'] !== $cell['region']) {
                    $result['skipped']++;

                    continue;
                }
                $result['normalized']++;
                $result['accepted']++;
                $result['anonymous'] += AnonymousCompany::isAnonymous($data['company_name']) ? 1 : 0;
                $result['anonymous_company_jobs'] = $result['anonymous'];
                // Observation only: no stable ID, DB lookup, change classification or body dump.
                $result['current_discovery_candidates'][] = array_intersect_key($data, array_flip([
                    'company_name', 'title', 'occupation', 'region', 'salary_min', 'salary_max', 'source_url',
                ]));
            } catch (Throwable $e) {
                $result['errors'][] = ['stage' => 'normalize', 'error_type' => class_basename($e)];
            }
        }

        return $result;
    }

    /** Only explicit prefectures in the provider location field are facts. No city inference. */
    private function careerjetRegion(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }
        preg_match_all('/北海道|東京都|京都府|大阪府|[一-龠]{2,3}県/u', $location, $prefectures);
        if (count(array_unique($prefectures[0])) !== 1) {
            return null;
        }
        // Separate destinations must each explicitly confirm the same supported prefecture.
        $parts = preg_split('/\s*[-－–—\/／、,;；・]\s*/u', $location);
        $regions = [];
        foreach ($parts as $part) {
            $matched = null;
            foreach (DailyDiscoveryService::REGIONS as $prefecture) {
                if (str_starts_with(trim($part), $prefecture)) {
                    $matched = $prefecture;
                    break;
                }
            }
            if ($matched === null) {
                return null;
            }
            $regions[] = $matched;
        }

        return count(array_unique($regions)) === 1 ? $regions[0] : null;
    }

    private function upsert(array $data, array $raw, bool $dryRun): array
    {
        $provider = $data['provider_key'];
        $identity = ['provider_key' => $provider, 'external_id' => $data['external_id']];
        $job = JobPosting::where($identity)->first();
        $route = ApplicationRoute::where($identity)->first();
        if ($job && $route && $route->job_posting_id !== $job->id) {
            throw new InvalidArgumentException('ConflictingProviderIdentity');
        }
        if (! $job && $route) {
            $job = JobPosting::findOrFail($route->job_posting_id);
            // A legacy merged posting can have another provider's identity. Do not overwrite it.
            if ($job->provider_key !== $provider || $job->external_id !== $data['external_id']) {
                throw new InvalidArgumentException('SharedProviderIdentityRequiresReview');
            }
        }
        $company = Company::where('name', $data['company_name'])->first();
        $before = $job?->attributesToArray();
        if ($job) {
            $before['company_name'] = Company::whereKey($job->company_id)->value('name');
            foreach (['published_at', 'provider_updated_at'] as $field) {
                $before[$field] = $job->getRawOriginal($field);
                $data[$field] ??= $before[$field];
            }
        }
        $state = app(DiscoveryChangeClassifier::class)->classify($before, $data);
        if ($route && ($route->availability_status !== 'available' || $route->unavailable_at !== null)) {
            $state = $state === 'new' ? 'new' : 'updated';
        }
        if (! $dryRun) {
            $company = AnonymousCompany::isAnonymous($data['company_name'])
                ? AnonymousCompany::resolve($provider)
                : ($company ?? Company::create(['name' => $data['company_name'], 'region' => $data['region']]));
            $attributes = array_diff_key($data, ['company_name' => true]);
            $job ??= new JobPosting;
            $job->fill([...$attributes, 'company_id' => $company->id,
                'first_seen_at' => $job->first_seen_at ?? now(), 'last_seen_at' => now()])->save();
            $platformId = $provider === 'careerjet' ? DB::table('platforms')->where('name', 'Careerjet')->value('id') : null;
            if ($provider === 'careerjet' && ! $platformId) {
                throw new InvalidArgumentException('MissingPlatform');
            }
            $route ??= new ApplicationRoute;
            $route->fill([...$identity, 'job_posting_id' => $job->id, 'route_type' => $platformId ? 'platform' : 'agent',
                'platform_id' => $platformId, 'agency_id' => $platformId ? null : ($provider === 'meitec_next' ? 7 : 8),
                'application_url' => $data['source_url'], 'availability_status' => 'available', 'unavailable_at' => null,
                'first_seen_at' => $route->first_seen_at ?? now(), 'last_seen_at' => now()])->save();
            Source::updateOrCreate(['url' => $data['source_url']], ['source_type' => $platformId ? 'platform_api' : 'official_site',
                'title' => $data['title'], 'publisher' => $provider, 'fetched_at' => now()]);
            if (! AnonymousCompany::isAnonymous($data['company_name'])) {
                app(CompanyUrlEvidence::class)->save($job->id, $company->id, $raw, $provider);
            }
            if ($state !== 'unchanged') {
                $extractor = app(JobFactExtractor::class);
                // Synchronize only dictionary-owned rule Facts; human/other extraction is untouched.
                $keys = array_column($extractor->extract($job), 'fact_key');
                JobFact::where('job_posting_id', $job->id)->where('extraction_method', 'rule')
                    ->whereIn('fact_key', array_column(JobFactDictionary::definitions(), 'fact_key'))
                    ->whereNotIn('fact_key', $keys)->delete();
                $extractor->persist($job);
            }
        }

        return ['state' => $state, 'job_id' => $job?->id, 'company_id' => $company?->id,
            'company_name' => $data['company_name']];
    }
}
