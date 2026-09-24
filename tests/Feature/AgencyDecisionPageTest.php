<?php

use App\Models\Agency;
use App\Models\AgencyFact;
use App\Models\Source;
use App\Models\UserQuery;
use App\Support\AgencyDecisionPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function agencyDecisionFixture(): array
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'PRIVATE-AGENCY-TOKEN',
        'raw_text' => 'PRIVATE-QUERY', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $agency = Agency::create(['name' => '相談先'.str_repeat('長い名称', 12), 'website_url' => 'https://agency.sample-company.jp', 'job_count' => 0]);
    $source = Source::create(['title' => '公式サービス情報', 'publisher' => '提供元', 'source_type' => 'official_site',
        'url' => 'https://agency.sample-company.jp/services', 'fetched_at' => '2026-09-19 12:00:00']);

    return [$query, $agency, $source];
}

function agencyDecisionFact(Agency $agency, Source $source, string $key, string $value, string $status = 'verified'): AgencyFact
{
    return AgencyFact::create(['agency_id' => $agency->id, 'source_id' => $source->id, 'fact_type' => $key,
        'fact_key' => $key, 'fact_value' => $value, 'verification_status' => $status, 'observed_at' => '2026-09-19 12:00:00']);
}

function agencyDecisionCard(Agency $agency, UserQuery $query): array
{
    return (new AgencyDecisionPresenter)->present($agency->fresh()->load('facts.source'), $query);
}

function agencyDecisionGet($test, UserQuery $query, array $input = [])
{
    return $test->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('query.agencies', ['userQuery' => $query->public_id, ...$input]));
}

test('agency public counts never turn unknown or explicit zero into mismatch', function ($value, $verification, $expected) {
    [$query, $agency, $source] = agencyDecisionFixture();
    if ($value !== null) {
        agencyDecisionFact($agency, $source, 'public_job_count', $value, $verification);
    }
    $card = agencyDecisionCard($agency, $query);
    expect($card['public_jobs']['count'])->toBe($expected);
    foreach ($card['layers'] as $layer) {
        expect(array_unique(array_column($layer['items'], 'status')))->toBe(['unknown']);
    }
    $response = agencyDecisionGet($this, $query)->assertOk()
        ->assertSee('この条件では公開求人を確認できていません')
        ->assertSee('公開求人が確認できないことは、紹介可能な求人がないことを意味しません。')
        ->assertDontSee('data-status="mismatch"', false);
    if ($expected !== null) {
        $response->assertSee('JobDDで確認できた公開求人：'.$expected.'件')->assertSee('集計対象の条件は未確認');
    } else {
        $response->assertDontSee('JobDDで確認できた公開求人：');
    }
})->with([[null, 'verified', null], ['0件', 'verified', '0'], ['0', 'pending', null], ['多数', 'verified', null], ['120件', 'verified', '120']]);

test('agency domain requires affirmative or explicitly negative evidence not absent list membership', function ($value, $verification, $expected) {
    [$query, $agency, $source] = agencyDecisionFixture();
    agencyDecisionFact($agency, $source, 'supported_occupation', $value, $verification);
    expect(agencyDecisionCard($agency, $query)['layers'][0]['items'][0]['status'])->toBe($expected);
})->with([
    ['機械設計', 'verified', 'match'], ['機械設計・電気設計', 'verified', 'match'],
    ['機械設計：非対応', 'verified', 'mismatch'], ['機械設計', 'pending', 'unknown'],
    ['電気設計', 'verified', 'unknown'], ['機械設計以外', 'verified', 'unknown'],
    ['機械設計', 'rejected', 'unknown'],
]);

test('agency opportunity advisory and outcome preserve three states independently', function ($key, $layer, $value, $expected) {
    [$query, $agency, $source] = agencyDecisionFixture();
    agencyDecisionFact($agency, $source, $key, $value);
    $card = agencyDecisionCard($agency, $query);
    expect($card['layers'][$layer]['items'][0]['status'])->toBe($expected)
        ->and($card['layers'][1]['items'][1]['status'])->toBe('unknown');
})->with([
    ['non_public_jobs', 1, 'あり', 'match'], ['non_public_jobs', 1, 'なし', 'mismatch'], ['non_public_jobs', 1, '多数', 'unknown'],
    ['technical_advisor', 2, '対応', 'match'], ['technical_advisor', 2, '非対応', 'mismatch'], ['technical_advisor', 2, '手厚い', 'unknown'],
    ['manufacturing_placement', 3, '実績あり', 'match'], ['manufacturing_placement', 3, '実績なし', 'mismatch'], ['manufacturing_placement', 3, '豊富', 'unknown'],
]);

test('conflicting agency evidence stays unknown and both sources remain accessible', function () {
    [$query, $agency, $source] = agencyDecisionFixture();
    agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計');
    agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計：対象外');
    agencyDecisionFact($agency, $source, 'public_job_count', '0');
    agencyDecisionFact($agency, $source, 'public_job_count', '4');
    $card = agencyDecisionCard($agency, $query);
    expect($card['layers'][0]['items'][0]['status'])->toBe('unknown')
        ->and($card['layers'][0]['items'][0]['facts'])->toHaveCount(2)
        ->and($card['public_jobs']['count'])->toBeNull();
    agencyDecisionGet($this, $query)->assertSee('保存された根拠に異なる記載');
});

