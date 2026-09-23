<?php

use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobDetailUseCaseService;
use App\Services\JobSelectionUseCaseService;
use App\Support\SeekerPreferences;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

function progressiveQuery($skills = null): UserQuery
{
    return UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => Str::random(64), 'raw_text' => 'private seeker input', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 500, 'detailed_skills' => $skills]);
}

function progressiveInput(array $changes = []): array
{
    return array_replace(['design_phases' => ['detailed_design', 'testing'], 'customer_contact' => 'want_less', 'manufacturing_relation' => 'want_more', 'site_relation' => 'no_preference', 'work_style' => 'チームで相談しながら進めたい'], $changes);
}

function progressiveJob(): JobPosting
{
    [$owner, $job, $admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));

    return $job;
}

function preferenceUrl(UserQuery $query, array $context = []): string
{
    return route('query.preferences.edit', ['userQuery' => $query->public_id, ...$context]);
}

test('simple entry remains sufficient and the list progressively offers optional preferences', function () {
    $response = $this->post(route('jobs.store'), ['occupation' => '機械設計', 'region' => '兵庫県', 'tools' => ['autocad'], 'custom_tools' => 'iCAD']);
    $response->assertRedirect();
    $query = UserQuery::sole();
    expect($query->detailed_skills)->toBe(['custom_tools' => 'iCAD']);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('もっと詳しく比較する')->assertSee('入力は任意です')->assertSee('iCAD');
    $this->get(preferenceUrl($query, ['tools' => ['autocad']]))->assertOk()->assertSee('すべて任意です')->assertSee('詳細設計')->assertSee('試験・評価')->assertSee('保存せず求人へ戻る')->assertSee('iCAD');
    $this->assertDatabaseCount('user_queries', 1);
    $this->assertDatabaseCount('score_results', 0);
});

test('saving and clearing replaces only the preference namespace and preserves simple and legacy intent', function ($legacy) {
    $query = progressiveQuery($legacy);
    $query->update(['priorities' => ['salary', 'region'], 'industry' => '既存業種', 'experience_years' => 4]);
    $original = $query->fresh()->getAttributes();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $url = preferenceUrl($query, ['page' => 3, 'tools' => ['nx', 'autocad']]);
    $input = progressiveInput();
    $this->patch($url, [...$input, 'occupation' => '電気設計', 'salary_min' => 999, 'priorities' => ['fake'], 'detailed_skills' => ['overwrite'], 'public_id' => 'overwrite', 'session_token' => 'overwrite', 'return_url' => 'https://outside.jp/'])->assertRedirect(route('query.jobs', ['userQuery' => $query->public_id, 'page' => 3, 'tools' => ['nx', 'autocad']]));
    $query->refresh();
    expect($query->detailed_skills)->toEqual([...(array) $legacy, 'seeker_preferences' => $input]);
    foreach ($original as $key => $value) {
        if (! in_array($key, ['detailed_skills', 'updated_at'])) {
            expect($query->getAttributes()[$key])->toBe($value);
        }
    }
    // A later request in the same authorized session reloads the persisted values.
    $this->get($url)->assertOk()->assertSee('チームで相談しながら進めたい')->assertViewHas('values', SeekerPreferences::values($input));
    $replacement = ['design_phases' => ['concept'], 'customer_contact' => 'neutral'];
    $this->patch($url, $replacement)->assertRedirect();
    $this->patch($url, $replacement)->assertRedirect();
    expect($query->fresh()->detailed_skills)->toEqual([...(array) $legacy, 'seeker_preferences' => $replacement]);
    $this->patch($url, [])->assertRedirect();
    expect($query->fresh()->detailed_skills)->toEqual($legacy ?: null);
    $this->assertDatabaseCount('user_queries', 1);
    $this->assertDatabaseCount('interaction_logs', 0);
})->with([[null], [['custom_tools' => 'iCAD', 'legacy' => ['note' => 'keep']]], [['CAD経験', '解析経験']]]);

