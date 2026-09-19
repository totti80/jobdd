<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobDiscoveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function decisionPageFixture(int $count = 23): UserQuery
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'private-session-value',
        'raw_text' => 'PRIVATE RAW', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600,
        'detailed_skills' => ['autocad']]);
    $company = Company::create(['name' => '比較会社 <script>alert(1)</script>']);
    for ($i = 0; $i < $count; $i++) {
        $job = JobPosting::create(['company_id' => $company->id, 'title' => '機械設計 '.$i.' <script>alert(2)</script>',
            'occupation' => '機械設計', 'region' => $i % 2 ? '大阪府' : '兵庫県',
            'salary_min' => $i % 3 ? 700 : null, 'salary_max' => null,
            'source_url' => 'https://careers.sample-company.jp/jobs/'.$i, 'provider_key' => 'sample',
            'description' => $i % 3 ? '【使用ツール】AutoCAD' : '【応募条件】AutoCADの経験 <script>alert(3)</script>']);
        if ($i % 4 !== 0) {
            JobFact::create(['job_posting_id' => $job->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
                'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
                'verification_status' => 'verified', 'observed_at' => '2026-09-19 00:00:00',
                'evidence_text' => $job->description]);
        }
    }

    return $query;
}

function decisionPageUrl(UserQuery $query, array $parameters = []): string
{
    return route('query.jobs', ['userQuery' => $query->public_id, ...$parameters]);
}

test('decision page renders the authorized pipeline with five reads and no writes', function () {
    $query = decisionPageFixture();
    $expected = (new JobDiscoveryService)->discover($query, 21)->take(20)->modelKeys();
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
            ->get(decisionPageUrl($query, ['tools' => ['autocad']]));
        $response->assertOk()->assertSee('求人を比較する')->assertSee('確認できた条件')
            ->assertSee('条件と異なる点')->assertSee('未確認')->assertSee('根拠を見る')
            ->assertSee('記載の文脈')->assertSee('求人元を見る')->assertSee('次へ')
            ->assertSee('担当業務での使用は確認できません。');
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(5)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    $data = $response->viewData('items');
    expect(array_map(fn ($item) => $item['job']->id, $data))->toBe($expected)->and($data)->toHaveCount(20);
    foreach ($data as $item) {
        expect($item['fit']['job_posting_id'])->toBe($item['job']->id)
            ->and($item['fit']['summary']['hard_mismatch_keys'])->toBe([])
            ->and($item['job']->relationLoaded('company'))->toBeFalse();
        foreach ($item['fit']['axes'] as $axis) {
            expect($axis['requirement']['hard'])->toBeFalse();
            foreach ($axis['evidence'] as $evidence) {
                if ($evidence['kind'] === 'job_fact') {
                    expect(in_array($evidence['job_fact_id'], $item['job']->jobFacts->modelKeys(), true))->toBeTrue();
                }
            }
        }
    }
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(', false)->assertDontSee('PRIVATE RAW')->assertDontSee('private-session-value')
        ->assertDontSee('TOP3')->assertDontSee('score')->assertDontSee('不適合')->assertDontSee('×')
        ->assertSee('rel="noopener noreferrer"', false)->assertSee('tools%5B0%5D=autocad', false);
});

test('decision pagination keeps tools and evaluates only twenty jobs', function () {
    $query = decisionPageFixture();
    $expected = (new JobDiscoveryService)->discover($query, 21, 20)->modelKeys();
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query, ['page' => 2, 'tools' => ['solidworks', 'autocad']]));
    $response->assertOk()->assertSee('前へ')->assertDontSee('次へ')->assertSee('tools%5B0%5D=solidworks', false);
    expect($response->viewData('items'))->toHaveCount(3)
        ->and(array_map(fn ($i) => $i['job']->id, $response->viewData('items')))->toBe($expected)
        ->and($response->viewData('pagination'))->toBe(['page' => 2, 'has_previous' => true, 'has_next' => false]);
});

test('decision page never infers tools from saved skills and shows empty pages safely', function () {
    $query = decisionPageFixture(1);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(decisionPageUrl($query));
    $response->assertOk();
    expect($response->viewData('selected_tools'))->toBe([])
        ->and(array_column($response->viewData('items')[0]['fit']['axes'], 'key'))->toBe(['occupation', 'region', 'salary']);
    $this->get(decisionPageUrl($query, ['page' => 2]))->assertOk()->assertSee('条件に該当する求人候補がありません');
});

test('decision page rejects malformed GET inputs', function ($input) {
    $query = decisionPageFixture(0);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query, $input))->assertStatus(422);
})->with([
    [['page' => 0]], [['page' => -1]], [['page' => 'abc']], [['page' => '1.5']], [['page' => ['1']]],
    [['page' => '999999999999999999999999']], [['tools' => 'autocad']], [['tools' => ['plc']]],
    [['tools' => ['autocad', 'autocad']]], [['tools' => ['x' => 'autocad']]], [['tools' => [['autocad']]]],
]);

test('decision route preserves session protection and public ID binding', function () {
    $query = decisionPageFixture(0);
    $this->get(decisionPageUrl($query))->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get(decisionPageUrl($query))->assertNotFound();
    $this->get('/query/nonexistent/jobs')->assertNotFound();
    $this->get('/query/'.$query->id.'/jobs')->assertNotFound();
    $query->update(['session_token' => '']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => ''])->get(decisionPageUrl($query))->assertNotFound();
});

test('decision use case preserves hard mismatches if explicitly supplied and batches companies', function () {
    $query = decisionPageFixture();
    $before = $query->getAttributes();
    $useCase = app(JobDecisionUseCaseService::class);
    $result = $useCase->run($query, 1, ['desired' => [['fact_key' => 'autocad', 'action' => 'use']], 'hard_axes' => ['region']]);
    expect($result['items'])->toHaveCount(20)->and($result['pagination']['has_next'])->toBeTrue()
        ->and(array_filter($result['items'], fn ($i) => $i['fit']['summary']['hard_mismatch_keys'] !== []))->not->toBeEmpty()
        ->and(array_filter($result['items'], fn ($i) => $i['job']->jobFacts->isEmpty()))->not->toBeEmpty()
        ->and($result['query'])->not->toHaveKeys(['raw_text', 'session_token'])->and($query->getAttributes())->toBe($before);
});

test('decision use case has no next page for exactly twenty jobs', function () {
    $query = decisionPageFixture(20);
    expect(app(JobDecisionUseCaseService::class)->run($query)['pagination']['has_next'])->toBeFalse();
});

test('decision page reads database sessions without updates or garbage collection', function () {
    $query = decisionPageFixture(1);
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    app('session')->forgetDrivers();
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_'.$query->public_id, $query->session_token);
    $session->save(); // Testing DB fixture only, before measurement.
    DB::table('sessions')->insert(['id' => str_repeat('a', 40), 'payload' => base64_encode(serialize([])), 'last_activity' => 1]);
    $before = DB::table('sessions')->orderBy('id')->get()->toJson();
    $sql = [];
    $record = true;
    DB::listen(function ($e) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $e->sql;
        }
    });
    try {
        $this->withCookie($session->getName(), $session->getId())->get(decisionPageUrl($query))->assertOk();
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([])
        ->and(DB::table('sessions')->orderBy('id')->get()->toJson())->toBe($before);
});
