<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\ContextRoleClassifier;
use App\Services\JobDetailUseCaseService;
use App\Services\JobDiscoveryService;
use App\Services\JobSelectionUseCaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function detailCompareFixture(int $count = 3): array
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'PRIVATE-TOKEN',
        'raw_text' => 'PRIVATE-QUERY', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600]);
    $company = Company::create(['name' => '比較会社 <script>company()</script>']);
    $jobs = [];
    for ($i = 0; $i < $count; $i++) {
        $job = JobPosting::create(['company_id' => $company->id, 'title' => '機械設計 '.$i.' <script>title()</script>',
            'occupation' => '機械設計', 'region' => $i % 2 ? '大阪府' : '兵庫県',
            'description' => '【応募条件】AutoCADの経験 <script>evidence()</script>',
            'source_url' => 'https://careers.sample-company.jp/jobs/'.$i, 'provider_key' => 'sample',
            'salary_min' => $i ? 400 : null, 'salary_max' => $i ? 500 : null]);
        if ($i === 0) {
            JobFact::create(['job_posting_id' => $job->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
                'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
                'verification_status' => 'verified', 'observed_at' => '2026-09-19 00:00:00', 'evidence_text' => $job->description]);
        }
        $jobs[] = $job;
    }

    return [$query, $jobs];
}

function detailCompareUrl(UserQuery $query, ?int $id = null, array $extra = []): string
{
    return route($id === null ? 'query.jobs.compare' : 'query.jobs.show', [
        'userQuery' => $query->public_id, ...($id === null ? [] : ['job' => $id]), ...$extra,
    ]);
}

test('saved custom tools survive pagination tool changes detail comparison and map without adding Fit axes', function () {
    [$query, $jobs] = detailCompareFixture(25);
    $custom = 'AutoCAD、iCAD SX <script>alert(1)</script>';
    $before = app(JobSelectionUseCaseService::class)->run($query, [$jobs[0]->id]);
    $query->update(['detailed_skills' => ['custom_tools' => $custom]]);
    $after = app(JobSelectionUseCaseService::class)->run($query->fresh(), [$jobs[0]->id]);
    expect($after['items'][0]['fit']['axes'])->toBe($before['items'][0]['fit']['axes'])
        ->and($after['items'][0]['fit']['summary'])->toBe($before['items'][0]['fit']['summary']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $urls = [
        route('query.jobs', ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['solidworks']]),
        route('query.jobs', ['userQuery' => $query->public_id, 'tools' => ['nx']]),
        route('query.jobs', ['userQuery' => $query->public_id, 'view' => 'map']),
        detailCompareUrl($query, $jobs[0]->id, ['page' => 2]),
        detailCompareUrl($query, null, ['jobs' => [$jobs[0]->id, $jobs[1]->id], 'page' => 2]),
    ];
    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('その他の希望ツール')->assertSee($custom)
            ->assertSee('判定未対応')->assertDontSee('<script>alert(1)</script>', false);
    }
    expect($query->fresh()->detailed_skills)->toBe(['custom_tools' => $custom]);
});

test('job detail exposes escaped Facts context and stored application routes with constant reads', function () {
    [$query, $jobs] = detailCompareFixture();
    foreach (['platform', 'agent', 'direct'] as $type) {
        ApplicationRoute::create(['job_posting_id' => $jobs[0]->id, 'route_type' => $type,
            'application_url' => 'https://apply.sample-company.jp/'.$type, 'availability_status' => 'available',
            'provider_key' => 'provider-'.$type, 'notes' => '<script>route()</script>公開経路の記載']);
    }
    $sql = [];
    $record = true;
    DB::listen(function ($e) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $e->sql;
        }
    });
    try {
        $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
            ->get(detailCompareUrl($query, $jobs[0]->id, ['tools' => ['autocad'], 'page' => 2]));
        $response->assertOk()->assertSee('求人詳細と根拠')->assertSee('確認できた')->assertSee('未確認')
            ->assertSee('求人本文で確認できた技術・工程')->assertSee('応募条件に記載')->assertSee('根拠を見る')
            ->assertSee('この求人で確認できた応募方法')->assertSeeInOrder(['Direct（', 'Agent（', 'Platform（'])
            ->assertSeeInOrder(['この求人で確認できた応募方法', '人材紹介会社へ相談する選択肢', '相談先候補を見る'])
            ->assertSee('/agencies?page=2', false)
            ->assertSee('https://apply.sample-company.jp/direct', false)->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('page=2', false)->assertSee('tools%5B0%5D=autocad', false)
            ->assertSee('&lt;script&gt;evidence()&lt;/script&gt;', false)->assertSee('&lt;script&gt;route()&lt;/script&gt;', false)
            ->assertDontSee('<script>', false)->assertDontSee('PRIVATE-TOKEN')->assertDontSee('PRIVATE-QUERY');
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
});

