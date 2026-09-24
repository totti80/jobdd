<?php

use App\Http\Middleware\JobDecisionSession;
use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\UserQuery;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;

function entryInput(array $changes = []): array
{
    return array_replace(['occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600,
        'tools' => ['solidworks', 'autocad']], $changes);
}

function entryRealCsrf(): void
{
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

function entryFreshSessionDriver(): void
{
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
}

test('new entry form is accessible and old input and result routes remain intact', function () {
    $this->get(route('jobs.start'))->assertOk()->assertSee('まずは4つの条件から求人を見てみる')
        ->assertSee('万円以上')->assertSee('電気設計')->assertSee('和歌山県')->assertSee('電気CAD')
        ->assertDontSee('name="raw_text"', false)->assertDontSee('name="salary_max"', false);
    $this->get('/query')->assertOk()->assertSee('新しい求人比較を試す');
    $this->get('/')->assertOk()->assertSee(route('jobs.start'));
    $old = $this->post(route('query.store'), ['raw_text' => '機械設計 兵庫県', 'occupation' => '機械設計', 'prefecture' => '兵庫県']);
    $old->assertRedirect();
    expect($old->headers->get('Location'))->toContain('/results/');
    $this->get($old->headers->get('Location'))->assertOk()->assertSee('応募経路を比較');
});

test('new query writes only one UserQuery and never persists tools or extra request fields', function () {
    $sql = [];
    DB::listen(function ($e) use (&$sql) {
        $sql[] = $e->sql;
    });
    $response = $this->post(route('jobs.store'), entryInput(['salary_max' => 900, 'industry' => 'injected',
        'detailed_skills' => ['catia'], 'priorities' => ['salary'], 'session_token' => 'injected']));
    $writes = array_values(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)));
    expect($writes)->toHaveCount(1)->and($writes[0])->toStartWith('insert into `user_queries`');
    $this->assertDatabaseCount('user_queries', 1);
    $this->assertDatabaseCount('score_results', 0);
    $this->assertDatabaseCount('interaction_logs', 0);
    $query = UserQuery::sole();
    expect($query->occupation)->toBe('機械設計')->and($query->region)->toBe('兵庫県')
        ->and($query->salary_min)->toBe(600)->and($query->salary_max)->toBeNull()
        ->and($query->industry)->toBeNull()->and($query->detailed_skills)->toBeNull()->and($query->priorities)->toBeNull()
        ->and($query->session_token)->toHaveLength(64)->and($query->public_id)->toHaveLength(36);
    $response->assertSessionHas('jobdd_query_token_'.$query->public_id, $query->session_token)
        ->assertRedirect(route('query.jobs', ['userQuery' => $query->public_id, 'tools' => ['solidworks', 'autocad']]))
        ->assertDontSee($query->session_token);
    expect($response->headers->get('Location'))->not->toContain($query->session_token);
    $this->get($response->headers->get('Location'))->assertOk()->assertDontSee($query->session_token)
        ->assertSee('SolidWorks')->assertSee('希望条件');
    $this->flushSession();
    $this->get($response->headers->get('Location'))->assertNotFound();
});

test('new entry accepts both occupations all regions and optional empty fields', function ($occupation, $region) {
    $this->post(route('jobs.store'), ['occupation' => $occupation, 'region' => $region, 'salary_min' => ''])->assertRedirect();
    $query = UserQuery::sole();
    expect($query->salary_min)->toBeNull()->and($query->occupation)->toBe($occupation)->and($query->region)->toBe($region);
})->with(['機械設計', '電気設計'])->with(['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県']);

test('custom tools persist only validated intent and never enter the URL or selected tools', function ($custom) {
    $response = $this->post(route('jobs.store'), entryInput(['custom_tools' => $custom,
        'detailed_skills' => ['custom_tools' => 'injected'], 'priorities' => ['injected']]));
    $query = UserQuery::sole();
    $expected = trim($custom ?? '');
    expect($query->detailed_skills)->toBe($expected === '' ? null : ['custom_tools' => $expected])
        ->and($query->priorities)->toBeNull();
    $response->assertRedirect(route('query.jobs', ['userQuery' => $query->public_id, 'tools' => ['solidworks', 'autocad']]));
    $list = $this->get($response->headers->get('Location'))->assertOk();
    if ($expected !== '') {
        $list->assertSee('その他の希望ツール')->assertSee($expected)->assertSee('判定未対応');
    } else {
        $list->assertDontSee('その他の希望ツール');
    }
    $list->assertDontSee('<script>alert(1)</script>', false);
})->with([null, '', '   ', 'iCAD SX、EPLAN、ANSYS', str_repeat('設', 500), '<script>alert(1)</script>']);