test('invalid detailed values return escaped editable errors without modifying stored intent', function ($changes) {
    $query = progressiveQuery(['custom_tools' => 'keep']);
    $before = $query->fresh()->getAttributes();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->patch(preferenceUrl($query), progressiveInput($changes))->assertStatus(422)->assertSee('入力内容を確認してください')->assertSee('name="work_style"', false);
    expect($query->fresh()->getAttributes())->toBe($before);
})->with([
    [['design_phases' => ['fake']]], [['design_phases' => ['concept', 'concept']]], [['design_phases' => 'concept']],
    [['design_phases' => ['named' => 'concept']]], [['design_phases' => [[]]]],
    [['customer_contact' => 'almost_daily']], [['manufacturing_relation' => 'fake']], [['site_relation' => ['want_more']]],
    [['work_style' => ['unsafe']]], [['work_style' => str_repeat('あ', 2001)]],
]);

test('preferences require the existing query session for reads and writes', function ($method, $token) {
    $query = progressiveQuery();
    $other = progressiveQuery();
    $before = $query->fresh()->getAttributes();
    $this->withSession(['jobdd_query_token_'.$other->public_id => $other->session_token]);
    if ($token !== null) {
        $this->withSession(['jobdd_query_token_'.$query->public_id => $token]);
    }
    $this->{$method}(preferenceUrl($query), $method === 'patch' ? progressiveInput() : [])->assertNotFound();
    expect($query->fresh()->getAttributes())->toBe($before);
})->with([['get', null], ['patch', null], ['get', 'wrong'], ['patch', 'wrong'], ['get', ''], ['patch', '']]);

test('return context is bounded and cannot redirect to an external URL', function ($context) {
    $query = progressiveQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(preferenceUrl($query, $context))->assertStatus(422);
    $this->patch(preferenceUrl($query, $context), progressiveInput())->assertStatus(422);
    expect($query->fresh()->detailed_skills)->toBeNull();
})->with([[['page' => 0]], [['page' => 'bad']], [['tools' => ['fake']]], [['tools' => ['autocad', 'autocad']]], [['return_job' => 'https://outside.jp']], [['return_job' => '-1']]]);

test('v2 pairs preferences with approved content without attaching Fit labels and supports editing back to detail', function () {
    $job = progressiveJob();
    $query = progressiveQuery();
    $context = ['page' => 2, 'tools' => ['autocad'], 'return_job' => $job->id];
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $target = route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id, 'page' => 2, 'tools' => ['autocad']]);
    $this->get($target)->assertOk()->assertDontSee('data-preference-comparison', false)->assertSee('もっと詳しく比較する');
    $this->patch(preferenceUrl($query, $context), progressiveInput(['work_style' => '<script>alert(1)</script>相談したい']))->assertRedirect($target);
    $response = $this->get($target)->assertOk()->assertSee('あなたの詳細希望と、この求人の仕事')->assertSee('あなたの希望')->assertSee('この求人の公開情報')->assertSee('関わりは少なめがよい')->assertSee('積極的に関わりたい')->assertSee('特に希望なし')->assertSee('月に数回')->assertSee('仕様の確認')->assertSee('ほぼ毎日')->assertSee('チームで設計')->assertSee('詳細希望を変更する')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('want_less');
    $rows = $response->viewData('decision_view')['preference_rows'];
    expect($rows)->toHaveCount(5);
    foreach ($rows as $row) {
        expect(array_keys($row))->toBe(['label', 'preference', 'job']);
    }
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-preference-comparison]//*[@data-status or @data-axis]')->length)->toBe(0);
    $job->structuredProfile()->update(['design_phases' => ['site_support'], 'customer_contact_note' => '未承認の接点', 'work_style' => '未承認の進め方']);
    $this->get($target)->assertOk()->assertDontSee('未承認の接点')->assertDontSee('未承認の進め方')->assertViewHas('decision_view', fn ($view) => $view['preference_rows'] === $rows);
    $this->get(preferenceUrl($query, $context))->assertOk()->assertSee($target)->assertSee('相談したい');
    $this->patch(preferenceUrl($query, $context), [])->assertRedirect($target);
    $this->get($target)->assertOk()->assertDontSee('data-preference-comparison', false);
});

