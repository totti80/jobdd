<?php

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\ContextRoleClassifier;
use App\Services\JobFitRunnerService;
use App\Services\JobFitService;
use App\Services\OccupationNormalizer;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

function runnerFixture(): array
{
    $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/job_fit/manual_examples.json'), true, 512, JSON_THROW_ON_ERROR);
    $jobs = $facts = [];
    // Deliberately non-ID, non-Fit order; caller order must survive.
    foreach ([21, 230, 8] as $id) {
        $case = array_values(array_filter($data['cases'], fn ($c) => $c['job']['id'] === $id))[0];
        $job = new JobPosting;
        $job->setRawAttributes($case['job']);
        $jobs[] = $job;
        $facts[$id] = array_map(function ($attrs) {
            $fact = new JobFact;
            $fact->setRawAttributes($attrs);

            return $fact;
        }, $case['facts']);
    }

    return [new UserQuery(['occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600]), $jobs, $facts,
        ['desired' => [['fact_key' => 'solidworks', 'action' => 'use'], ['fact_key' => 'autocad', 'action' => 'use']], 'hard_axes' => ['region']]];
}

function sliceFitService(): JobFitService
{
    return new JobFitService(new ContextRoleClassifier, new OccupationNormalizer);
}

test('applies one query and requirement set to every candidate without changing Fit output', function ($collection) {
    [$query, $jobs, $facts, $requirements] = runnerFixture();
    $fit = sliceFitService();
    $runner = new JobFitRunnerService($fit);
    $results = $runner->run($query, $collection ? new Collection($jobs) : $jobs, $facts, $requirements);
    expect($results)->toHaveCount(count($jobs))
        ->and(array_column($results, 'job_posting_id'))->toBe([21, 230, 8]);
    foreach ($jobs as $i => $job) {
        expect($results[$i])->toBe($fit->evaluate($query, $job, $facts[$job->id], $requirements))
            ->and(array_column($results[$i]['axes'], 'key'))->toBe(['occupation', 'region', 'salary', 'tool_use:solidworks', 'tool_use:autocad']);
        foreach ($results[$i]['axes'] as $axis) {
            foreach ($axis['evidence'] as $evidence) {
                if ($evidence['kind'] === 'job_fact') {
                    expect(in_array($evidence['job_fact_id'], array_map(fn ($f) => $f->id, $facts[$job->id]), true))->toBeTrue();
                }
            }
        }
    }
})->with([false, true]);

test('preserves unknown jobs and hard mismatches without filtering', function () {
    [$query, $jobs, $facts, $requirements] = runnerFixture();
    $results = (new JobFitRunnerService(sliceFitService()))->run($query, $jobs, $facts, $requirements);
    expect($results)->toHaveCount(3)
        ->and($results[0]['summary']['hard_mismatch_keys'])->toContain('region')
        ->and($results[0]['summary']['unknowns'])->toBeGreaterThan(0)
        ->and($results[2]['summary']['hard_mismatch_keys'])->toContain('region');
    foreach ($results as $result) {
        expect($result)->not->toHaveKeys(['score', 'rank', 'ranking', 'overall_status', 'top3']);
    }
});

test('explicit empty Fact groups allow no-Fact candidates', function () {
    [$query, $jobs, , $requirements] = runnerFixture();
    $results = (new JobFitRunnerService(sliceFitService()))->run($query, $jobs, [21 => [], 230 => [], 8 => []], $requirements);
    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        foreach (array_slice($result['axes'], 3) as $axis) {
            expect($axis['status'])->toBe('unknown')->and($axis['reason_code'])->toBe('presence_not_found');
        }
    }
});

test('zero candidates return no results or evaluations', function () {
    $fit = $this->createMock(JobFitService::class);
    $fit->expects($this->never())->method('evaluate');
    expect((new JobFitRunnerService($fit))->run(new UserQuery, [], [], []))->toBe([]);
});

test('malformed candidate and Fact mappings fail before evaluation', function ($kind) {
    [$query, $jobs, $facts, $requirements] = runnerFixture();
    switch ($kind) {
        case 'missing group': unset($facts[21]);
            break;
        case 'extra group': $facts[999] = [];
            break;
        case 'foreign fact': $facts[21][] = $facts[230][0];
            break;
        case 'null group': $facts[21] = null;
            break;
        case 'not a list': $facts[21] = ['fact' => $facts[21][0]];
            break;
        case 'not a fact': $facts[21] = [new stdClass];
            break;
        case 'duplicate job': $jobs[] = $jobs[0];
            break;
        case 'not a job': $jobs[] = new stdClass;
            break;
        case 'missing job ID': $jobs[0]->id = null;
            break;
    }
    $fit = $this->createMock(JobFitService::class);
    $fit->expects($this->never())->method('evaluate');
    expect(fn () => (new JobFitRunnerService($fit))->run($query, $jobs, $facts, $requirements))->toThrow(InvalidArgumentException::class);
})->with(['missing group', 'extra group', 'foreign fact', 'null group', 'not a list', 'not a fact', 'duplicate job', 'not a job', 'missing job ID']);

test('loop has no connection access or mutation and repeats identically even when Facts reorder', function () {
    [$query, $jobs, $facts, $requirements] = runnerFixture();
    $snapshot = fn () => [$query->getAttributes(), array_map(fn ($j) => $j->getAttributes(), $jobs), array_map(fn ($fs) => array_map(fn ($f) => $f->getAttributes(), $fs), $facts)];
    $before = $snapshot();
    $old = Model::getConnectionResolver();
    $resolver = $this->createMock(ConnectionResolverInterface::class);
    $resolver->expects($this->never())->method('connection');
    Model::setConnectionResolver($resolver);
    try {
        $runner = new JobFitRunnerService(sliceFitService());
        $first = $runner->run($query, $jobs, $facts, $requirements);
        expect($runner->run($query, $jobs, $facts, $requirements))->toBe($first)
            ->and($runner->run($query, $jobs, array_map('array_reverse', $facts), $requirements))->toBe($first)
            ->and($snapshot())->toBe($before);
    } finally {
        $old ? Model::setConnectionResolver($old) : Model::unsetConnectionResolver();
    }
});
