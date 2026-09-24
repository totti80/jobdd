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
        ->assertDontSee('あなた専用 転職コンシェルジュ');
})->with(['home', 'jobs.start', 'public.resources', 'public.contact']);

test('coming soon pages neither expose operations email nor provide inquiry forms', function () {
    foreach (['public.resources', 'public.contact'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('現在準備中')->assertDontSee('postmaster@')->assertDontSee('<form', false);
    }
});

test('resolvers without query lead to the input form with useful guidance', function () {
    $this->get(route('public.preferences'))->assertRedirect(route('jobs.start', ['guide' => 'preferences']));
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
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get(route('public.preferences'))->assertRedirect(route('jobs.start', ['guide' => 'preferences']));
    foreach ([$query->public_id, ['invalid']] as $reference) {
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