test('preferences leave all public Fit results selection order comparison and score records unchanged', function ($skills) {
    $job = progressiveJob();
    $query = progressiveQuery($skills);
    $query->update(['priorities' => ['salary']]);
    $legacy = JobPosting::create(['company_id' => $job->company_id, 'title' => '既存の求人', 'occupation' => '機械設計', 'region' => '大阪府', 'source_url' => 'https://careers.legacy-company.jp/job']);
    $requirements = ['desired' => [['fact_key' => 'autocad', 'action' => 'use']]];
    $before = app(JobDecisionUseCaseService::class)->run($query, 1, $requirements);
    $selectedBefore = app(JobSelectionUseCaseService::class)->run($query, [$legacy->id, $job->id], $requirements);
    $scores = DB::table('score_results')->get()->toJson();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->patch(preferenceUrl($query), progressiveInput())->assertRedirect();
    $after = app(JobDecisionUseCaseService::class)->run($query->fresh(), 1, $requirements);
    $selectedAfter = app(JobSelectionUseCaseService::class)->run($query->fresh(), [$legacy->id, $job->id], $requirements);
    expect(array_column($after['items'], 'fit'))->toBe(array_column($before['items'], 'fit'))
        ->and(array_map(fn ($item) => $item['job']->id, $after['items']))->toBe(array_map(fn ($item) => $item['job']->id, $before['items']))
        ->and(array_column($selectedAfter['items'], 'fit'))->toBe(array_column($selectedBefore['items'], 'fit'))
        ->and(DB::table('score_results')->get()->toJson())->toBe($scores);
    $detail = app(JobDetailUseCaseService::class)->run($query->fresh(), $job->id, $requirements);
    expect($detail['items'][0]['fit'])->toBe($selectedBefore['items'][1]['fit']);
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $legacy->id], 'tools' => ['autocad']]))->assertOk()->assertDontSee('data-preference-comparison', false);
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $legacy->id]))->assertOk()->assertViewIs('query.job-show')->assertSee('既存の求人');
    expect(SeekerPreferences::read($query->fresh())['work_style'])->toBe('チームで相談しながら進めたい');
})->with([[null], [['custom_tools' => 'iCAD']], [['既存スキル']]]);

test('missing approved profile values remain neutral and never inferred from preferences', function () {
    $job = progressiveJob();
    $snapshot = $job->publishedProfile()->first();
    $data = $snapshot->profile_data;
    $data['structured_profile'] = [];
    $snapshot->update(['profile_data' => $data]);
    $query = progressiveQuery(['seeker_preferences' => progressiveInput()]);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]))->assertOk()->assertSee('求人側の情報はまだ確認できていません');
});

test('detailed form reads database sessions without writes and PATCH enforces real CSRF', function () {
    $query = progressiveQuery();
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_'.$query->public_id, $query->session_token);
    $session->save();
    $before = DB::table('sessions')->get()->toJson();
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $this->withCookie($session->getName(), $session->getId())->get(preferenceUrl($query))->assertOk();
    } finally {
        $record = false;
    }
    expect(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    $this->patch(preferenceUrl($query), progressiveInput())->assertStatus(419);
    expect($query->fresh()->detailed_skills)->toBeNull();
    $this->patch(preferenceUrl($query), [...progressiveInput(), '_token' => $session->token()])->assertRedirect();
    expect($query->fresh()->detailed_skills['seeker_preferences'])->toEqual(progressiveInput())
        ->and(DB::table('sessions')->get()->toJson())->toBe($before);
});

test('unmergeable legacy JSON is preserved instead of overwritten', function () {
    $query = progressiveQuery('legacy scalar');
    $before = $query->fresh()->getAttributes();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->patch(preferenceUrl($query), progressiveInput())->assertStatus(409);
    expect($query->fresh()->getAttributes())->toBe($before);
});
