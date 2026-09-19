<?php

namespace App\Services;

use App\Models\ApplicationRoute;
use App\Models\UserQuery;

class JobDetailUseCaseService
{
    public function __construct(private JobSelectionUseCaseService $selection, private ContextRoleClassifier $classifier) {}

    public function run(UserQuery $query, int $id, array $requirements = []): array
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
                'context' => $contexts[$fact->id] ?? $this->classifier->classify($item['job'], $fact)];
        }
        $data['application_routes'] = ApplicationRoute::query()->where('job_posting_id', $id)
            ->whereIn('route_type', ['direct', 'agent', 'platform'])
            ->orderByRaw("CASE route_type WHEN 'direct' THEN 0 WHEN 'agent' THEN 1 ELSE 2 END")
            ->orderBy('id')->get();

        return $data;
    }
}
