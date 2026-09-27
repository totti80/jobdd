<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\UserQuery;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/** Reads comparison candidates only; Fit, ranking and persistence belong elsewhere. */
class JobDiscoveryService
{
    private const OCCUPATIONS = ['機械設計', '電気設計'];

    private const REGIONS = ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'];

    private const FIXTURE_HOSTS = ['example.com', 'example.org', 'example.net', 'test', 'invalid', 'localhost', 'example'];

    /**
     * @param  int|null  $total  Output-only count after the source gate, before slicing.
     * @return Collection<int, JobPosting> Full models with all jobFacts loaded in ID order.
     *
     * A caller-owned consistent snapshot is needed for repeatability across concurrent updates.
     */
    public function discover(UserQuery $query, int $limit = 20, int $offset = 0, ?int &$total = null): Collection
    {
        if (! in_array($query->occupation, self::OCCUPATIONS, true)
            || ($query->region !== null && ! in_array($query->region, self::REGIONS, true))
            || $limit < 1 || $limit > 50 || $offset < 0) {
            throw new InvalidArgumentException('Invalid discovery occupation, region or pagination.');
        }

        $scope = JobPosting::query()->forPublic()->where('status', 'published')->where('occupation', $query->occupation)
            ->whereIn('region', self::REGIONS)->whereNull('unavailable_at');
        if ($query->region !== null) {
            $scope->orderByRaw('CASE WHEN region = ? THEN 0 ELSE 1 END ASC', [$query->region]);
        }
        $scope->orderBy('id');

        // Gate before pagination; only this lightweight projection spans the whole scope.
        $eligible = (clone $scope)->get(['id', 'region', 'source_url'])
            ->filter(fn (JobPosting $job) => $this->validSource($job->source_url));
        // Display-only metadata from the already-loaded, gated population. No extra SQL.
        $total = $eligible->count();
        $ids = $eligible->values()->slice($offset, $limit)->pluck('id')->all();
        if ($ids === []) {
            return new Collection;
        }

        return $scope->whereIn('id', $ids)
            ->with(['jobFacts' => fn ($facts) => $facts->orderBy('id')])->get();
    }

    private function validSource(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        foreach (self::FIXTURE_HOSTS as $fixture) {
            if ($host === $fixture || str_ends_with($host, '.'.$fixture)) {
                return false;
            }
        }

        return true;
    }
}
