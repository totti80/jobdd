<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Models\UserQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function navigationQuery(array $attributes = []): UserQuery
{
    return UserQuery::create([...['public_id' => (string) Str::uuid(), 'session_token' => Str::random(64), 'raw_text' => 'UI test', 'occupation' => '機械設計', 'region' => '兵庫県'], ...$attributes]);
}

function navigationJob(array $attributes = []): JobPosting
{
    return JobPosting::create([...['company_id' => Company::create(['name' => '公開企業'])->id, 'title' => '公開の機械設計', 'occupation' => '機械設計', 'region' => '兵庫県', 'status' => 'published', 'source_url' => 'https://careers.real-company.jp/job'], ...$attributes]);
}

test('public navigation renders six matching header and footer links', function (string $route) {
    $response = $this->get(route($route))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    $expected = ['トップ', 'かんたん入力', '詳細条件', 'お役立ち情報', '企業向け', 'お問い合わせ'];
    $destinations = [];
    foreach (['header', 'footer'] as $area) {
        $links = $xpath->query('//'.$area.'//nav/a');
        expect($links->length)->toBe(6);
        foreach ($links as $i => $link) {
            expect(trim($link->textContent))->toBe($expected[$i]);
            $destinations[$area][] = $link->getAttribute('href');
        }
    }
    expect($destinations['header'])->toBe($destinations['footer']);
    $response->assertSee('メインナビゲーション')->assertSee('フッターナビゲーション')->assertSee('data-site-menu', false)
        ->assertSee('根拠とともに、仕事を選ぶ。')->assertDontSee('あなた専用 転職コンシェルジュ');
    expect($xpath->query('//header//nav/a[@aria-current="page"]')->length)->toBe(1);
    expect($xpath->query('//header//span[@class="site-tagline"]')->length)->toBe(1);
})->with(['home', 'jobs.start', 'public.preferences', 'public.resources', 'public.contact']);

test('coming soon pages neither expose operations email nor provide inquiry forms', function () {
    foreach (['public.resources', 'public.contact'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('現在準備中')->assertDontSee('postmaster@')->assertDontSee('<form', false);
    }
});

test('resolvers without query show preferences guidance or the comparison input entry', function () {
    $this->get(route('public.preferences'))->assertOk()->assertViewIs('public.preferences');
    $this->get(route('public.compare'))->assertRedirect(route('jobs.start', ['guide' => 'compare']));
    $this->get(route('jobs.start', ['guide' => 'compare']))->assertSee('比較したい求人を選んでください');
});

test('query resolver uses valid session ownership and preserves safe page and tools', function () {
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('public.preferences', ['query' => $query->public_id, 'page' => 2, 'tools' => ['autocad']]))
        ->assertRedirect(route('query.preferences.edit', ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['autocad']]));
    $this->get(route('public.preferences'))->assertRedirect(route('query.preferences.edit', ['userQuery' => $query->public_id, 'page' => 1]));
});

test('resolver rejects foreign and malformed query references without leaking tokens', function () {
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get(route('public.preferences'))->assertOk()->assertViewIs('public.preferences')->assertDontSee($query->session_token);
    foreach ([$query->public_id, ['invalid']] as $reference) {
        $this->get(route('public.preferences', ['query' => $reference]))->assertOk()
            ->assertViewIs('public.preferences')->assertDontSee('<form', false)->assertDontSee($query->session_token);
        $this->get(route('public.compare', ['query' => $reference]))->assertRedirect(route('jobs.start', ['guide' => 'compare']))->assertDontSee($query->session_token);
    }
});

test('resolver skips obsolete queries and uses the newest authorized compatible one', function () {
    $query = navigationQuery();
    $invalid = navigationQuery(['occupation' => '営業']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token, 'jobdd_query_token_'.$invalid->public_id => $invalid->session_token])
        ->get(route('public.preferences'))->assertRedirect(route('query.preferences.edit', ['userQuery' => $query->public_id, 'page' => 1]));
});

test('compare entry reuses per-query browser storage and validates selected jobs before redirect', function () {
    $query = navigationQuery();
    $one = navigationJob();
    $two = navigationJob();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(route('public.compare'))->assertOk()->assertSee('data-query-id="'.$query->public_id.'"', false)->assertSee('求人一覧で選ぶ');
    $this->get(route('public.compare', ['jobs' => [$one->id, $two->id], 'tools' => ['autocad']]))
        ->assertRedirect(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$one->id, $two->id], 'page' => 1, 'tools' => ['autocad']]));
    $two->update(['status' => 'draft']);
    $this->get(route('public.compare', ['jobs' => [$one->id, $two->id]]))->assertOk()->assertSee('選び直してください')->assertDontSee('data-invalid-selection="false"', false);
});

