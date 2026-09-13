<?php

namespace App\Console\Commands;

use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Services\OccupationNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildDirectLookupQueue extends Command
{
    protected $signature = 'jobdd:build-direct-lookup-queue {--limit=10 : Maximum number of companies}';

    protected $description = 'Build a private snapshot of companies awaiting official Direct verification (no crawling).';

    public function handle(OccupationNormalizer $normalizer): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $this->error('limit must be a positive integer.');

            return self::FAILURE;
        }

        // Count distinct available jobs, not routes or discovery-source rows.
        $directJobs = JobPosting::query()
            ->whereNull('unavailable_at')
            ->whereHas('company', fn ($query) => $query->where('name', '!=', 'A製作所'))
            ->whereHas('applicationRoutes', fn ($query) => $query
                ->where('route_type', 'direct')
                ->where('availability_status', 'available')
                ->whereNull('unavailable_at'))
            ->get();
        $coverage = [];
        foreach ($directJobs as $job) {
            $key = $this->cellKey($job->region, $normalizer->normalize($job->title, $job->description));
            $coverage[$key] = ($coverage[$key] ?? 0) + 1;
        }

        $candidates = DirectReverseLookupCandidate::query()
            ->with('company')
            ->whereIn('direct_status', ['unverified', 'crawl_failed'])
            ->whereNotNull('region')->where('region', '!=', '')
            ->whereIn('occupation', ['機械設計', '電気設計', '施工管理'])
            ->where('matching_job_count', '>', 0)
            ->whereHas('company', fn ($query) => $query->where('name', '!=', 'A製作所')->where('name', '!=', ''))
            // Exclude only the confirmed company/cell, regardless of discovery source.
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('direct_reverse_lookup_candidates as confirmed_candidates')
                ->where('confirmed_candidates.direct_status', 'confirmed')
                ->whereColumn('confirmed_candidates.company_id', 'direct_reverse_lookup_candidates.company_id')
                ->whereColumn('confirmed_candidates.region', 'direct_reverse_lookup_candidates.region')
                ->whereColumn('confirmed_candidates.occupation', 'direct_reverse_lookup_candidates.occupation'))
            ->orderBy('id')
            ->get()
            ->sortBy(function (DirectReverseLookupCandidate $candidate) use ($coverage) {
                $region = $this->prefecture($candidate->region);
                $count = $coverage[$this->cellKey($candidate->region, $candidate->occupation)] ?? 0;

                return [
                    in_array($region, ['大阪府', '兵庫県'], true) ? 0 : 1,
                    $count === 0 ? 0 : ($count < 3 ? 1 : 2),
                    $candidate->direct_status === 'unverified' ? 0 : 1,
                    $candidate->checked_at === null ? 0 : 1,
                    $candidate->id,
                ];
            });

        $records = $candidates->groupBy('company_id')->take($limit)->map(function ($companyCandidates) use ($coverage) {
            $candidate = $companyCandidates->first();

            return [
                'company_id' => $candidate->company_id,
                'company_name' => $candidate->company->name,
                'candidate_id' => $candidate->id,
                'region' => $candidate->region,
                'occupation' => $candidate->occupation,
                'official_site_url' => $candidate->website_url ?: $candidate->company->website_url,
                'official_recruit_url' => $candidate->official_recruit_url,
                'status' => 'unverified',
                'direct_coverage' => $coverage[$this->cellKey($candidate->region, $candidate->occupation)] ?? 0,
                // Preserve every eligible discovery row; only the primary cell is a processing target.
                'discovery_candidates' => $companyCandidates->sortBy('id')->map(fn ($item) => [
                    'candidate_id' => $item->id,
                    'company_id' => $item->company_id,
                    'region' => $item->region,
                    'occupation' => $item->occupation,
                    'discovery_source' => $item->discovery_source,
                    'direct_status' => $item->direct_status,
                    'website_url' => $item->website_url,
                    'official_recruit_url' => $item->official_recruit_url,
                ])->values()->all(),
            ];
        })->values()->all();

        $path = storage_path('app/private/crawler/direct_lookup_queue.json');
        File::ensureDirectoryExists(dirname($path), 0700);
        // Atomic replacement: unchanged DB + limit produces identical bytes, never an appended queue.
        File::replace($path, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n", 0600);
        $this->info('Direct lookup queue companies: '.count($records));
        $this->info($path);

        return self::SUCCESS;
    }

    private function prefecture(?string $region): string
    {
        foreach (['大阪府', '兵庫県', '京都府', '滋賀県', '奈良県', '和歌山県'] as $prefecture) {
            if (str_starts_with((string) $region, $prefecture)) {
                return $prefecture;
            }
        }

        return (string) $region;
    }

    private function cellKey(?string $region, ?string $occupation): string
    {
        return $this->prefecture($region).'|'.$occupation;
    }
}
