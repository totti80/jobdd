<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\JobDiscoveryService;
use App\Services\JobFitRunnerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

function discoveryJob(array $attributes = []): JobPosting
{
    return JobPosting::create(array_replace([
        'company_id' => Company::firstOrCreate(['name' => 'A製作所'])->id,
        'title' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県',
        'source_url' => 'https://careers.sample-company.jp/jobs/1',
        'description' => '【使用ツール】AutoCAD',
    ], $attributes));
}

function discoveryFact(JobPosting $job, array $attributes = []): JobFact
{
    return JobFact::forceCreate(array_replace([
        'job_posting_id' => $job->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
        'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
        'verification_status' => 'verified', 'evidence_text' => 'AutoCAD', 'observed_at' => '2026-09-19 00:00:00',
    ], $attributes));
}

function discoveryQuery(array $attributes = []): UserQuery
{
    return new UserQuery(array_replace(['occupation' => '機械設計', 'region' => '兵庫県'], $attributes));
}

test('discovery source gate respects URL syntax and host boundaries', function ($url, $accepted) {
    $job = discoveryJob(['source_url' => $url]);
    $before = $job->fresh()->getAttributes();
    $jobs = (new JobDiscoveryService)->discover(discoveryQuery());
    expect($jobs->modelKeys())->toBe($accepted ? [$job->id] : [])
        ->and($job->fresh()->getAttributes())->toBe($before);
})->with([
    'http' => ['http://careers.sample-company.jp/job', true],
    'https' => ['https://careers.sample-company.jp/job', true],
    'uppercase scheme' => ['HTTPS://careers.sample-company.jp/job', true],
    'normal trailing dot' => ['https://careers.sample-company.jp./job', true],
    'fixture in path' => ['https://careers.sample-company.jp/example.com/job', true],
    'fixture in query' => ['https://careers.sample-company.jp/?host=example.com', true],
    'fixture in fragment' => ['https://careers.sample-company.jp/#example.com', true],
    'prefix boundary' => ['https://notexample.com/job', true],
    'suffix boundary' => ['https://example.com.sample-company.jp/job', true],
    'test word' => ['https://test-company.jp/job', true],
    'null' => [null, false], 'empty' => ['', false],
    'malformed' => ['not a url', false], 'relative' => ['/jobs/1', false],
    'scheme relative' => ['//careers.sample-company.jp/job', false],
    'ftp' => ['ftp://careers.sample-company.jp/job', false],
    'javascript' => ['javascript:alert(1)', false],
    'leading space' => [' https://careers.sample-company.jp/job', false],
    'trailing space' => ['https://careers.sample-company.jp/job ', false],
    'example com' => ['https://example.com/job', false],
    'example org' => ['https://example.org/job', false],
    'example net' => ['https://example.net/job', false],
    'test' => ['https://test/job', false],
    'invalid' => ['https://invalid/job', false],
    'localhost' => ['https://localhost/job', false],
    'example' => ['https://example/job', false],
    'sub com' => ['https://foo.example.com/job', false],
    'sub org' => ['https://foo.example.org/job', false],
    'sub net' => ['https://foo.example.net/job', false],
    'sub test' => ['https://foo.test/job', false],
    'sub invalid' => ['https://foo.invalid/job', false],
    'sub localhost' => ['https://foo.localhost/job', false],
    'sub example' => ['https://foo.example/job', false],
    'upper fixture' => ['https://EXAMPLE.COM/job', false],
    'dot fixture' => ['https://example.com./job', false],
    'upper sub dot' => ['https://FOO.EXAMPLE.COM./job', false],
    'fixture userinfo' => ['https://person@foo.example.org/job', false],
    'fixture port' => ['https://foo.example.net:443/job', false],
]);

