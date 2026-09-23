<?php

use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Services\JobDetailUseCaseService;
use App\Services\JobSelectionUseCaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

function decisionPublish($job, $owner, $admin): void
{
    app(CompanyJobReviewService::class)->request($job, $owner);
    app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));
}

function decisionQuery(): UserQuery
{
    return UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'seeker-secret', 'raw_text' => 'private-input', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400]);
}

function decisionUrl($query, $job): string
{
    return route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id, 'tools' => ['autocad'], 'page' => 2]);
}

function decisionContent($response): string
{
    // Framework-injected assets are request-local; compare the actual public detail markup.
    expect(preg_match('/<main\b.*?<\/main>/s', $response->getContent(), $matches))->toBe(1);

    return $matches[0];
}

test('approved snapshot renders decision sections provenance and existing route and comparison links', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $query = decisionQuery();
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job));
    $response->assertOk()->assertViewIs('query.job-show-v2')->assertSee('data-decision-view="v2"', false);
    foreach (['この求人の要点', 'あなたの希望との照合', '何を設計する仕事か', 'どの工程を担当するか', '入社直後 → 将来', 'CAD / Tool', '誰と仕事をするか', '仕事の進め方', '代表的な1日', 'この仕事の難しいところ', '合いやすい働き方', '合いにくい可能性がある働き方', '代表的な案件', '情報源と根拠', '応募方法',
        '公開テスト株式会社', '生産設備', '工場向け', '構想', '詳細設計', '部品設計', '将来的な担当可能性（確約ではありません）', 'AutoCAD', '主に使う', '必須経験', '製図', '同じ設計チーム', '製造', '月に数回', 'ほぼ毎日', 'ほとんどない', '仕様の確認', '組立調整', '現場は別担当', 'チームで設計', '設計打合せ', '精度を保つ', '製品知識', '相談しながら進める', '単独完結を希望', '搬送装置', '半年', '3名', '図面を読めること', '設計経験', '解析経験', '調整の多さ',
        '代表的な1日の例です。毎日同じ業務内容を保証するものではありません。', '企業提供情報', 'JobDD公開確認済み', '企業申告内容の真実性を保証するものではありません。', '公開確認・公開日時', '日本時間', '情報源を見る', '項目別の根拠を見る', '保存された根拠（日本語表示）', '比較に追加', '応募方法を見る'] as $text) {
        $response->assertSee($text);
    }
    foreach (['detailed_design', 'manufacturing_support', 'several_times_month', '向いている人', '向いていない人', 'あなたに最適', 'seeker-secret', 'private-input'] as $text) {
        $response->assertDontSee($text);
    }
    $response->assertSee(route('routes.show', $job), false)->assertSee('select_job='.$job->id, false)->assertSee('page=2', false)->assertSee('tools%5B0%5D=autocad', false);
    $this->get(route('routes.show', $job))->assertOk()->assertSee('https://careers.publish-company.jp/apply', false);
    expect(DB::table('interaction_logs')->where('event_type', 'route_opened')->where('target_id', $job->id)->exists())->toBeTrue();
});

