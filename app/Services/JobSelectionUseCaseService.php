<?php

namespace App\Services;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\UserQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Resolves up to three explicitly selected candidates, preserving the user's order. */
class JobSelectionUseCaseService
{
    private const REGIONS = ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'];

    public function __construct(private JobFitRunnerService $runner) {}

    public function run(UserQuery $query, array $ids, array $requirements = []): array
    {
        if (! in_array($query->occupation, ['機械設計', '電気設計'], true)
            || ($query->region !== null && ! in_array($query->region, self::REGIONS, true))
            || ! array_is_list($ids) || count($ids) < 1 || count($ids) > 3
            || count(array_unique($ids, SORT_REGULAR)) !== count($ids)
            || count(array_filter($ids, fn ($id) => is_int($id) && $id > 0)) !== count($ids)) {
            throw new InvalidArgumentException('Invalid candidate selection.');
        }

        // Mirror the v0.1 Discovery contract for bounded ID access, not its first page.
        // Contract-parity tests guard this boundary; core Discovery is deliberately unchanged.
        $loaded = JobPosting::query()->whereIn('id', $ids)->where('occupation', $query->occupation)
            ->whereIn('region', self::REGIONS)->whereNull('unavailable_at')
            ->with(['jobFacts' => fn ($facts) => $facts->orderBy('id')])->get()->keyBy('id');
        if ($loaded->count() !== count($ids) || $loaded->contains(fn ($job) => ! $this->candidateSource($job->source_url))) {
            throw (new ModelNotFoundException)->setModel(JobPosting::class);
        }
        $jobs = array_map(fn ($id) => $loaded->get($id), $ids);
        $facts = [];
        foreach ($jobs as $job) {
            $facts[$job->id] = $job->getRelation('jobFacts')->all();
        }
        $fits = $this->runner->run($query, $jobs, $facts, $requirements);
        $companies = Company::query()->whereIn('id', $loaded->pluck('company_id')->unique())->pluck('name', 'id');
        $items = [];
        foreach ($jobs as $i => $job) {
            $items[] = ['job' => $job, 'company_name' => $companies->get($job->company_id, '会社名未確認'), 'fit' => $fits[$i]];
        }

        return ['query' => $query->only(['public_id', 'occupation', 'region', 'salary_min', 'salary_max']),
            'selected_tools' => array_column($requirements['desired'] ?? [], 'fact_key'), 'items' => $items];
    }

    private function candidateSource(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        foreach (['example.com', 'example.org', 'example.net', 'test', 'invalid', 'localhost', 'example'] as $fixture) {
            if ($host === $fixture || str_ends_with($host, '.'.$fixture)) {
                return false;
            }
        }

        return true;
    }
}
