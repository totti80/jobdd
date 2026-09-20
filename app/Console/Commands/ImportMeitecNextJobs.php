<?php

namespace App\Console\Commands;

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\DailyDiscoveryImporter;
use App\Services\DirectLookup\CompanyUrlEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportMeitecNextJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawler:import-meitec-next-jobs {--daily-input= : Internal daily payload filename} {--dry-run : Daily import preview only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Meitec Next job postings from crawler JSON';

    /**
     * メイテックネクストの agencies.id
     */
    private const MEITEC_NEXT_AGENCY_ID = 7;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('daily-input') || $this->option('dry-run')) {
            return app(DailyDiscoveryImporter::class)->command($this, 'meitec_next');
        }

        $path = storage_path(
            'app/private/crawler/meitec_next_jobs.json'
        );

        if (! File::exists($path)) {
            $this->error(
                "JSON file not found: {$path}"
            );

            return self::FAILURE;
        }

        $json = File::get($path);

        $data = json_decode(
            $json,
            true
        );

        if (! is_array($data)) {
            $this->error(
                'Invalid JSON format.'
            );

            return self::FAILURE;
        }

        $jobs = $data['jobs'] ?? [];

        if (! is_array($jobs)) {
            $this->error(
                'jobs key is missing or invalid.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Meitec Next import start'
        );

        $this->info(
            'JSON jobs: '.count($jobs)
        );

        $createdCompanies = 0;
        $createdJobs = 0;
        $updatedJobs = 0;
        $createdRoutes = 0;
        $updatedRoutes = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($jobs as $index => $jobData) {
            $number = $index + 1;

            $companyName = trim(
                (string) ($jobData['company_name'] ?? '')
            );

            $title = trim(
                (string) ($jobData['title'] ?? '')
            );

            $region = trim(
                (string) ($jobData['region'] ?? '')
            );

            $sourceUrl = trim(
                (string) ($jobData['source_url'] ?? '')
            );

            $externalId = trim(
                (string) ($jobData['external_id'] ?? $jobData['id'] ?? $sourceUrl)
            );

            if (
                $companyName === ''
                || $title === ''
                || $sourceUrl === ''
            ) {
                $this->warn(
                    "[{$number}] skipped: required field missing"
                );

                $skipped++;

                continue;
            }

            try {
                DB::transaction(
                    function () use (
                        $jobData,
                        $companyName,
                        $title,
                        $region,
                        $sourceUrl,
                        $externalId,
                        &$createdCompanies,
                        &$createdJobs,
                        &$updatedJobs,
                        &$createdRoutes,
                        &$updatedRoutes
                    ) {
                        // ----------------------------------------
                        // Company
                        // ----------------------------------------

                        $company = Company::firstOrCreate(
                            [
                                'name' => $companyName,
                            ],
                            [
                                'website_url' => null,
                                'industry' => null,
                                'region' => (
                                    $region !== ''
                                    ? $region
                                    : null
                                ),
                            ]
                        );

                        if ($company->wasRecentlyCreated) {
                            $createdCompanies++;
                        }

                        // ----------------------------------------
                        // JobPosting
                        // ----------------------------------------

                        $jobPosting = JobPosting::query()
                            ->where('provider_key', 'meitec_next')
                            ->where('external_id', $externalId)
                            ->first()
                            ?? JobPosting::updateOrCreate(
                                [
                                    'company_id' => $company->id,
                                    'title' => $title,
                                    'region' => (
                                        $region !== ''
                                        ? $region
                                        : null
                                    ),
                                ],
                                [
                                    'occupation' => (
                                        $jobData['occupation']
                                        ?? '機械設計'
                                    ),

                                    'industry' => null,

                                    'salary_min' => (
                                        $jobData['salary_min']
                                        ?? null
                                    ),

                                    'salary_max' => (
                                        $jobData['salary_max']
                                        ?? null
                                    ),

                                    'description' => (
                                        $jobData['description']
                                        ?? null
                                    ),

                                    'employment_type' => (
                                        $jobData['employment_type']
                                        ?? null
                                    ),

                                    'source_url' => (
                                        $sourceUrl
                                    ),
                                    'provider_key' => 'meitec_next',
                                    'external_id' => $externalId,
                                    'first_seen_at' => now(),
                                    'last_seen_at' => now(),
                                    'unavailable_at' => null,
                                ]
                            );

                        $jobPosting->update([
                            'provider_key' => 'meitec_next',
                            'external_id' => $externalId,
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                        ]);

                        if ($jobPosting->wasRecentlyCreated) {
                            $createdJobs++;
                        } else {
                            $updatedJobs++;
                        }

                        // ----------------------------------------
                        // ApplicationRoute
                        // ----------------------------------------

                        app(CompanyUrlEvidence::class)->save($jobPosting->id, $company->id, $jobData, 'meitec_next');

                        $route = ApplicationRoute::query()
                            ->where('provider_key', 'meitec_next')
                            ->where('external_id', $externalId)
                            ->first()
                            ?? ApplicationRoute::query()
                                ->where('job_posting_id', $jobPosting->id)
                                ->where('route_type', 'agent')
                                ->where('agency_id', self::MEITEC_NEXT_AGENCY_ID)
                                ->first();

                        $routeData = [
                            'job_posting_id' => $jobPosting->id,
                            'route_type' => 'agent',
                            'agency_id' => self::MEITEC_NEXT_AGENCY_ID,
                            'platform_id' => null,
                            'application_url' => $sourceUrl,
                            'availability_status' => 'available',
                            'notes' => 'メイテックネクスト公開求人から自動取得',
                            'first_seen_at' => $route?->first_seen_at ?? now(),
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                            'provider_key' => 'meitec_next',
                            'external_id' => $externalId,
                        ];

                        if ($route) {
                            $route->update($routeData);
                        } else {
                            $route = ApplicationRoute::create($routeData);
                        }

                        if ($route->wasRecentlyCreated) {
                            $createdRoutes++;
                        } else {
                            $updatedRoutes++;
                        }
                    }
                );

                $this->line(
                    "[{$number}] {$companyName} | {$title}"
                );
            } catch (Throwable $e) {
                $errors++;

                $this->error(
                    "[{$number}] import error: "
                        .$companyName
                        .' | '
                        .$title
                );

                $this->error(
                    $e->getMessage()
                );
            }
        }

        if (($data['completed'] ?? false) === true) {
            $seenIds = collect($jobs)
                ->map(fn (array $row) => trim((string) ($row['external_id'] ?? $row['id'] ?? $row['source_url'] ?? '')))
                ->filter()
                ->values();

            ApplicationRoute::query()
                ->where('provider_key', 'meitec_next')
                ->where('availability_status', 'available')
                ->when($seenIds->isNotEmpty(), fn ($query) => $query->whereNotIn('external_id', $seenIds))
                ->update([
                    'availability_status' => 'unavailable',
                    'unavailable_at' => now(),
                ]);
        }

        $this->newLine();

        $this->info(
            '=============================='
        );

        $this->info(
            'Import completed'
        );

        $this->info(
            "companies created: {$createdCompanies}"
        );

        $this->info(
            "jobs created: {$createdJobs}"
        );

        $this->info(
            "jobs updated: {$updatedJobs}"
        );

        $this->info(
            "routes created: {$createdRoutes}"
        );

        $this->info(
            "routes updated: {$updatedRoutes}"
        );

        $this->info(
            "skipped: {$skipped}"
        );

        $this->info(
            "errors: {$errors}"
        );

        $this->info(
            '=============================='
        );

        return
            $errors === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
