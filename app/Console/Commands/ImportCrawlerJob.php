<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportCrawlerJob extends Command
{
    protected $signature = 'crawler:import-job';

    protected $description = 'Import a crawled job JSON file into JobDD';

    public function handle(): int
    {
        $runId = DB::table('crawl_runs')->insertGetId([
            'crawler_name' => 'mhi_job_import',
            'status' => 'running',
            'started_at' => now(),
            'fetched_count' => 1,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $path = 'crawler/mhi_job.json';

            if (!Storage::disk('local')->exists($path)) {
                throw new \RuntimeException('Crawler JSON not found.');
            }

            $json = Storage::disk('local')->get($path);

            $job = json_decode($json, true);

            if (!is_array($job)) {
                throw new \RuntimeException('Invalid JSON.');
            }

            $companyName = $job['company_name'] ?? null;
            $title = $job['title'] ?? null;
            $employmentType = $job['employment_type'] ?? null;
            $location = $job['location'] ?? null;
            $sourceUrl = $job['source_url'] ?? null;

            if (!$companyName || !$title || !$sourceUrl) {
                throw new \RuntimeException('Required crawler data is missing.');
            }

            $createdCount = 0;
            $updatedCount = 0;

            DB::transaction(function () use (
                $companyName,
                $title,
                $employmentType,
                $location,
                $sourceUrl,
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
                        'website_url' => 'https://www.mhi.com/',
                        'industry' => '製造業',
                        'region' => '東京都',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $createdCount++;
                } else {
                    $companyId = $company->id;

                    DB::table('companies')
                        ->where('id', $companyId)
                        ->update([
                            'website_url' => 'https://www.mhi.com/',
                            'industry' => '製造業',
                            'updated_at' => now(),
                        ]);

                    $updatedCount++;
                }

                /*
                 * 2. Job posting
                 */
                $jobPosting = DB::table('job_postings')
                    ->where('source_url', $sourceUrl)
                    ->first();

                $jobPostingData = [
                    'company_id' => $companyId,
                    'title' => $title,
                    'occupation' => '機械設計',
                    'industry' => '製造業',
                    'region' => '長崎県',
                    'salary_min' => null,
                    'salary_max' => null,
                    'description' => '',
                    'employment_type' => $employmentType,
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
                    'source_type' => 'official_recruiting',
                    'title' => $title,
                    'publisher' => $companyName,
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
                 * 4. Direct application route
                 */
                $route = DB::table('application_routes')
                    ->where('job_posting_id', $jobPostingId)
                    ->where('route_type', 'direct')
                    ->first();

                $routeData = [
                    'job_posting_id' => $jobPostingId,
                    'route_type' => 'direct',
                    'agency_id' => null,
                    'platform_id' => null,
                    'application_url' => $sourceUrl,
                    'availability_status' => 'available',
                    'notes' => '企業公式採用導線上の求人ページから応募できます。',
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

            DB::table('crawl_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'success',
                    'finished_at' => now(),
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'failed_count' => 0,
                    'updated_at' => now(),
                ]);

            $this->info('Crawler job imported successfully.');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Company', $companyName],
                    ['Title', $title],
                    ['Employment', $employmentType ?? ''],
                    ['Location', $location ?? ''],
                    ['Source', $sourceUrl],
                    ['Created', $createdCount],
                    ['Updated', $updatedCount],
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

            $this->error('Import failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