test('malformed compare selections and context safely display the comparison entry', function (array $params) {
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('public.compare', $params))->assertOk()->assertSee('求人一覧で選ぶ');
})->with([
    [['jobs' => 'bad']], [['jobs' => ['1', '1']]], [['jobs' => ['1', '2', '3', '4']]],
    [['jobs' => [['bad'], '2'], 'page' => ['bad'], 'tools' => [['bad']]]],
]);

test('company entry routes guest member platform owner and unaffiliated user safely', function () {
    $this->get(route('public.company'))->assertRedirect(route('company.register'));
    $member = User::factory()->create();
    $member->companies()->attach(Company::create(['name' => '企業'])->id, ['role' => 'company_editor']);
    $this->actingAs($member)->get(route('public.company'))->assertRedirect(route('company.dashboard'));
    $this->actingAs(User::factory()->create(['system_role' => 'platform_owner']))->get(route('public.company'))->assertRedirect(route('admin.job-reviews.index'));
    $this->actingAs(User::factory()->create())->get(route('public.company'))->assertOk()->assertSee('企業に所属していません');
});

test('new job entry uses public title and safe input fallback then authorized existing detail', function () {
    $job = navigationJob();
    $this->get(route('public.job', $job->id))->assertRedirect(route('jobs.start', ['job' => $job->id]));
    $this->get(route('jobs.start', ['job' => $job->id]))->assertOk()->assertSee('「公開の機械設計」が気になった方へ');
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('public.job', $job->id))->assertRedirect(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id, 'page' => 1]));
    $job->update(['occupation' => '電気設計']);
    $this->get(route('public.job', $job->id))->assertRedirect(route('jobs.start', ['job' => $job->id]));
    $job->update(['status' => 'draft']);
    $this->get(route('public.job', $job->id))->assertRedirect(route('jobs.start', ['guide' => 'unavailable']));
    $this->get(route('jobs.start', ['job' => $job->id]))->assertDontSee('「公開の機械設計」が気になった方へ');
});

test('public entries use the existing read-only session path without database writes', function () {
    config(['session.driver' => 'database']);
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    DB::enableQueryLog();
    foreach (['home', 'public.resources', 'public.contact', 'public.preferences', 'public.compare', 'public.company', 'jobs.start'] as $route) {
        $this->get(route($route))->assertStatus(in_array($route, ['public.preferences', 'public.company']) ? 302 : 200);
    }
    $writes = collect(DB::getQueryLog())->filter(fn ($entry) => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $entry['query']));
    expect($writes)->toBeEmpty();
    DB::disableQueryLog();
});

