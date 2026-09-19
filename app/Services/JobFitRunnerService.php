<?php

namespace App\Services;

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Applies Fit to loaded candidates in their given order; never discovers or filters jobs. */
class JobFitRunnerService
{
    public function __construct(private JobFitService $fit) {}

    /**
     * @param  Collection|array<JobPosting>  $jobs
     * @param  array<int, list<JobFact>>  $factsByJob  Explicit empty lists distinguish no Facts from missing preload.
     */
    public function run(UserQuery $query, Collection|array $jobs, array $factsByJob, array $confirmedRequirements = []): array
    {
        $ids = [];
        foreach ($jobs as $job) {
            if (! $job instanceof JobPosting || ! is_numeric($job->id) || (int) $job->id <= 0
                || (string) (int) $job->id !== (string) $job->id || isset($ids[(int) $job->id])) {
                throw new InvalidArgumentException('Candidates must have distinct saved job IDs.');
            }
            $id = (int) $job->id;
            $ids[$id] = true;
            if (! array_key_exists($id, $factsByJob) || ! is_array($factsByJob[$id]) || ! array_is_list($factsByJob[$id])) {
                throw new InvalidArgumentException('Every candidate requires a preloaded Fact list.');
            }
            foreach ($factsByJob[$id] as $fact) {
                if (! $fact instanceof JobFact || (string) $fact->job_posting_id !== (string) $job->id) {
                    throw new InvalidArgumentException('A Fact does not belong to its candidate.');
                }
            }
        }
        if (array_diff(array_keys($factsByJob), array_keys($ids)) !== []) {
            throw new InvalidArgumentException('Fact groups must only contain candidate IDs.');
        }
        $results = [];
        foreach ($jobs as $job) {
            $results[] = $this->fit->evaluate($query, $job, $factsByJob[(int) $job->id], $confirmedRequirements);
        }

        return $results;
    }
}
