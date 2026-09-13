<?php

namespace App\Console\Commands;

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Models\Source;
use App\Services\OccupationNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDirectReverseLookupQueue extends Command
{
    protected $signature = 'jobdd:process-direct-reverse-lookup-queue {--input=crawler/data/direct_reverse_lookup_slice.json}';

    public function handle(OccupationNormalizer $normalizer): int
    {
        $records = json_decode((string) file_get_contents(base_path($this->option('input'))), true);
        if (! is_array($records)) {
            $this->error('Queue JSON is invalid.');
            return self::FAILURE;
        }

        foreach ($records as $record) {
            $this->process($record, $normalizer);
        }

        return self::SUCCESS;
    }

    private function process(array $record, OccupationNormalizer $normalizer): void
    {
        foreach (['company_name', 'region', 'occupation', 'official_site_url', 'official_recruit_url', 'status'] as $field) {
            if (blank($record[$field] ?? null)) {
                throw new \InvalidArgumentException("Missing {$field}");
            }
        }

        if (! in_array($record['status'], ['confirmed', 'not_found', 'crawl_failed'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        if ($record['status'] === 'confirmed') {
            foreach (['external_id', 'title', 'description', 'employment_type', 'source_url', 'application_url'] as $field) {
                if (blank($record[$field] ?? null)) {
                    throw new \InvalidArgumentException("Confirmed record missing {$field}");
                }
            }
            if ($normalizer->normalize($record['title'], $record['description']) !== $record['occupation']) {
                throw new \InvalidArgumentException('Occupation is not normalized');
            }
        }

        DB::transaction(function () use ($record) {
            $company = Company::query()->where('name', $record['company_name'])->firstOrFail();
            DirectReverseLookupCandidate::query()
                ->where('company_id', $company->id)
                ->where('region', $record['region'])
                ->where('occupation', $record['occupation'])
                ->update([
                    'website_url' => $record['official_site_url'],
                    'official_recruit_url' => $record['official_recruit_url'],
                    'direct_status' => $record['status'],
                    'checked_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($record['status'] !== 'confirmed') {
                return;
            }

            $job = JobPosting::query()->updateOrCreate(
                ['provider_key' => 'official_direct', 'external_id' => $record['external_id']],
                [
                    'company_id' => $company->id,
                    'title' => $record['title'],
                    'occupation' => $record['occupation'],
                    'industry' => $record['industry'] ?? null,
                    'region' => $record['region'],
                    'description' => $record['description'],
                    'employment_type' => $record['employment_type'],
                    'source_url' => $record['source_url'],
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'unavailable_at' => null,
                ]
            );
            Source::query()->updateOrCreate(
                ['url' => $record['source_url']],
                ['source_type' => 'official_recruiting', 'title' => $record['title'], 'publisher' => $company->name, 'fetched_at' => now()]
            );
            ApplicationRoute::query()->updateOrCreate(
                ['provider_key' => 'official_direct', 'external_id' => $record['external_id']],
                [
                    'job_posting_id' => $job->id,
                    'route_type' => 'direct',
                    'application_url' => $record['application_url'],
                    'availability_status' => 'available',
                    'notes' => 'Official site, recruiting page, job, occupation, region, and application URL reviewed.',
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'unavailable_at' => null,
                ]
            );
        });
    }
}

