<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\JobPublishedProfile;
use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobFitService;
use App\Services\JobListSort;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function sortingFixture(int $count = 45): array
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'sort-token', 'raw_text' => 'fixture',
        'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 500]);
    $company = Company::create(['name' => '並べ替え検証会社']);
    $jobs = collect();
    for ($i = 0; $i < $count; $i++) {
        $jobs->push(JobPosting::create(['company_id' => $company->id, 'title' => '機械設計 '.($i + 1), 'occupation' => '機械設計',
            'region' => $i % 3 === 0 ? '大阪府' : '兵庫県', 'salary_min' => 400 + $i * 10, 'salary_max' => 900 + $i * 10,
            'published_at' => now()->startOfDay()->subDays($i), 'source_url' => 'https://careers.sample.jp/job/'.$i]));
    }

    return [$query, $jobs];
}

function sortingIds(array $result): array
{
    return array_map(fn ($item) => $item['job']->id, $result['items']);
}

test('fit ordering compares mismatch match unknown independently', function () {
    $key = fn ($mismatch, $match, $unknown) => JobListSort::fitKey(['confirmed_mismatches' => $mismatch, 'confirmed_matches' => $match, 'unknowns' => $unknown]);
    expect($key(0, 0, 4) < $key(1, 3, 0))->toBeTrue()
        ->and($key(0, 4, 0) < $key(0, 3, 1))->toBeTrue()
        ->and($key(0, 3, 0) < $key(0, 3, 1))->toBeTrue();
});

test('sorting precedes pagination and does not write for every mode', function (string $sort) {
    [$query, $jobs] = sortingFixture();
    $expected = match ($sort) {
        'newest' => $jobs->pluck('id')->all(),
        'salary_desc' => $jobs->reverse()->pluck('id')->all(),
        default => $jobs->sortBy(fn ($j) => [($j->region !== '兵庫県' ? 1 : 0), -($j->salary_min >= 500 ? 3 : 2), $j->salary_min >= 500 ? 0 : 1, -$j->published_at->timestamp, $j->id])->pluck('id')->all(),
    };
    DB::enableQueryLog();
    DB::flushQueryLog();
    $actual = [];
    foreach ([1, 2, 3] as $page) {
        $result = app(JobDecisionUseCaseService::class)->run($query, $page, [], $sort);
        $actual = [...$actual, ...sortingIds($result)];
        expect($result['total'])->toBe(45)->and($result['sort'])->toBe($sort);
    }
    $sql = DB::getQueryLog();
    DB::disableQueryLog();
    expect($actual)->toBe($expected)->and(array_unique($actual))->toHaveCount(45)
        ->and(array_filter($sql, fn ($q) => ! str_starts_with(strtolower($q['query']), 'select')))->toBe([]);
})->with(['fit', 'newest', 'salary_desc']);

test('newest uses published date then first seen with null last and ID tie', function () {
    [$query, $jobs] = sortingFixture(5);
    foreach ($jobs as $job) {
        $job->update(['published_at' => null, 'first_seen_at' => null]);
    }
    $jobs[0]->update(['published_at' => '2026-01-01', 'first_seen_at' => '2026-09-01']);
    $jobs[1]->update(['first_seen_at' => '2026-02-01']);
    $jobs[2]->update(['published_at' => '2026-02-01']);
    expect(sortingIds(app(JobDecisionUseCaseService::class)->run($query, 1, [], 'newest')))
        ->toBe([$jobs[1]->id, $jobs[2]->id, $jobs[0]->id, $jobs[3]->id, $jobs[4]->id]);
    foreach ($jobs as $job) {
        $job->update(['region' => '兵庫県', 'salary_min' => 600, 'salary_max' => 800]);
    }
    expect(sortingIds(app(JobDecisionUseCaseService::class)->run($query)))->toBe([$jobs[1]->id, $jobs[2]->id, $jobs[0]->id, $jobs[3]->id, $jobs[4]->id]);
});

test('salary floor upper tie and malformed values are safe', function () {
    $values = [[600, 800], [600, 900], [500, 2000], [null, 3000], [null, null], [-1, 1000], [800, 600], ['bad', 1000]];
    $jobs = collect(array_map(fn ($v, $i) => new JobPosting(['id' => $i, 'salary_min' => $v[0], 'salary_max' => $v[1]]), $values, array_keys($values)));
    expect($jobs->sortBy(fn ($job) => JobListSort::salaryKey($job))->values()->map(fn ($job) => [$job->salary_min, $job->salary_max])->all())
        ->toBe([$values[1], $values[0], $values[2], $values[3], $values[4], $values[5], $values[6], $values[7]]);
});