test('authoring changes pending review and requested changes preserve the last approved page until republish', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $query = decisionQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $before = decisionContent($this->get(decisionUrl($query, $job))->assertOk());
    $job->update(['title' => '再承認まで非公開のタイトル', 'region' => '大阪府', 'salary_min' => 800, 'salary_max' => 900, 'source_url' => 'https://careers.publish-company.jp/next', 'application_requirements' => '再承認まで非公開の条件']);
    $job->company()->update(['name' => '再承認まで非公開の会社名']);
    $job->structuredProfile()->update(['design_target' => '再承認まで非公開の設計対象', 'fit_work_style' => '再承認まで非公開の働き方']);
    $job->toolUsages()->update(['tool_name' => '再承認まで非公開のツール']);
    $job->typicalDayItems()->update(['activity' => '再承認まで非公開の一日']);
    expect(decisionContent($this->get(decisionUrl($query, $job))->assertOk()))->toBe($before);
    app(CompanyJobReviewService::class)->request($job, $owner);
    expect(decisionContent($this->get(decisionUrl($query, $job))->assertOk()))->toBe($before);
    app(CompanyJobReviewService::class)->requestChanges($job, $admin, '確認してください', app(CompanyJobAuthoringData::class)->token($job->fresh()));
    expect(decisionContent($this->get(decisionUrl($query, $job))->assertOk()))->toBe($before);
    $this->travel(1)->hours();
    decisionPublish($job, $owner, $admin);
    $response = $this->get(decisionUrl($query, $job))->assertOk();
    foreach (['タイトル', '会社名', '設計対象', '働き方', 'ツール', '一日', '条件'] as $field) {
        $response->assertSee('再承認まで非公開の'.$field);
    }
    $response->assertSee('大阪府')->assertSee('800万円')->assertDontSee('生産設備');
    $snapshot = $job->publishedProfile()->first();
    $response->assertSee($snapshot->published_at->timezone('Asia/Tokyo')->format('Y年m月d日 H:i（日本時間）'));
    expect($snapshot->published_at->gt($job->fresh()->published_at))->toBeTrue();
});

test('initial draft publication states stay private even if a snapshot row exists', function ($reviewStatus, $withSnapshot) {
    [$owner, $job, $admin] = publishFixture();
    if ($withSnapshot) {
        decisionPublish($job, $owner, $admin);
    }
    $job->fresh()->update(['status' => 'draft', 'review_status' => $reviewStatus]);
    $query = decisionQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job))->assertNotFound();
})->with([['not_submitted', false], ['pending_review', false], ['changes_requested', false], ['approved', true]]);

test('decision detail preserves seeker session authorization and company preview authorization', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $query = decisionQuery();
    $this->get(decisionUrl($query, $job))->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get(decisionUrl($query, $job))->assertNotFound();
    $this->get(route('company.jobs.preview', $job))->assertRedirect(route('login'));
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job))->assertOk();
});

test('decision rendering reads no level two authoring tables and performs only bounded selects', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $query = decisionQuery();
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job))->assertOk();
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(7);
    foreach ($sql as $statement) {
        expect($statement)->toMatch('/^select\b/i')->not->toMatch('/job_structured_profiles|job_tool_usages|job_typical_day_items/i');
    }
});

test('v2 keeps existing Fit axes and self report tools unknown independently of detailed profile', function ($salary, $status) {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $query = decisionQuery();
    $query->update(['salary_min' => $salary]);
    $requirements = ['desired' => [['fact_key' => 'autocad', 'action' => 'use']]];
    $expected = app(JobSelectionUseCaseService::class)->run($query, [$job->id], $requirements)['items'][0]['fit'];
    $data = app(JobDetailUseCaseService::class)->run($query, $job->id, $requirements);
    expect($data['items'][0]['fit'])->toBe($expected);
    $axes = collect($expected['axes'])->keyBy('key');
    expect($axes->keys()->all())->toBe(['occupation', 'region', 'salary', 'tool_use:autocad'])
        ->and($axes['occupation']['status'])->toBe('match')->and($axes['region']['status'])->toBe('match')
        ->and($axes['salary']['status'])->toBe($status)->and($axes['tool_use:autocad']['status'])->toBe('unknown');
    $tool = collect($data['decision_view']['sections'])->firstWhere('id', 'tools')['tools'][0];
    expect($tool['fields']['仕事での使用'])->toBe('主に使う')->and($tool['fields']['応募時の経験要件'])->toBe('必須経験')->and($tool['status'])->toBe('unknown');
    $job->structuredProfile()->update(['design_phases' => ['testing'], 'customer_contact_frequency' => 'almost_daily', 'fit_work_style' => '別の働き方', 'required_experience' => '別の経験']);
    decisionPublish($job, $owner, $admin);
    $after = app(JobDetailUseCaseService::class)->run($query, $job->id, $requirements)['items'][0]['fit'];
    expect($after['axes'])->toBe($expected['axes'])->and($after['summary'])->toBe($expected['summary']);
})->with([[400, 'match'], [500, 'unknown'], [700, 'mismatch']]);