test('agency page renders four layers evidence safe links escaped content and preserved navigation', function () {
    [$query, $agency, $source] = agencyDecisionFixture();
    agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計');
    agencyDecisionFact($agency, $source, 'non_public_jobs', 'あり');
    agencyDecisionFact($agency, $source, 'interview_support', '<script>alert(1)</script>', 'pending');
    $response = agencyDecisionGet($this, $query, ['page' => 2, 'tools' => ['autocad']])->assertOk()
        ->assertSee('専門領域との適合')->assertSee('求人・機会へのアクセス')->assertSee('相談・支援との適合')->assertSee('支援実績の根拠')
        ->assertSee('非公開求人の取扱い')->assertSee('あなたの条件に合う紹介可能求人')->assertSee('公式サイトを見る')
        ->assertSee('Source')->assertSee('公式サービス情報')->assertSee('取得日時')->assertSee('観測日時')
        ->assertSee('根拠を確認できていません')->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('page=2', false)->assertSee('tools%5B0%5D=autocad', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert', false)
        ->assertDontSee('PRIVATE-AGENCY-TOKEN')->assertDontSee('PRIVATE-QUERY');
    foreach (['score', 'TOP3', 'おすすめ', 'ランキング', 'winner', '4/4'] as $word) {
        $response->assertDontSee($word);
    }
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-layer]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-fact-key="non_public_jobs" and @data-status="match"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-fact-key="personal_opportunities" and @data-status="unknown"]')->length)->toBe(1)
        ->and($xpath->query('//main//details/summary')->length)->toBe(10);
    if (getenv('JOBDD_UI_CAPTURE_DIR')) {
        file_put_contents(getenv('JOBDD_UI_CAPTURE_DIR').'/agencies.html', $response->getContent());
    }
});

test('agency invalid and fixture sources do not confirm facts or become links', function ($url) {
    [$query, $agency, $source] = agencyDecisionFixture();
    $source->update(['url' => $url]);
    agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計');
    agencyDecisionFact($agency, $source, 'public_job_count', '0');
    $response = agencyDecisionGet($this, $query)->assertOk()->assertDontSee('data-status="match"', false)
        ->assertDontSee('JobDDで確認できた公開求人：')->assertDontSee('href="'.$url.'"', false);
    $agency->update(['website_url' => $url]);
    agencyDecisionGet($this, $query)->assertOk()->assertSee('現在、確認できる相談先候補がありません。');
})->with(['javascript:alert(1)', 'data:text/html,hello', 'ftp://agency.jp', 'https://example.com/a', 'https://foo.EXAMPLE.ORG./a']);

test('agency missing source does not confirm a verified fact', function () {
    [$query, $agency, $source] = agencyDecisionFixture();
    $fact = agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計');
    $fact->setRelation('source', null);
    $agency->setRelation('facts', new Collection([$fact]));
    $card = (new AgencyDecisionPresenter)->present($agency, $query);
    expect($card['layers'][0]['items'][0]['status'])->toBe('unknown');
    $this->view('query.partials.agency-evidence', ['facts' => collect([$fact])])->assertSee('Sourceは未確認です。');
});

test('agency ordering and query count are fixed independent of jobs facts or candidate count', function ($count) {
    [$query, $first, $source] = agencyDecisionFixture();
    agencyDecisionFact($first, $source, 'supported_region', '兵庫県');
    $ids = [$first->id];
    for ($i = 1; $i < $count; $i++) {
        $agency = Agency::create(['name' => '追加'.$i, 'website_url' => 'https://agency.sample-company.jp/'.$i, 'job_count' => 100000 + $i]);
        agencyDecisionFact($agency, $source, 'public_job_count', (string) $i);
        $ids[] = $agency->id;
    }
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $response = agencyDecisionGet($this, $query)->assertOk();
    } finally {
        $record = false;
    }
    expect($response->viewData('items')->pluck('id')->all())->toBe($ids)
        ->and($sql)->toHaveCount(4)
        ->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
})->with([1, 9, 25]);

test('agency route requires its own nonempty session token and validates navigation inputs', function () {
    [$query] = agencyDecisionFixture();
    $url = route('query.agencies', ['userQuery' => $query->public_id]);
    $this->get($url)->assertNotFound();
    $this->get($url.'?token='.$query->session_token)->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get($url)->assertNotFound();
    agencyDecisionGet($this, $query, ['page' => 0])->assertStatus(422);
    agencyDecisionGet($this, $query, ['tools' => ['invalid']])->assertStatus(422);
    $query->update(['session_token' => '']);
    agencyDecisionGet($this, $query)->assertNotFound();
});

test('agency GET with database session performs only reads including expired-session lottery', function () {
    [$query, $agency, $source] = agencyDecisionFixture();
    agencyDecisionFact($agency, $source, 'supported_occupation', '機械設計');
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    app('session')->forgetDrivers();
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_'.$query->public_id, $query->session_token);
    $session->save();
    $tables = ['agencies', 'agency_facts', 'sources', 'user_queries', 'sessions', 'score_results', 'interaction_logs'];
    $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    $before = $snapshot();
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $this->withCookie($session->getName(), $session->getId())
            ->get(route('query.agencies', ['userQuery' => $query->public_id]))->assertOk();
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(5)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([])
        ->and($snapshot())->toBe($before);
});
