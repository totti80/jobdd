<?php

namespace App\Services;

use App\Models\ApplicationRoute;
use App\Models\JobPublishedProfile;
use App\Models\UserQuery;
use App\Support\JobComparisonPresenter;
use App\Support\SeekerPreferences;
use Illuminate\Support\Facades\DB;

/** Display-only material; selection order and the existing Fit contract stay unchanged. */
class JobComparisonUseCaseService
{
    public function __construct(private JobSelectionUseCaseService $selection) {}

    public function run(UserQuery $query, array $ids, array $requirements = []): array
    {
        return DB::transaction(function () use ($query, $ids, $requirements) {
            $data = $this->selection->run($query, $ids, $requirements);
            $snapshots = JobPublishedProfile::whereIn('job_posting_id', $ids)->get()->keyBy('job_posting_id');
            $routes = ApplicationRoute::whereIn('job_posting_id', $ids)->where('availability_status', 'available')->whereNull('unavailable_at')->get()->groupBy('job_posting_id');
            foreach ($data['items'] as &$item) {
                $snapshot = $snapshots->get($item['job']->id);
                abort_if($snapshot && ($snapshot->profile_data['schema_version'] ?? null) !== 1, 404);
                $item = (new JobComparisonPresenter)->present($item, $snapshot, $routes->get($item['job']->id) ?? collect());
            }
            unset($item);
            $data['comparison_preferences'] = SeekerPreferences::comparison(SeekerPreferences::read($query), []);

            return $data;
        });
    }
}