test('missing optional details stay neutral and snapshot values are escaped with safe source links', function () {
    [$owner, $job, $admin] = publishFixture();
    $job->structuredProfile()->update(['future_scope' => null, 'representative_project' => null, 'onboarding_challenges' => null, 'design_target' => '<script>unsafe()</script>']);
    decisionPublish($job, $owner, $admin);
    $snapshot = $job->publishedProfile()->first();
    $data = $snapshot->profile_data;
    $data['provenance']['url'] = 'javascript:alert(1)';
    $data['structured_profile']['design_phases'] = ['unknown_internal_key'];
    $data['tool_usages'] = [];
    $data['typical_day_items'] = [];
    $snapshot->update(['profile_data' => $data]);
    $query = decisionQuery();
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job));
    $response->assertOk()->assertSee('この情報はまだ確認できていません')->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)
        ->assertDontSee('<script>unsafe()</script>', false)->assertDontSee('href="javascript:', false)->assertDontSee('unknown_internal_key');
    $points = collect($response->viewData('decision_view')['sections'])->firstWhere('id', 'key-points')['points'];
    expect(count($points))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5);
});

test('timeline follows snapshot sort order and ignores authoring reorder', function () {
    [$owner, $job, $admin] = publishFixture();
    $job->typicalDayItems()->create(['time_label' => '午後', 'activity' => '図面レビュー', 'sort_order' => 10]);
    decisionPublish($job, $owner, $admin);
    $snapshot = $job->publishedProfile()->first();
    $data = $snapshot->profile_data;
    $data['typical_day_items'] = array_reverse($data['typical_day_items']);
    $snapshot->update(['profile_data' => $data]);
    $job->typicalDayItems()->update(['sort_order' => 99, 'activity' => '未承認の業務']);
    $query = decisionQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(decisionUrl($query, $job))->assertOk()->assertSeeInOrder(['午前', '設計打合せ', '午後', '図面レビュー'])->assertDontSee('未承認の業務');
});

test('legacy detail evidence routes and mixed comparison remain compatible', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $legacy = JobPosting::create(['company_id' => $job->company_id, 'title' => '既存の求人', 'occupation' => '機械設計', 'region' => '兵庫県', 'description' => '従来の本文', 'source_url' => 'https://careers.legacy-company.jp/job']);
    $legacy->jobFacts()->create(['fact_category' => 'tool', 'fact_key' => 'autocad', 'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'evidence_text' => 'AutoCADで設計します。', 'extraction_method' => 'rule', 'verification_status' => 'verified']);
    $legacy->applicationRoutes()->create(['route_type' => 'direct', 'availability_status' => 'available', 'application_url' => 'https://careers.legacy-company.jp/apply']);
    $query = decisionQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(decisionUrl($query, $legacy))->assertOk()->assertViewIs('query.job-show')->assertSee('求人詳細と根拠')->assertSee('従来の本文')->assertSee('AutoCADで設計します。')->assertSee('https://careers.legacy-company.jp/apply', false)->assertDontSee('data-decision-view="v2"', false);
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $legacy->id], 'tools' => ['autocad']]))->assertOk()->assertSee('機械設計エンジニア')->assertSee('既存の求人');
    $this->get(route('query.jobs', ['userQuery' => $query->public_id, 'select_job' => $job->id]))->assertOk()->assertSee('form="compare-selection"', false)->assertSee('比較に追加');
});

test('an unsupported snapshot never falls back to editable job content', function () {
    [$owner, $job, $admin] = publishFixture();
    decisionPublish($job, $owner, $admin);
    $snapshot = $job->publishedProfile()->first();
    $data = $snapshot->profile_data;
    $data['schema_version'] = 999;
    $snapshot->update(['profile_data' => $data]);
    $job->update(['title' => '未承認の本文へフォールバックしない']);
    $query = decisionQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionUrl($query, $job))->assertNotFound()->assertDontSee('未承認の本文へフォールバックしない');
});