test('discovery uses only canonical stored scope and saved availability', function ($attributes, $accepted) {
    $job = discoveryJob($attributes);
    expect((new JobDiscoveryService)->discover(discoveryQuery())->modelKeys())->toBe($accepted ? [$job->id] : []);
})->with([
    'Hyogo' => [['region' => '兵庫県'], true], 'Osaka' => [['region' => '大阪府'], true],
    'Kyoto' => [['region' => '京都府'], true], 'Shiga' => [['region' => '滋賀県'], true],
    'Nara' => [['region' => '奈良県'], true], 'Wakayama' => [['region' => '和歌山県'], true],
    'city' => [['region' => '兵庫県西宮市'], false],
    'multiple' => [['region' => '東京都 / 兵庫県'], false],
    'other multiple' => [['region' => '大阪府 / 徳島県'], false],
    'outside' => [['region' => '東京都'], false],
    'missing region' => [['region' => null], false],
    'empty region' => [['region' => ''], false],
    'other occupation' => [['occupation' => '電気設計'], false],
    'unknown occupation' => [['occupation' => null], false],
    'empty occupation' => [['occupation' => ''], false],
    'outside occupation' => [['occupation' => '施工管理'], false],
    'unavailable' => [['unavailable_at' => '2026-01-01 00:00:00'], false],
    'future unavailable' => [['unavailable_at' => '2030-01-01 00:00:00'], false],
    'stale' => [['last_seen_at' => '2000-01-01 00:00:00'], true],
    'missing freshness' => [['last_seen_at' => null, 'published_at' => null], true],
    'null provider' => [['provider_key' => null], true],
    'unknown provider' => [['provider_key' => 'unknown_provider'], true],
    'low salary' => [['salary_min' => 100, 'salary_max' => 200], true],
    'unknown salary' => [['salary_min' => null, 'salary_max' => null], true],
    'conflicting body' => [['title' => '電気設計', 'description' => '回路設計'], true],
]);

test('discovery accepts both occupations and every supported query region', function ($occupation, $region) {
    $job = discoveryJob(['occupation' => $occupation]);
    expect((new JobDiscoveryService)->discover(discoveryQuery(['occupation' => $occupation, 'region' => $region]))->modelKeys())->toBe([$job->id]);
})->with(['機械設計', '電気設計'])->with([null, '兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県']);

test('discovery groups only the requested region then preserves ascending IDs within both groups', function () {
    $jobs = collect(['和歌山県', '兵庫県', '滋賀県', '大阪府', '兵庫県', '京都府', '奈良県'])
        ->map(fn ($region) => discoveryJob(['region' => $region]));
    $service = new JobDiscoveryService;
    $expected = [$jobs[1]->id, $jobs[4]->id, $jobs[0]->id, $jobs[2]->id, $jobs[3]->id, $jobs[5]->id, $jobs[6]->id];
    expect($service->discover(discoveryQuery())->modelKeys())->toBe($expected)
        ->and($service->discover(discoveryQuery())->modelKeys())->toBe($expected)
        ->and($service->discover(discoveryQuery(['region' => null]))->modelKeys())->toBe($jobs->pluck('id')->all());
});

test('discovery paginates the gated population without losing rows', function ($limit) {
    discoveryJob(['source_url' => 'https://example.com/first']);
    $ids = [];
    for ($i = 0; $i < 53; $i++) {
        $ids[] = discoveryJob()->id;
        if ($i === 7) {
            discoveryJob(['source_url' => null]);
        }
    }
    $service = new JobDiscoveryService;
    expect($service->discover(discoveryQuery())->modelKeys())->toBe(array_slice($ids, 0, 20));
    $rebuilt = [];
    for ($offset = 0; $offset < 53; $offset += $limit) {
        $page = $service->discover(discoveryQuery(), $limit, $offset);
        expect($page->modelKeys())->toBe(array_slice($ids, $offset, $limit));
        array_push($rebuilt, ...$page->modelKeys());
    }
    expect($rebuilt)->toBe($ids)->and(array_unique($rebuilt))->toHaveCount(53)
        ->and($service->discover(discoveryQuery(), $limit, 53))->toBeInstanceOf(Collection::class)->toBeEmpty();
})->with([1, 20, 30, 50]);

test('discovery ignores all query fields outside occupation and region', function ($changes) {
    $first = discoveryJob(['salary_min' => null, 'salary_max' => null]);
    $second = discoveryJob(['region' => '大阪府', 'salary_min' => 100, 'salary_max' => 200]);
    $query = discoveryQuery($changes);
    $before = $query->getAttributes();
    expect((new JobDiscoveryService)->discover($query)->modelKeys())->toBe([$first->id, $second->id])
        ->and($query->getAttributes())->toBe($before);
})->with([
    'salary' => [['salary_min' => 9999, 'salary_max' => 10000]],
    'skills' => [['detailed_skills' => ['catia', 'nx']]],
    'priorities' => [['priorities' => ['salary', 'region']]],
    'text' => [['raw_text' => 'PRIVATE desired tools']],
    'experience' => [['experience_years' => 30]],
]);

test('provider freshness and Fact updates never change candidate IDs or order', function () {
    $first = discoveryJob();
    $second = discoveryJob();
    $service = new JobDiscoveryService;
    $expected = $service->discover(discoveryQuery())->modelKeys();
    // These mutations are fixture setup in the testing DB, outside discovery measurements.
    $first->update(['provider_key' => 'z_unknown', 'last_seen_at' => '2000-01-01', 'published_at' => null]);
    $second->update(['provider_key' => 'official_direct', 'last_seen_at' => '2030-01-01', 'published_at' => '2030-01-01']);
    discoveryFact($second);
    discoveryFact($second, ['verification_status' => 'unverified']);
    expect($service->discover(discoveryQuery())->modelKeys())->toBe($expected);
});

