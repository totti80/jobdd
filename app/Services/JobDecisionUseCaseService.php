<?php

namespace App\Services;

use App\Models\Company;
use App\Models\UserQuery;
use InvalidArgumentException;

class JobDecisionUseCaseService
{
    public const TOOLS = ['autocad' => 'AutoCAD', 'inventor' => 'Inventor', 'solidworks' => 'SolidWorks',
        'catia' => 'CATIA', 'creo' => 'Creo', 'nx' => 'NX', 'electrical_cad' => '電気CAD'];

    public function __construct(private JobDiscoveryService $discovery, private JobFitRunnerService $runner) {}

    public function run(UserQuery $query, int $page = 1, array $confirmedRequirements = []): array
    {
        if ($page < 1 || $page > intdiv(PHP_INT_MAX, 20)) {
            throw new InvalidArgumentException('Invalid page.');
        }
        $candidates = $this->discovery->discover($query, 21, ($page - 1) * 20);
        $jobs = $candidates->take(20)->values();
        $facts = [];
        foreach ($jobs as $job) {
            $facts[$job->id] = $job->getRelation('jobFacts')->all();
        }
        $fits = $this->runner->run($query, $jobs, $facts, $confirmedRequirements);
        $companies = $jobs->isEmpty() ? collect() : Company::query()
            ->whereIn('id', $jobs->pluck('company_id')->unique())->pluck('name', 'id');
        $items = [];
        foreach ($jobs as $index => $job) {
            $items[] = ['job' => $job, 'company_name' => $companies->get($job->company_id, '会社名未確認'), 'fit' => $fits[$index]];
        }

        return [
            'query' => $query->only(['public_id', 'occupation', 'region', 'salary_min', 'salary_max']),
            'custom_tools' => $query->detailed_skills['custom_tools'] ?? null,
            'selected_tools' => array_column($confirmedRequirements['desired'] ?? [], 'fact_key'),
            'items' => $items,
            'pagination' => ['page' => $page, 'has_previous' => $page > 1, 'has_next' => $candidates->count() > 20],
        ];
    }
}