test('detail keeps missing routes and invalid or unavailable application links unconfirmed', function ($url, $status, $unavailable) {
    [$query, $jobs] = detailCompareFixture(1);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(detailCompareUrl($query, $jobs[0]->id))->assertOk()->assertSee('保存済み情報では応募方法を確認できていません')->assertDontSee('応募できない');
    ApplicationRoute::create(['job_posting_id' => $jobs[0]->id, 'route_type' => 'agent',
        'application_url' => $url, 'availability_status' => $status, 'unavailable_at' => $unavailable]);
    $response = $this->get(detailCompareUrl($query, $jobs[0]->id));
    $response->assertOk()->assertSee('現在利用できる応募先リンクを確認できていません')->assertDontSee('提供元の応募情報を見る');
    if ($url !== null) {
        $response->assertDontSee('href="'.$url.'"', false);
    }
})->with([
    ['javascript:alert(1)', 'available', null], ['data:text/html,hello', 'available', null],
    ['ftp://host.jp/job', 'available', null], [null, 'available', null],
    ['https://apply.sample-company.jp/job', 'unknown', null],
    ['https://apply.sample-company.jp/job', 'available', '2026-01-01 00:00:00'],
]);

test('selected candidate access agrees with Discovery scope and Source gate', function ($attributes) {
    [$query, $jobs] = detailCompareFixture(2);
    $jobs[0]->update($attributes);
    $eligible = (new JobDiscoveryService)->discover($query, 50)->modelKeys();
    expect($eligible)->not->toContain($jobs[0]->id);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(detailCompareUrl($query, $jobs[0]->id))->assertNotFound();
    $this->get(detailCompareUrl($query, null, ['jobs' => [$jobs[1]->id, $jobs[0]->id]]))->assertNotFound();
})->with([
    [['occupation' => '電気設計']], [['occupation' => null]], [['region' => '東京都']],
    [['region' => '兵庫県西宮市']], [['region' => '東京都 / 兵庫県']], [['unavailable_at' => '2026-01-01']],
    [['source_url' => null]], [['source_url' => 'javascript:alert(1)']],
    [['source_url' => 'https://EXAMPLE.COM./job']], [['source_url' => 'https://foo.example.org/job']],
    [['source_url' => 'https://foo.example.net/job']], [['source_url' => 'https://foo.test/job']],
    [['source_url' => 'https://foo.invalid/job']], [['source_url' => 'https://localhost/job']],
    [['source_url' => 'https://foo.example/job']],
]);

test('detail allows legitimate candidates beyond the first page without Fact or salary filters', function () {
    [$query, $jobs] = detailCompareFixture(25);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(detailCompareUrl($query, $jobs[23]->id, ['tools' => ['autocad']]))
        ->assertOk()->assertSee('条件と異なる')->assertSee('未確認')->assertSee('保存済み情報では技術・工程の記載を確認できていません。');
    $jobs[23]->update(['source_url' => 'https://notexample.com/path/example.com', 'provider_key' => null]);
    $this->get(detailCompareUrl($query, $jobs[23]->id))->assertOk();
});

