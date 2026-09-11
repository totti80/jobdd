<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportCareerjetJob extends Command
{
    protected $signature = 'crawler:import-careerjet-job';

    protected $description = 'Import Careerjet job JSON into JobDD';

    public function handle(): int
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

            $jobs = json_decode($json, true);

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
                    $location = trim((string) ($job['locations'] ?? ''));
                    $description = (string) ($job['description'] ?? '');
                    $sourceUrl = trim((string) ($job['url'] ?? ''));

                    $salaryMinRaw = $job['salary_min'] ?? null;
                    $salaryMaxRaw = $job['salary_max'] ?? null;
                    $salaryType = $job['salary_type'] ?? null;

                    if (!$companyName) {
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
                            'updated_at' => now(),
                        ];

                        if (!$jobPosting) {
                            $jobPostingData['created_at'] = now();

                            $jobPostingId = DB::table('job_postings')
                                ->insertGetId($jobPostingData);

                            $createdCount++;
                        } else {
                            $jobPostingId = $jobPosting->id;

                            DB::table('job_postings')
                                ->where('id', $jobPostingId)
                                ->update($jobPostingData);

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
                                ->update($routeData);

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