test('request normalizes sorting and keeps navigation context', function ($sort, $expected) {
    [$query, $jobs] = sortingFixture(21);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(route('query.jobs', ['userQuery' => $query->public_id, 'sort' => $sort, 'tools' => ['autocad']]))->assertOk();
    expect($response->viewData('sort'))->toBe($expected);
    $response->assertSee('sort='.$expected, false)->assertSee('name="sort"', false)->assertSee('表示順')->assertDontSee('おすすめ順');
    $detail = $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $jobs[0]->id, 'sort' => $expected, 'page' => 2]))->assertOk();
    $detail->assertSee('sort='.$expected, false);
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => $jobs->take(2)->pluck('id')->all(), 'sort' => $expected]))->assertOk()->assertSee('sort='.$expected, false);
})->with([['fit', 'fit'], ['newest', 'newest'], ['salary_desc', 'salary_desc'], ['bad', 'fit'], [['newest'], 'fit'], [null, 'fit']]);

test('sort uses public snapshot values and excludes nonpublic jobs', function () {
    [$query, $jobs] = sortingFixture(5);
    foreach (['draft', 'paused', 'closed'] as $i => $status) {
        $jobs[$i]->update(['status' => $status]);
    }
    $public = $jobs[3]->only(['title', 'occupation', 'region', 'salary_min', 'salary_max', 'source_url']);
    JobPublishedProfile::create(['job_posting_id' => $jobs[3]->id, 'published_at' => now(), 'profile_data' => ['schema_version' => 1, 'level_one' => $public, 'company' => ['name' => '公開会社']]]);
    $jobs[3]->update(['salary_min' => 9999, 'salary_max' => 99999, 'occupation' => '電気設計', 'region' => '東京都']);
    foreach (array_keys(JobListSort::OPTIONS) as $sort) {
        $result = app(JobDecisionUseCaseService::class)->run($query, 1, [], $sort);
        expect($result['total'])->toBe(2)->and(sortingIds($result))->toContain($jobs[3]->id, $jobs[4]->id);
        $item = collect($result['items'])->firstWhere('job.id', $jobs[3]->id);
        expect($item['job']->salary_min)->toBe($public['salary_min']);
    }
});

test('sorting fixture exports all modes for browser verification', function () {
    [$query] = sortingFixture();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $capture = getenv('JOBDD_SORT_CAPTURE_DIR');
    foreach (array_keys(JobListSort::OPTIONS) as $sort) {
        foreach ([1, 2, 3] as $page) {
            $response = $this->get(route('query.jobs', ['userQuery' => $query->public_id, 'sort' => $sort, 'page' => $page]))->assertOk();
            if ($capture) {
                if (! is_dir($capture)) {
                    mkdir($capture, 0777, true);
                }
                file_put_contents($capture.'/'.$sort.'-'.$page.'.html', $response->getContent());
            }
        }
    }
});

test('tool sort matches full evaluator and ignores optional detailed preferences', function () {
    [$query, $jobs] = sortingFixture(3);
    foreach ($jobs as $job) {
        $job->update(['region' => '兵庫県', 'salary_min' => 600]);
    }
    $jobs[2]->update(['description' => '【担当業務】AutoCADを使用して設計します。']);
    JobFact::create(['job_posting_id' => $jobs[2]->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
        'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule', 'verification_status' => 'verified',
        'observed_at' => '2026-09-01', 'evidence_text' => 'AutoCADを使用して設計します。']);
    $requirements = ['desired' => [['fact_key' => 'autocad', 'action' => 'use']]];
    $service = app(JobDecisionUseCaseService::class);
    $before = $service->run($query, 1, $requirements);
    expect(sortingIds($before)[0])->toBe($jobs[2]->id);
    $query->detailed_skills = ['seeker_preferences' => ['work_style' => '重要', 'design_phases' => ['concept_design']]];
    $query->priorities = ['salary'];
    expect(sortingIds($service->run($query, 1, $requirements)))->toBe(sortingIds($before));
    foreach ($before['items'] as $item) {
        expect($item['fit']['summary'])->toBe(app(JobFitService::class)->evaluate($query, $item['job'], $item['job']->jobFacts->all(), $requirements)['summary']);
    }
});

test('detailed condition editing retains sort without storing it', function () {
    [$query] = sortingFixture(0);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $context = ['userQuery' => $query->public_id, 'sort' => 'salary_desc', 'page' => 2, 'tools' => ['autocad']];
    $this->get(route('query.preferences.edit', $context))->assertOk()->assertSee('sort=salary_desc', false);
    $this->patch(route('query.preferences.update', $context), ['work_style' => '設計に関わりたい'])
        ->assertRedirect(route('query.jobs', ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['autocad'], 'sort' => 'salary_desc']));
    expect($query->fresh()->detailed_skills)->toBe(['seeker_preferences' => ['work_style' => '設計に関わりたい']]);
});
