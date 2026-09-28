<?php

namespace App\Services;

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Support\SeekerPreferences;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Throwable;

/** Transient display ordering only. No score, writes or evidence relations. */
class JobListSort
{
    public const OPTIONS = ['fit' => '希望条件に近い順', 'newest' => '新着順', 'salary_desc' => '年収が高い順'];

    public static function normalize(mixed $sort): string
    {
        return is_string($sort) && isset(self::OPTIONS[$sort]) ? $sort : 'fit';
    }

    public function order(Collection $jobs, UserQuery $query, string $sort, array $requirements): Collection
    {
        $sort = self::normalize($sort);
        $keys = [];
        $facts = collect();
        $tools = array_column($requirements['desired'] ?? [], 'fact_key');
        if ($sort === 'fit' && $tools !== [] && $jobs->isNotEmpty()) {
            // Only requested Tool facts. No Source/Evidence/Authoring relation hydration.
            $facts = JobFact::query()->whereIn('job_posting_id', $jobs->modelKeys())
                ->whereIn('fact_key', $tools)->orderBy('id')->get()->groupBy('job_posting_id');
        }
        $fit = app(JobFitService::class);
        $input = SeekerPreferences::fitInput($query);
        foreach ($jobs as $job) {
            $date = self::date($job->getRawOriginal('published_at'))
                ?? self::date($job->getRawOriginal('first_seen_at'));
            $newest = [$date === null ? 1 : 0, -($date ?? 0)];
            if ($sort === 'fit') {
                // Reuse the authoritative evaluator, discard per-job evidence immediately.
                $summary = $fit->evaluate($input, $job, $facts->get($job->id, collect())->all(), $requirements)['summary'];
                $keys[$job->id] = [...self::fitKey($summary), ...$newest, (int) $job->id];
            } elseif ($sort === 'salary_desc') {
                $keys[$job->id] = [...self::salaryKey($job), (int) $job->id];
            } else {
                $keys[$job->id] = [...$newest, (int) $job->id];
            }
        }

        return $jobs->sort(fn ($a, $b) => $keys[$a->id] <=> $keys[$b->id])->values();
    }

    public static function fitKey(array $summary): array
    {
        return [$summary['confirmed_mismatches'], -$summary['confirmed_matches'], $summary['unknowns']];
    }

    public static function salaryKey(JobPosting $job): array
    {
        $valid = fn ($value) => (is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/D', $value))) && $value > 0;
        $min = $job->salary_min;
        $max = $job->salary_max;
        if (($min !== null && ! $valid($min)) || ($max !== null && ! $valid($max))
            || ($min !== null && $max !== null && $min > $max)) {
            return [2, 0, 0];
        }

        // Known floors first; upper-only ranges follow; unknown/invalid last.
        return [$min !== null ? 0 : ($max !== null ? 1 : 2), -(int) $min, -(int) $max];
    }

    private static function date(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value)) {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();

            return is_array($errors) && ($errors['warning_count'] || $errors['error_count']) ? null : $date->getTimestamp();
        } catch (Throwable) {
            return null;
        }
    }
}