test('invalid custom tools return accessible errors without writes', function ($custom) {
    $this->post(route('jobs.store'), entryInput(['custom_tools' => $custom]))
        ->assertStatus(422)->assertSee('500文字以内の文字列')
        ->assertSee('href="#custom_tools"', false)->assertSee('id="custom-tools-error"', false)
        ->assertSee('aria-invalid="true"', false);
    $this->assertDatabaseCount('user_queries', 0);
})->with([str_repeat('設', 501), [['iCAD SX']], 123]);

test('validation redisplay escapes custom tools and preserves the entered value', function () {
    $custom = '</textarea><script>alert(1)</script>';
    $this->post(route('jobs.store'), entryInput(['region' => '', 'custom_tools' => $custom]))
        ->assertStatus(422)->assertSee($custom)->assertDontSee($custom, false)
        ->assertSee('value="solidworks" checked', false);
    $this->assertDatabaseCount('user_queries', 0);
});

test('new entry rejects invalid inputs in Japanese without creating a query', function ($changes, $message) {
    $this->post(route('jobs.store'), entryInput($changes))->assertStatus(422)->assertSee($message)
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->assertDatabaseCount('user_queries', 0);
    $this->assertDatabaseCount('score_results', 0);
    $this->assertDatabaseCount('interaction_logs', 0);
})->with([
    [['occupation' => null], '職種は'], [['occupation' => '施工管理'], '職種は'], [['occupation' => ['機械設計']], '職種は'],
    [['region' => null], '希望地域は'], [['region' => '東京都'], '希望地域は'], [['region' => ['兵庫県']], '希望地域は'],
    [['salary_min' => -1], '希望年収は'], [['salary_min' => 0], '希望年収は'], [['salary_min' => 10001], '希望年収は'],
    [['salary_min' => 600.5], '希望年収は'], [['salary_min' => ['600']], '希望年収は'],
    [['salary_min' => '<script>alert(1)</script>'], '希望年収は'],
    [['tools' => 'solidworks'], 'ツールは'], [['tools' => ['plc']], 'ツールは'],
    [['tools' => ['autocad', 'autocad']], 'ツールは'], [['tools' => ['x' => 'autocad']], 'ツールは'],
    [['tools' => [['autocad']]], 'ツールは'], [['tools' => null], 'ツールは'],
]);