test('compare renders two or three jobs in input order with six reads and no writes', function ($count) {
    [$query, $jobs] = detailCompareFixture();
    $ids = array_slice(array_reverse(array_map(fn ($j) => $j->id, $jobs)), 3 - $count);
    $sql = [];
    $record = true;
    DB::listen(function ($e) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $e->sql;
        }
    });
    try {
        $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
            ->get(detailCompareUrl($query, null, ['jobs' => $ids, 'tools' => ['autocad'], 'page' => 2]));
        $response->assertOk()->assertSee('選んだ求人を比較する')->assertSee('根拠を見る')
            ->assertSee('条件と異なる')->assertSee('未確認')->assertSee('詳細を見る')
            ->assertSee('tools%5B0%5D=autocad', false)->assertSee('page=2', false)
            ->assertDontSee('TOP3')->assertDontSee('おすすめ')->assertDontSee('最も一致')->assertDontSee('score')
            ->assertDontSee('PRIVATE-TOKEN')->assertDontSee('<script>', false);
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    $items = $response->viewData('items');
    expect(array_map(fn ($item) => $item['job']->id, $items))->toBe($ids);
    foreach ($items as $item) {
        expect($item['fit']['job_posting_id'])->toBe($item['job']->id);
        foreach ($item['fit']['axes'] as $axis) {
            foreach ($axis['evidence'] as $evidence) {
                if ($evidence['kind'] === 'job_fact') {
                    expect(in_array($evidence['job_fact_id'], $item['job']->jobFacts->modelKeys(), true))->toBeTrue();
                }
            }
        }
    }
})->with([2, 3]);

test('compare rejects missing malformed duplicate or excessive selection', function ($selection) {
    [$query] = detailCompareFixture(0);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(detailCompareUrl($query, null, ['jobs' => $selection]))->assertStatus(422)->assertSee('比較する求人を重複なく2〜3件選んでください。');
})->with([[[]], [[1]], [[1, 2, 3, 4]], [[1, 1]], [['x', 2]], [[0, 2]], [['99999999999999999999999', 2]], ['1,2'], [['a' => 1, 'b' => 2]]]);

test('detail compare and missing candidates preserve session authorization', function () {
    [$query, $jobs] = detailCompareFixture(2);
    $detail = detailCompareUrl($query, $jobs[0]->id);
    $compare = detailCompareUrl($query, null, ['jobs' => [$jobs[0]->id, $jobs[1]->id]]);
    $this->get($detail)->assertNotFound();
    $this->get($compare)->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get($detail)->assertNotFound();
    $this->get($compare)->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(detailCompareUrl($query, 999999))->assertNotFound();
    $this->get(detailCompareUrl($query, null, ['jobs' => [$jobs[0]->id, 999999]]))->assertNotFound();
    $this->get(detailCompareUrl($query, $jobs[0]->id, ['tools' => ['invalid']]))->assertStatus(422);
    $this->get(detailCompareUrl($query, null, ['jobs' => [$jobs[0]->id, $jobs[1]->id], 'page' => 0]))->assertStatus(422);
});

test('list links compare form and detail links preserve tools and page', function () {
    [$query] = detailCompareFixture(25);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('query.jobs', ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['solidworks']]))
        ->assertOk()->assertSee('比較に追加')->assertSee('選んだ求人を比較する')->assertSee('詳細を見る')
        ->assertSee('/jobs/compare', false)->assertSee('name="jobs[]"', false)->assertSee('form="compare-selection"', false)
        ->assertSee('name="page" value="2"', false)->assertSee('name="tools[]" value="solidworks"', false)
        ->assertSee('tools%5B0%5D=solidworks', false);
});

test('detail reuses Fit context without classifying the same saved Fact twice', function () {
    [$query, $jobs] = detailCompareFixture(1);
    $classifier = $this->createMock(ContextRoleClassifier::class);
    $classifier->expects($this->never())->method('classify');
    $service = new JobDetailUseCaseService(app(JobSelectionUseCaseService::class), $classifier);
    $data = $service->run($query, $jobs[0]->id, ['desired' => [['fact_key' => 'autocad', 'action' => 'use']]]);
    expect($data['presence_facts'][0]['context'])->toBe($data['items'][0]['fit']['axes'][3]['evidence'][0]['context']);
});

test('new detail and compare routes keep database sessions read-only', function ($detail) {
    [$query, $jobs] = detailCompareFixture(2);
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    app('session')->forgetDrivers();
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_'.$query->public_id, $query->session_token);
    $session->save();
    $before = DB::table('sessions')->orderBy('id')->get()->toJson();
    $sql = [];
    $record = true;
    DB::listen(function ($e) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $e->sql;
        }
    });
    try {
        $url = $detail ? detailCompareUrl($query, $jobs[0]->id) : detailCompareUrl($query, null, ['jobs' => [$jobs[0]->id, $jobs[1]->id]]);
        $this->withCookie($session->getName(), $session->getId())->get($url)->assertOk();
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(7)
        ->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([])
        ->and(DB::table('sessions')->orderBy('id')->get()->toJson())->toBe($before);
})->with([true, false]);
