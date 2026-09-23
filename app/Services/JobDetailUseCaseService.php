<?php

namespace App\Services;

use App\Models\ApplicationRoute;
use App\Models\UserQuery;
use App\Support\PublishedJobDecisionPresenter;
use App\Support\SeekerPreferences;
use Illuminate\Support\Facades\DB;

class JobDetailUseCaseService
{
    public function __construct(private JobSelectionUseCaseService $selection, private ContextRoleClassifier $classifier) {}

    public function run(UserQuery $query, int $id, array $requirements = []): array
    {
        // Read one committed publication: snapshot, facts and routes change atomically on approval.
        return DB::transaction(fn () => $this->read($query, $id, $requirements));
    }

    private function read(UserQuery $query, int $id, array $requirements): array
    {
        $data = $this->selection->run($query, [$id], $requirements);
        $item = $data['items'][0];
        $contexts = [];
        foreach ($item['fit']['axes'] as $axis) {
            foreach ($axis['evidence'] as $evidence) {
                if ($evidence['kind'] === 'job_fact') {
                    $contexts[$evidence['job_fact_id']] = $evidence['context'];
                }
            }
        }
        $data['presence_facts'] = [];
        foreach ($item['job']->getRelation('jobFacts') as $fact) {
            $data['presence_facts'][] = ['fact' => $fact,
                'context' => $fact->extraction_method === 'company_self_reported'
                    ? ['role' => $fact->context_role ?? 'unknown', 'reason' => '企業がフォームで申告した内容です。公開確認は真偽の保証を意味しません。', 'matched_contexts' => [], 'notes' => []]
                    : ($contexts[$fact->id] ?? $this->classifier->classify($item['job'], $fact))];
        }
        $data['application_routes'] = ApplicationRoute::query()->where('job_posting_id', $id)
            ->whereIn('route_type', ['direct', 'agent', 'platform'])
            ->orderByRaw("CASE route_type WHEN 'direct' THEN 0 WHEN 'agent' THEN 1 ELSE 2 END")
            ->orderBy('id')->get();

        $snapshot = $item['job']->publishedProfile()->first();
        if ($snapshot !== null) {
            // An unreadable publication must never fall back to the editable JobPosting.
            abort_unless(($snapshot->profile_data['schema_version'] ?? null) === 1, 404);
            $facts = $item['job']->getRelation('jobFacts');
            $facts->load('source');
            $data['decision_view'] = (new PublishedJobDecisionPresenter)->present($snapshot, $item['fit'], $facts, $data['application_routes'], SeekerPreferences::read($query));
        }

        return $data;
    }
}
