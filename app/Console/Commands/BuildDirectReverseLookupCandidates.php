<?php

namespace App\Console\Commands;

use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Services\OccupationNormalizer;
use Illuminate\Console\Command;

class BuildDirectReverseLookupCandidates extends Command
{
  protected $signature = 'jobdd:build-direct-reverse-lookup';

  protected $description = 'Build unverified Direct candidates from discovered Agent and Platform companies.';

  public function handle(OccupationNormalizer $normalizer): int
  {
    $jobs = JobPosting::query()
      ->with('company')
      ->whereHas('applicationRoutes', fn($query) => $query
        ->whereIn('route_type', ['agent', 'platform'])
        ->where('availability_status', 'available')
        ->whereNull('unavailable_at'))
      ->get()
      ->filter(fn(JobPosting $job) => $job->company?->name !== 'A製作所');

    $count = 0;
    foreach ($jobs->groupBy(fn(JobPosting $job) => $job->company_id) as $companyJobs) {
      $company = $companyJobs->first()->company;
      foreach (
        $companyJobs->groupBy(fn(JobPosting $job) => implode('|', [
          $job->region,
          $normalizer->normalize($job->title, $job->description),
        ])) as $cellJobs
      ) {
        $job = $cellJobs->first();
        $occupation = $normalizer->normalize($job->title, $job->description);

        if (!in_array($occupation, ['機械設計', '電気設計', '施工管理'], true)) {
          continue;
        }

        foreach ($cellJobs->flatMap(fn(JobPosting $item) => $item->applicationRoutes)->pluck('provider_key')->filter()->unique() as $provider) {
          DirectReverseLookupCandidate::updateOrCreate(
            [
              'company_id' => $company->id,
              'region' => $job->region,
              'occupation' => $occupation,
              'discovery_source' => $provider,
            ],
            [
              'matching_job_count' => $cellJobs->count(),
              'website_url' => $company->website_url,
              'last_seen_at' => now(),
            ]
          );
          $count++;
        }
      }
    }

    $this->info("Direct reverse lookup candidates updated: {$count}");
    return self::SUCCESS;
  }
}