test('fresh browser form bootstraps real CSRF without GET writes and POST persists only query and session', function () {
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    entryFreshSessionDriver();
    entryRealCsrf();
    DB::table('sessions')->insert(['id' => str_repeat('z', 40), 'payload' => base64_encode(serialize([])), 'last_activity' => 1]);
    $sql = [];
    DB::listen(function ($e) use (&$sql) {
        $sql[] = $e->sql;
    });
    $form = $this->get(route('jobs.start'))->assertOk();
    expect(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    $this->assertDatabaseCount('sessions', 1);
    $bootstrap = $form->getCookie(JobDecisionSession::FORM_COOKIE);
    expect($bootstrap->isHttpOnly())->toBeTrue()->and($bootstrap->getSameSite())->toBe('lax');
    preg_match('/name="_token" value="([^"]+)"/', $form->getContent(), $token);
    $cookies = [];
    foreach ($form->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    entryFreshSessionDriver(); // A second tab / reload must retain the first form's CSRF token.
    $revisit = $this->withUnencryptedCookies($cookies)->get(route('jobs.start'))->assertOk();
    $revisit->assertSee('name="_token" value="'.$token[1].'"', false);
    expect(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    entryFreshSessionDriver(); // A new HTTP request must not retain the previous in-memory token.
    $sql = [];
    $post = $this->withUnencryptedCookies($cookies)->post(route('jobs.store'), entryInput(['_token' => $token[1]]));
    $post->assertRedirect();
    $writes = array_values(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)));
    expect($writes)->toHaveCount(2)->and($writes[0])->toStartWith('insert into `user_queries`')
        ->and($writes[1])->toStartWith('insert into `sessions`');
    $this->assertDatabaseCount('sessions', 2); // The expired fixture is not garbage-collected.
    $query = UserQuery::sole();
    $before = DB::table('sessions')->orderBy('id')->get()->toJson();
    entryFreshSessionDriver();
    $sql = [];
    $this->get($post->headers->get('Location'))->assertOk()->assertDontSee($query->session_token);
    expect(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([])
        ->and(DB::table('sessions')->orderBy('id')->get()->toJson())->toBe($before);
});

test('entry bootstrap rejects missing tampered expired or foreign cookies and wrong CSRF', function ($mode) {
    config(['session.driver' => 'database']);
    entryFreshSessionDriver();
    entryRealCsrf();
    $form = $this->get(route('jobs.start'))->assertOk();
    $session = $form->getCookie(config('session.cookie'))->getValue();
    $bootstrap = json_decode($form->getCookie(JobDecisionSession::FORM_COOKIE)->getValue(), true);
    $token = $bootstrap['csrf'];
    if ($mode === 'expired') {
        $bootstrap['expires'] = time() - 1;
    }
    if ($mode === 'foreign') {
        $bootstrap['id'] = str_repeat('b', 40);
    }
    $cookies = [config('session.cookie') => $session];
    if ($mode !== 'missing') {
        $cookies[JobDecisionSession::FORM_COOKIE] = $mode === 'tampered' ? 'invalid' : json_encode($bootstrap);
    }
    entryFreshSessionDriver();
    $this->withCookies($cookies)->post(route('jobs.store'), entryInput(['_token' => $mode === 'wrong token' ? 'wrong' : $token]))->assertStatus(419);
    $this->assertDatabaseCount('user_queries', 0);
})->with(['missing', 'tampered', 'expired', 'foreign', 'wrong token']);

test('new entry preserves an existing session and cannot replace its CSRF token', function () {
    config(['session.driver' => 'database']);
    entryFreshSessionDriver();
    entryRealCsrf();
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_existing', 'existing-secret');
    $csrf = $session->token();
    $session->save();
    $id = $session->getId();
    entryFreshSessionDriver();
    $form = $this->withCookie(config('session.cookie'), $id)->get(route('jobs.start'))->assertOk();
    $form->assertDontSee('existing-secret');
    $fake = json_encode(['id' => $id, 'csrf' => str_repeat('a', 40), 'expires' => time() + 1200]);
    entryFreshSessionDriver();
    $this->withCookie(JobDecisionSession::FORM_COOKIE, $fake)->post(route('jobs.store'), entryInput(['_token' => str_repeat('a', 40)]))->assertStatus(419);
    entryFreshSessionDriver();
    $this->post(route('jobs.store'), entryInput(['_token' => $csrf]))->assertRedirect()
        ->assertSessionHas('jobdd_query_token_existing', 'existing-secret');
});

test('new entry connects list detail comparison and application routes with tools and navigation', function () {
    $company = Company::create(['name' => '主導線確認企業']);
    $jobs = collect(range(1, 3))->map(fn ($i) => JobPosting::create(['company_id' => $company->id,
        'title' => '設計担当'.$i, 'occupation' => '機械設計', 'region' => '兵庫県',
        'source_url' => 'https://careers.sample-company.jp/jobs/'.$i, 'description' => '機械設計業務']));
    ApplicationRoute::create(['job_posting_id' => $jobs[0]->id, 'route_type' => 'direct',
        'application_url' => 'https://careers.sample-company.jp/apply', 'availability_status' => 'available']);
    $post = $this->post(route('jobs.store'), entryInput());
    $list = $this->get($post->headers->get('Location'))->assertOk()->assertSee('合わないという意味ではありません。');
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$list->getContent());
    $xpath = new DOMXPath($doc);
    $detailUrl = $xpath->query('//article//a[contains(text(), "詳細を見る")]')->item(0)->getAttribute('href');
    $detail = $this->get($detailUrl)->assertOk()->assertSee('https://careers.sample-company.jp/apply')
        ->assertSee('一覧で比較する求人を選ぶ')->assertSee(route('jobs.start'));
    $compareUrl = $xpath->query('//form[@id="compare-selection"]')->item(0)->getAttribute('action');
    $compare = $this->get($compareUrl.'?'.http_build_query(['jobs' => $jobs->take(2)->pluck('id')->all(), 'tools' => ['solidworks', 'autocad'], 'page' => 1]))
        ->assertOk()->assertSee('どれが一番かをJobDDが決めるのではなく')->assertSee('詳細を見る');
    foreach ([$list, $detail, $compare] as $response) {
        $response->assertSee('SolidWorks')->assertSee('AutoCAD')->assertSee(route('jobs.start'))->assertDontSee(UserQuery::sole()->session_token);
    }
});

test('entry rejects a modified encrypted bootstrap cookie', function () {
    config(['session.driver' => 'database']);
    entryFreshSessionDriver();
    entryRealCsrf();
    $form = $this->get(route('jobs.start'))->assertOk();
    preg_match('/name="_token" value="([^"]+)"/', $form->getContent(), $token);
    $cookies = [];
    foreach ($form->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    $cookies[JobDecisionSession::FORM_COOKIE] = substr_replace($cookies[JobDecisionSession::FORM_COOKIE], '!!!!', 10, 4);
    entryFreshSessionDriver();
    $this->withUnencryptedCookies($cookies)->post(route('jobs.store'), entryInput(['_token' => $token[1]]))->assertStatus(419);
    $this->assertDatabaseCount('user_queries', 0);
});