test('header and footer preferences without a query open the detailed conditions guide', function () {
    $response = $this->get(route('jobs.start'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    $link = $xpath->query('//header//nav/a')->item(2);
    expect($link->getAttribute('href'))->toBe(route('public.preferences'));
    expect($xpath->query('//footer//nav/a')->item(2)->getAttribute('href'))->toBe($link->getAttribute('href'));
    $response = $this->get($link->getAttribute('href'))->assertOk()
        ->assertViewIs('public.preferences')->assertSee('<title>詳細条件入力 | JobDD</title>', false)
        ->assertSee('詳細条件を入力するには、まず4つの基本条件')->assertDontSee('<form', false);
    $response->assertHeader('Cache-Control', 'no-store, private');
    $this->assertDatabaseCount('user_queries', 0);
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect(trim($xpath->query('//header//nav/a[@aria-current="page"]')->item(0)->textContent))->toBe('詳細条件');
    expect(trim($xpath->query('//main//h1')->item(0)->textContent))->toBe('詳細条件入力');
    $cta = $xpath->query('//main//a')->item(0);
    expect(trim($cta->textContent))->toBe('かんたん入力をする');
    expect($cta->getAttribute('href'))->toBe(route('jobs.start'));
    $this->get($cta->getAttribute('href'))->assertOk();
});

test('header directly edits the current query and retains list context through save cancel and clear', function () {
    $query = navigationQuery();
    $newer = navigationQuery();
    $this->withSession([
        'jobdd_query_token_'.$query->public_id => $query->session_token,
        'jobdd_query_token_'.$newer->public_id => $newer->session_token,
    ]);
    $context = ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['autocad'], 'sort' => 'salary_desc'];
    $list = route('query.jobs', $context);
    $response = $this->get($list)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    $edit = $xpath->query('//header//nav/a')->item(2)->getAttribute('href');
    expect($edit)->toBe(route('query.preferences.edit', $context));
    expect($xpath->query('//footer//nav/a')->item(2)->getAttribute('href'))->toBe($edit);
    $response = $this->get($edit)->assertOk()->assertSee('<title>詳細条件入力 | JobDD</title>', false)->assertSee('詳細条件入力');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//header//nav/a[@aria-current="page"]')->length)->toBe(1);
    expect(trim($xpath->query('//header//nav/a[@aria-current="page"]')->item(0)->textContent))->toBe('詳細条件');
    expect($xpath->query('//a[contains(text(), "保存せず求人へ戻る")]')->item(0)->getAttribute('href'))->toBe($list);
    $save = $xpath->query('//form')->item(0)->getAttribute('action');
    $this->patch($save, ['work_style' => '相談しながら進めたい'])->assertRedirect($list);
    expect($query->fresh()->detailed_skills['seeker_preferences']['work_style'])->toBe('相談しながら進めたい');
    $this->get($list)->assertOk();
    expect($query->fresh()->detailed_skills['seeker_preferences']['work_style'])->toBe('相談しながら進めたい');
    $this->patch($save, ['work_style' => ''])->assertRedirect($list);
    expect($query->fresh()->detailed_skills)->toBeNull();
    expect($newer->fresh()->detailed_skills)->toBeNull();
});

test('header on public pages reuses the authorized session query directly', function () {
    $query = navigationQuery();
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('jobs.start'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//header//nav/a')->item(2)->getAttribute('href'))
        ->toBe(route('query.preferences.edit', $query->public_id));
});

test('header from job detail retains the job return context', function () {
    $query = navigationQuery();
    $job = navigationJob();
    $context = ['userQuery' => $query->public_id, 'page' => 2, 'tools' => ['autocad'], 'sort' => 'newest'];
    $detail = route('query.jobs.show', [...$context, 'job' => $job->id]);
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get($detail)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    $edit = $xpath->query('//header//nav/a')->item(2)->getAttribute('href');
    parse_str(parse_url($edit, PHP_URL_QUERY), $params);
    expect($params)->toMatchArray(['page' => '2', 'sort' => 'newest', 'tools' => ['autocad'], 'return_job' => (string) $job->id]);
    $this->get($edit)->assertOk()->assertSee(e($detail), false);
    $this->patch(route('query.preferences.update', [...$context, 'return_job' => $job->id]), ['work_style' => '相談したい'])->assertRedirect($detail);
});

test('navigation reuses bound or controller resolved queries and shares one lookup between header and footer', function (string $name) {
    $query = navigationQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $selects = [];
    $record = true;
    DB::listen(function ($event) use (&$selects, &$record) {
        if ($record && preg_match('/^select\b.*\bfrom `user_queries`/i', $event->sql)) {
            $selects[] = $event->sql;
        }
    });
    try {
        $response = $this->get(route($name, str_starts_with($name, 'query.') ? ['userQuery' => $query->public_id] : []))->assertOk();
    } finally {
        $record = false;
    }
    // Includes the route binding or public controller lookup, not just view rendering.
    expect($selects)->toHaveCount(1);
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($dom);
    foreach (['header', 'footer'] as $area) {
        expect($xpath->query('//'.$area.'//nav/a')->item(2)->getAttribute('href'))
            ->toBe(route('query.preferences.edit', $query->public_id));
    }
})->with(['jobs.start', 'public.resources', 'public.compare', 'query.preferences.edit', 'query.agencies', 'query.jobs']);

test('navigation resolution including an absent query never leaks into another request', function () {
    $query = navigationQuery();
    $target = route('public.resources');
    $edit = route('query.preferences.edit', $query->public_id);
    $guide = route('public.preferences');
    $this->get($target)->assertOk()->assertSee($guide, false)->assertDontSee($edit, false);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get($target)->assertOk()->assertSee($edit, false)->assertDontSee($guide, false);
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])
        ->get($target)->assertOk()->assertSee($guide, false)->assertDontSee($edit, false);
});

test('preferences cannot be edited or saved without an existing query', function () {
    $id = (string) Str::uuid();
    $this->get(route('public.preferences'))->assertOk()->assertDontSee('<form', false);
    $this->get(route('query.preferences.edit', $id))->assertNotFound();
    $this->patch(route('query.preferences.update', $id), ['work_style' => '相談したい'])->assertNotFound();
    $this->assertDatabaseCount('user_queries', 0);
});