test('discovery preloads all Facts in ID order with full attributes and no extra relations', function () {
    $job = discoveryJob();
    discoveryJob(); // Same company must not be deduplicated or blacklisted.
    $a = discoveryFact($job, ['id' => 200, 'verification_status' => 'unverified']);
    $b = discoveryFact($job, ['id' => 100, 'fact_category' => 'domain', 'fact_key' => 'arbitrary_saved_fact', 'extraction_method' => 'manual']);
    $before = $job->fresh()->getAttributes();
    $factsBefore = JobFact::orderBy('id')->get()->map->getAttributes()->all();
    $service = new JobDiscoveryService;
    $jobs = $service->discover(discoveryQuery());
    // The public projection adds a read-only company label; legacy rows keep every stored attribute.
    expect($jobs)->toHaveCount(2)->and(collect($jobs[0]->getAttributes())->sortKeys()->all())->toBe(collect([...$before, 'published_company_name' => null])->sortKeys()->all())
        ->and($jobs[0]->getRelations())->toHaveKeys(['jobFacts'])
        ->and(array_keys($jobs[0]->getRelations()))->toBe(['jobFacts'])
        ->and($jobs[0]->jobFacts->modelKeys())->toBe([$b->id, $a->id])
        ->and($jobs[0]->jobFacts->map->getAttributes()->all())->toBe($factsBefore)
        ->and($jobs[1]->relationLoaded('jobFacts'))->toBeTrue()->and($jobs[1]->jobFacts)->toBeEmpty()
        ->and($service->discover(discoveryQuery())->toArray())->toBe($jobs->toArray());
    foreach ($jobs[0]->jobFacts as $fact) {
        expect($fact->getRelations())->toBe([]);
    }
    expect($job->fresh()->getAttributes())->toBe($before)
        ->and(JobFact::orderBy('id')->get()->map->getAttributes()->all())->toBe($factsBefore);
});

test('discovery and Runner use constant selects and no writes without dropping unknown or hard mismatch', function ($count) {
    for ($i = 0; $i < $count; $i++) {
        $job = discoveryJob(['region' => $i % 2 ? '大阪府' : '兵庫県']);
        if ($i % 3 !== 0) {
            discoveryFact($job);
        }
    }
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $query = discoveryQuery(['salary_min' => 600]);
        $before = $query->getAttributes();
        $service = new JobDiscoveryService;
        $jobs = $service->discover($query, $count);
        expect($sql)->toHaveCount(3)->and($jobs)->toHaveCount($count);
        $facts = [];
        foreach ($jobs as $job) {
            expect($job->relationLoaded('jobFacts'))->toBeTrue();
            $facts[$job->id] = $job->getRelation('jobFacts')->all();
            foreach ($facts[$job->id] as $fact) {
                expect($fact->job_posting_id)->toBe($job->id);
            }
        }
        $snapshots = $jobs->toArray();
        $requirements = ['desired' => [['fact_key' => 'autocad', 'action' => 'use']], 'hard_axes' => ['region']];
        $runner = app(JobFitRunnerService::class);
        $results = $runner->run($query, $jobs, $facts, $requirements);
        expect($results)->toHaveCount($count)->and(array_column($results, 'job_posting_id'))->toBe($jobs->modelKeys())
            ->and($runner->run($query, $jobs, $facts, $requirements))->toBe($results)->and($sql)->toHaveCount(3)
            ->and($query->getAttributes())->toBe($before)->and($jobs->toArray())->toBe($snapshots);
        foreach ($results as $i => $result) {
            expect($result['summary']['unknowns'])->toBeGreaterThan(0)
                ->and($result)->not->toHaveKeys(['score', 'rank', 'overall_status', 'discovery_score', 'recommendation_reason']);
            if ($jobs[$i]->region === '大阪府') {
                expect($result['summary']['hard_mismatch_keys'])->toBe(['region']);
            }
        }
        expect($results[0]['axes'][3]['reason_code'])->toBe('presence_not_found');
        expect($service->discover($query, $count)->toArray())->toBe($snapshots)->and($sql)->toHaveCount(6);
        expect($service->discover($query, 20, $count))->toBeEmpty()->and($sql)->toHaveCount(7)
            ->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    } finally {
        $record = false;
    }
})->with([1, 20, 50]);
