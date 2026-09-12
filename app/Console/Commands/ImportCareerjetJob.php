<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\OccupationNormalizer;

class ImportCareerjetJob extends Command
{
    protected $signature = 'crawler:import-careerjet-job';

    protected $description = 'Import Careerjet job JSON into JobDD';

    public function handle(OccupationNormalizer $occupationNormalizer): int
    {
        $runId = DB::table('crawl_runs')->insertGetId([
            'crawler_name' => 'careerjet_job_import',
            'status' => 'running',
            'started_at' => now(),
            'fetched_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $path = 'crawler/careerjet_jobs.json';

            if (!Storage::disk('local')->exists($path)) {
                throw new \RuntimeException('Careerjet JSON not found.');
            }

            $json = Storage::disk('local')->get($path);

            $payload = json_decode($json, true);

            $jobs = is_array($payload) && array_key_exists('jobs', $payload)
                ? ($payload['jobs'] ?? [])
                : $payload;

            if (!is_array($jobs)) {
                throw new \RuntimeException('Invalid JSON.');
            }

            $createdCount = 0;
            $updatedCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            foreach ($jobs as $job) {
                try {
                    $companyName = trim((string) ($job['company'] ?? ''));
                    $title = trim((string) ($job['title'] ?? ''));
                    $location = trim((string) ($job['region'] ?? $job['locations'] ?? ''));
                    $description = (string) ($job['description'] ?? '');
                    $sourceUrl = trim((string) ($job['url'] ?? ''));
                    $externalId = trim((string) ($job['external_id'] ?? $job['id'] ?? ''));
                    $externalId = $externalId !== ''
                        ? $externalId
                        : hash('sha256', $sourceUrl);

                    $salaryMinRaw = $job['salary_min'] ?? null;
                    $salaryMaxRaw = $job['salary_max'] ?? null;
                    $salaryType = $job['salary_type'] ?? null;

                    if (!$companyName) {
                        $this->warn('Skipped Careerjet job: company_name is missing.');
                        $skippedCount++;
                        continue;
                    }

                    if (!$title || !$sourceUrl) {
                        $failedCount++;
                        continue;
                    }

                    $salaryMin = $this->convertSalaryToAnnualManYen(
                        $salaryMinRaw,
                        $salaryType
                    );

                    $salaryMax = $this->convertSalaryToAnnualManYen(
                        $salaryMaxRaw,
                        $salaryType
                    );

                    DB::transaction(function () use (
                        $companyName,
                        $title,
                        $location,
                        $description,
                        $sourceUrl,
                        $externalId,
                        $occupationNormalizer,
                        $salaryMin,
                        $salaryMax,
                        &$createdCount,
                        &$updatedCount
                    ) {
                        /*
                         * 1. Company
                         */
                        $company = DB::table('companies')
                            ->where('name', $companyName)
                            ->first();

                        if (!$company) {
                            $companyId = DB::table('companies')->insertGetId([
                                'name' => $companyName,
                                'website_url' => null,
                                'industry' => null,
                                'region' => $location ?: null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $createdCount++;
                        } else {
                            $companyId = $company->id;
                        }

                        /*
                         * 2. Job posting
                         */
                        $jobPosting = DB::table('job_postings')
                            ->where('company_id', $companyId)
                            ->where('title', $title)
                            ->where('region', $location ?: null)
                            ->first();

                        $jobPostingData = [
                            'company_id' => $companyId,
                            'title' => $title,
                            'occupation' => '機械設計',
                            'industry' => null,
                            'region' => $location ?: null,
                            'salary_min' => $salaryMin,
                            'salary_max' => $salaryMax,
                            'description' => $description,
                            'employment_type' => '正社員',
                            'source_url' => $sourceUrl,
                            'provider_key' => 'careerjet',
                            'external_id' => $externalId ?: null,
                            'first_seen_at' => now(),
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                            'updated_at' => now(),
                        ];

                        $jobPostingData['occupation'] = $occupationNormalizer->normalize(
                            $title,
                            $description
                        );

                        if (!$jobPosting) {
                            $jobPostingData['created_at'] = now();

                            $jobPostingId = DB::table('job_postings')
                                ->insertGetId($jobPostingData);

                            $createdCount++;
                        } else {
                            $jobPostingId = $jobPosting->id;

                            DB::table('job_postings')
                                ->where('id', $jobPostingId)
                                ->update(array_merge($jobPostingData, [
                                    'first_seen_at' => $jobPosting->first_seen_at ?: now(),
                                ]));

                            $updatedCount++;
                        }

                        /*
                         * 3. Source
                         */
                        $source = DB::table('sources')
                            ->where('url', $sourceUrl)
                            ->first();

                        $sourceData = [
                            'source_type' => 'platform_api',
                            'title' => $title,
                            'publisher' => 'Careerjet',
                            'url' => $sourceUrl,
                            'fetched_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (!$source) {
                            $sourceData['created_at'] = now();

                            DB::table('sources')->insert($sourceData);

                            $createdCount++;
                        } else {
                            DB::table('sources')
                                ->where('id', $source->id)
                                ->update($sourceData);

                            $updatedCount++;
                        }

                        /*
                         * 4. Careerjet platform route
                         */
                        $platform = DB::table('platforms')
                            ->where('name', 'Careerjet')
                            ->first();

                        if (!$platform) {
                            throw new \RuntimeException(
                                'Careerjet platform is not registered.'
                            );
                        }

                        $route = DB::table('application_routes')
                            ->where('provider_key', 'careerjet')
                            ->where('external_id', $externalId)
                            ->first()
                            ?? DB::table('application_routes')
                            ->where('job_posting_id', $jobPostingId)
                            ->where('route_type', 'platform')
                            ->where('platform_id', $platform->id)
                            ->first();

                        $routeData = [
                            'job_posting_id' => $jobPostingId,
                            'route_type' => 'platform',
                            'agency_id' => null,
                            'platform_id' => $platform->id,
                            'application_url' => $sourceUrl,
                            'availability_status' => 'available',
                            'provider_key' => 'careerjet',
                            'external_id' => $externalId ?: null,
                            'first_seen_at' => now(),
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                            'notes' => 'Careerjetで確認した求人です。',
                            'updated_at' => now(),
                        ];

                        if (!$route) {
                            $routeData['created_at'] = now();

                            DB::table('application_routes')->insert($routeData);

                            $createdCount++;
                        } else {
                            DB::table('application_routes')
                                ->where('id', $route->id)
                                ->update(array_merge($routeData, [
                                    'job_posting_id' => $jobPostingId,
                                ]));

                            $updatedCount++;
                        }
                    });
                } catch (\Throwable $e) {
                    $failedCount++;

                    $this->warn(
                        'Skipped failed job: '
                            . ($job['title'] ?? 'unknown')
                            . ' / '
                            . $e->getMessage()
                    );
                }
            }

            DB::table('crawl_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'success',
                    'finished_at' => now(),
                    'fetched_count' => count($jobs),
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'failed_count' => $failedCount,
                    'updated_at' => now(),
                ]);

            if (($data['completed'] ?? false) === true) {
                $seenIds = collect($jobs)
                    ->map(function (array $job) {
                        $externalId = trim((string) ($job['external_id'] ?? $job['id'] ?? ''));
                        $sourceUrl = trim((string) ($job['url'] ?? ''));

                        return $externalId !== '' ? $externalId : hash('sha256', $sourceUrl);
                    })
                    ->filter()
                    ->values();

                DB::table('application_routes')
                    ->where('provider_key', 'careerjet')
                    ->where('availability_status', 'available')
                    ->when($seenIds->isNotEmpty(), fn($query) => $query->whereNotIn('external_id', $seenIds))
                    ->update([
                        'availability_status' => 'unavailable',
                        'unavailable_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $this->info('Careerjet jobs imported successfully.');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Fetched', count($jobs)],
                    ['Created', $createdCount],
                    ['Updated', $updatedCount],
                    ['Skipped Company Missing', $skippedCount],
                    ['Failed', $failedCount],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::table('crawl_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'failed_count' => 1,
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            $this->error('Careerjet import failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function convertSalaryToAnnualManYen(
        mixed $salary,
        ?string $salaryType
    ): ?int {
        if (!is_numeric($salary)) {
            return null;
        }

        $salary = (float) $salary;

        return match ($salaryType) {
            'M' => (int) round($salary * 12 / 10000),
            'Y' => (int) round($salary / 10000),
            default => null,
        };
    }
}
