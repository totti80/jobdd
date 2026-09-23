<?php

use App\Mail\CompanyJobReviewRequested;
use App\Models\ApplicationRoute;
use App\Models\JobPosting;
use App\Models\Source;
use App\Models\User;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobPublishValidator;
use App\Services\CompanyJobReviewService;
use App\Services\ContextRoleClassifier;
use App\Services\JobDetailUseCaseService;
use App\Services\JobDiscoveryService;
use App\Services\JobFitService;
use App\Services\RouteSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(fn () => Mail::fake());

require_once __DIR__.'/../Support/PublishFixture.php';

function reviewToken(JobPosting $job): string
{
    return app(CompanyJobAuthoringData::class)->token($job->fresh());
}
function requestAndApprove(JobPosting $job, User $owner, User $admin): void
{
    app(CompanyJobReviewService::class)->request($job, $owner);
    app(CompanyJobPublishService::class)->approve($job, $admin, reviewToken($job));
}
function publicArtifacts(JobPosting $job): array
{
    return ['facts' => $job->jobFacts()->orderBy('id')->get()->toArray(), 'snapshot' => $job->publishedProfile()->first()?->toArray(), 'routes' => $job->applicationRoutes()->orderBy('id')->get()->toArray(), 'sources' => Source::orderBy('id')->get()->toArray()];
}

test('preview is authorized escaped and read only for owner editor and platform', function ($role) {
    [$user,$job,$admin] = publishFixture($role);
    $job->update(['title' => '<script>secret()</script>']);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $this->actingAs($user)->get(route('company.jobs.preview', $job))->assertOk()->assertSee('PREVIEW')->assertSee('生産設備')->assertSee('AutoCAD')->assertSee('搬送装置')->assertSee('&lt;script&gt;secret()&lt;/script&gt;', false)->assertDontSee('<script>secret()</script>', false);
    expect(array_filter($queries, fn ($sql) => preg_match('/^(insert|update|delete)\b/i', $sql)))->toBe([]);
    $this->actingAs($admin)->get(route('company.jobs.preview', $job))->assertOk();
    expect($job->fresh()->review_status)->toBe('not_submitted');
    $this->assertDatabaseCount('job_facts', 0);
    $this->assertDatabaseCount('job_published_profiles', 0);
})->with(['company_owner', 'company_editor']);

test('guests and other companies cannot preview request or administer', function () {
    [$owner,$job,$admin] = publishFixture();
    foreach (['company.jobs.preview', 'admin.job-reviews.show'] as $route) {
        $this->get(route($route, $job))->assertRedirect(route('login'));
    }
    $this->post(route('company.jobs.review-request', $job))->assertRedirect(route('login'));
    [$other] = publishFixture();
    $this->actingAs($other)->get(route('company.jobs.preview', $job))->assertForbidden();
    $this->post(route('company.jobs.review-request', $job))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.job-reviews.index'))->assertForbidden();
    $this->get(route('admin.job-reviews.show', $job))->assertForbidden();
    foreach (['approve', 'changes-requested'] as $action) {
        $this->post(route('admin.job-reviews.'.$action, $job), ['review_token' => reviewToken($job), 'review_note' => '理由'])->assertForbidden();
    }
    Mail::assertNothingSent();
});

test('each missing Core or Level 1 field prevents publication requests', function ($target, $field) {
    [$owner,$job] = publishFixture();
    match ($target) {
        'basic' => $job->update([$field => $field === 'title' ? '' : null]),
        'profile' => $job->structuredProfile()->update([$field => null]),
        'tool' => $job->toolUsages()->update([$field => null]),
        'day' => $job->typicalDayItems()->update([$field => null]),
        'no_tools' => $job->toolUsages()->delete(),
        'no_days' => $job->typicalDayItems()->delete(),
        'salary' => $job->update(['salary_min' => null, 'salary_max' => null]),
    };
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertSessionHasErrors();
    expect($job->fresh()->review_status)->toBe('not_submitted');
    Mail::assertNothingSent();
})->with([
    ['basic', 'title'], ['basic', 'occupation'], ['basic', 'region'], ['basic', 'employment_type'], ['basic', 'description'], ['basic', 'application_requirements'], ['basic', 'source_url'], ['salary', 'salary'],
    ['profile', 'design_target'], ['profile', 'design_phases'], ['profile', 'initial_assignment'], ['profile', 'collaborators'], ['profile', 'customer_contact_frequency'], ['profile', 'customer_contact_note'], ['profile', 'manufacturing_relation_frequency'], ['profile', 'manufacturing_relation_note'], ['profile', 'site_relation_frequency'], ['profile', 'site_relation_note'], ['profile', 'work_style'], ['profile', 'difficult_points'],
    ['tool', 'tool_name'], ['tool', 'usage_context'], ['tool', 'experience_expectation'], ['day', 'time_label'], ['day', 'activity'], ['no_tools', 'tools'], ['no_days', 'days'],
]);

test('valid incomplete optional fields pass and invalid core keys or url fail', function () {
    [$owner,$job] = publishFixture();
    $job->structuredProfile()->update(['representative_project' => null, 'hard_to_convey' => null, 'future_scope' => null]);
    expect(app(CompanyJobPublishValidator::class)->errors($job->fresh()))->toBe([]);
    $job->update(['source_url' => 'javascript:alert(1)']);
    expect(app(CompanyJobPublishValidator::class)->errors($job->fresh()))->not->toBe([]);
    $job->update(['source_url' => 'https://careers.publish-company.jp']);
    $job->structuredProfile()->update(['design_phases' => ['fake']]);
    expect(app(CompanyJobPublishValidator::class)->errors($job->fresh()))->not->toBe([]);
});

test('request is idempotent mails configured admin URL once and cannot publish', function () {
    [$owner,$job,$admin] = publishFixture();
    config(['jobdd.review_notification_email' => 'review@example.test']);
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertRedirect(route('company.jobs.preview', $job));
    $requested = $job->fresh()->review_requested_at;
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->review_status)->toBe('pending_review')->and($job->fresh()->status)->toBe('draft')->and($job->fresh()->review_requested_at->equalTo($requested))->toBeTrue();
    $this->assertDatabaseCount('job_facts', 0);
    $this->assertDatabaseCount('job_published_profiles', 0);
    Mail::assertSent(CompanyJobReviewRequested::class, 1);
    Mail::assertSent(CompanyJobReviewRequested::class, function ($mail) use ($job) {
        expect($mail->render())->toContain('公開テスト株式会社', '機械設計エンジニア', route('admin.job-reviews.show', $job))->not->toContain('/approve');

        return $mail->hasTo('review@example.test');
    });
    $this->actingAs($admin)->get(route('admin.job-reviews.index'))->assertOk()->assertSee($job->title);
    $this->get(route('admin.job-reviews.show', $job))->assertOk()->assertSee('承認して公開');
    expect($job->fresh()->status)->toBe('draft');
});

test('notification failure logs clearly but keeps committed request', function () {
    [$owner,$job] = publishFixture();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mail unavailable'));
    Log::shouldReceive('error')->once()->with('Company job review notification failed; request remains pending.', Mockery::on(fn ($context) => $context['job_posting_id'] === $job->id));
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->review_status)->toBe('pending_review');
});

test('approval atomically publishes source facts snapshot route and explicit roles', function () {
    [$owner,$job,$admin] = publishFixture();
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertRedirect();
    $token = reviewToken($job);
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertRedirect();
    $job->refresh();
    expect($job->status)->toBe('published')->and($job->review_status)->toBe('approved')->and($job->reviewed_by_user_id)->toBe($admin->id)->and($job->reviewed_at)->not->toBeNull()->and($job->published_at)->not->toBeNull();
    $source = Source::sole();
    expect($source->source_type)->toBe('company_self_reported')->and($source->url)->toBe(route('jobs.provenance', $job))->not->toBe($job->source_url);
    $facts = $job->jobFacts;
    expect($facts)->not->toBeEmpty()->and($facts->every(fn ($f) => $f->extraction_method === 'company_self_reported' && $f->verification_status === 'self_reported' && filled($f->context_role) && $f->source_id === $source->id))->toBeTrue();
    expect($facts->where('fact_category', 'tool_usage')->sole()->context_role)->toBe('responsibility')->and($facts->where('fact_category', 'tool_expectation')->sole()->context_role)->toBe('required_experience');
    expect($facts->where('fact_key', 'customer_contact')->sole()->normalized_value)->toBe('several_times_month')->and($facts->where('fact_key', 'customer_contact')->sole()->evidence_text)->toContain('仕様の確認');
    expect($facts->where('fact_category', 'typical_day'))->toHaveCount(0);
    expect($job->publishedProfile->profile_data['typical_day_items'][0]['activity'])->toBe('設計打合せ')->and($job->publishedProfile->profile_data['structured_profile']['representative_project']['what_made'])->toBe('搬送装置');
    expect($job->applicationRoutes()->sole()->application_url)->toBe($job->source_url);
    $before = publicArtifacts($job);
    $this->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertStatus(409);
    expect(publicArtifacts($job))->toBe($before);
    $this->get(route('jobs.provenance', $job))->assertOk()->assertSee('真偽を保証するものではありません');
});

test('stale admin preview is rejected when company edits pending content', function () {
    [$owner,$job,$admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    $token = reviewToken($job);
    $this->actingAs($owner)->patch(route('company.jobs.structured.update', [$job, 4]), ['difficult_points' => '更新された難しさ'])->assertRedirect();
    expect($job->fresh()->review_status)->toBe('pending_review');
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertStatus(409);
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token, 'review_note' => '古い確認'])->assertStatus(409);
    $this->assertDatabaseCount('job_published_profiles', 0);
    $this->post(route('admin.job-reviews.approve', $job), ['review_token' => reviewToken($job)])->assertRedirect();
});

test('changes request needs reason preserves public artifacts and supports reapplication', function () {
    [$owner,$job,$admin] = publishFixture();
    requestAndApprove($job, $owner, $admin);
    $before = publicArtifacts($job);
    app(CompanyJobReviewService::class)->request($job->fresh(), $owner);
    $token = reviewToken($job);
    $this->actingAs($admin)->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token])->assertSessionHasErrors('review_note');
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token, 'review_note' => '具体例を追記してください'])->assertRedirect();
    expect($job->fresh()->status)->toBe('published')->and($job->fresh()->review_status)->toBe('changes_requested')->and($job->fresh()->review_note)->toBe('具体例を追記してください')->and(publicArtifacts($job))->toBe($before);
    $this->actingAs($owner)->get(route('company.dashboard'))->assertSee('具体例を追記してください');
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->review_note)->toBeNull()->and($job->fresh()->reviewed_by_user_id)->toBeNull();
});

test('republish keeps old public Level 1 filters facts routes until approval and preserves external facts', function () {
    [$owner,$job,$admin] = publishFixture();
    requestAndApprove($job, $owner, $admin);
    $firstPublished = $job->fresh()->published_at;
    $external = $job->jobFacts()->create(['fact_category' => 'tool', 'fact_key' => 'inventor', 'fact_value' => 'Inventor', 'normalized_value' => 'Inventor', 'extraction_method' => 'rule', 'verification_status' => 'verified', 'evidence_text' => 'Inventorを使用', 'observed_at' => now()]);
    $job->applicationRoutes()->create(['route_type' => 'platform', 'application_url' => 'https://platform.example.test']);
    $before = publicArtifacts($job);
    $input = $job->only(CompanyJobAuthoringData::BASIC_FIELDS);
    $input['title'] = '未承認の電気設計';
    $input['occupation'] = '電気設計';
    $input['region'] = '大阪府';
    $input['salary_min'] = 900;
    $input['salary_max'] = 1000;
    $input['source_url'] = 'https://careers.changed-company.jp/apply';
    $this->actingAs($owner)->patch(route('company.jobs.basic.update', $job), $input)->assertRedirect();
    $this->patch(route('company.jobs.structured.update', [$job, 2]), ['tools' => [['tool_name' => '社内CAD', 'usage_context' => 'other_department', 'experience_expectation' => 'not_required']]])->assertRedirect();
    expect(publicArtifacts($job))->toBe($before)->and($job->fresh()->review_status)->toBe('approved');
    $public = JobPosting::query()->forPublic()->findOrFail($job->id);
    expect($public->title)->toBe('機械設計エンジニア')->and($public->occupation)->toBe('機械設計')->and($public->salary_max)->toBe(600)->and($public->source_url)->toBe('https://careers.publish-company.jp/apply');
    $query = new UserQuery(['occupation' => '機械設計', 'region' => '兵庫県']);
    expect(app(JobDiscoveryService::class)->discover($query)->pluck('id')->all())->toBe([$job->id]);
    $this->get(route('company.jobs.preview', $job))->assertSee('未承認の電気設計');
    app(CompanyJobReviewService::class)->request($job->fresh(), $owner);
    expect($job->fresh()->status)->toBe('published')->and(publicArtifacts($job))->toBe($before);
    $this->travel(2)->seconds();
    app(CompanyJobPublishService::class)->approve($job->fresh(), $admin, reviewToken($job));
    expect(JobPosting::query()->forPublic()->find($job->id)->title)->toBe('未承認の電気設計');
    expect($job->fresh()->published_at->equalTo($firstPublished))->toBeTrue()->and($job->fresh()->publishedProfile->published_at->greaterThan($firstPublished))->toBeTrue();
    expect($job->jobFacts()->whereKey($external->id)->exists())->toBeTrue()->and($job->applicationRoutes()->where('route_type', 'direct')->count())->toBe(1)->and($job->applicationRoutes()->where('route_type', 'platform')->count())->toBe(1)->and($job->publishedProfile()->count())->toBe(1)->and(Source::count())->toBe(1);
    $fact = $job->jobFacts()->where('fact_category', 'tool_usage')->sole();
    expect($fact->fact_key)->toBe('other_tool')->and($fact->normalized_value)->toBe('社内CAD')->and($fact->context_role)->toBe('other_department');
});

test('late publish failure rolls back all publication and review writes', function () {
    [$owner,$job,$admin] = publishFixture();
    requestAndApprove($job, $owner, $admin);
    app(CompanyJobReviewService::class)->request($job->fresh(), $owner);
    $before = publicArtifacts($job);
    $state = $job->fresh()->toArray();
    JobPosting::updating(function ($model) {
        if ($model->isDirty('review_status') && $model->review_status === 'approved') {
            throw new RuntimeException('publish rollback probe');
        }
    });
    try {
        expect(fn () => app(CompanyJobPublishService::class)->approve($job->fresh(), $admin, reviewToken($job)))->toThrow(RuntimeException::class, 'publish rollback probe');
        expect(publicArtifacts($job))->toBe($before)->and($job->fresh()->toArray())->toBe($state);
    } finally {
        JobPosting::flushEventListeners();
    }
});

test('company facts do not add Fit axes and detail uses explicit context without classifier inference', function () {
    [$owner,$job,$admin] = publishFixture();
    requestAndApprove($job, $owner, $admin);
    $job = JobPosting::query()->forPublic()->with('jobFacts')->find($job->id);
    $query = new UserQuery(['occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 300]);
    $fit = app(JobFitService::class)->evaluate($query, $job, $job->jobFacts->all(), ['desired' => [['fact_key' => 'autocad', 'action' => 'use']]]);
    expect(array_column($fit['axes'], 'key'))->toBe(['occupation', 'region', 'salary', 'tool_use:autocad'])->and($fit['axes'][3]['status'])->toBe('unknown');
    $this->mock(ContextRoleClassifier::class)->shouldNotReceive('classify');
    $detail = app(JobDetailUseCaseService::class)->run($query, $job->id);
    $tool = collect($detail['presence_facts'])->first(fn ($item) => $item['fact']->fact_category === 'tool_usage');
    expect($tool['context']['role'])->toBe('responsibility');
});

test('self report source cannot overwrite external application URL source and direct route reuses identity', function () {
    [$owner,$job,$admin] = publishFixture();
    $external = Source::create(['url' => $job->source_url, 'source_type' => 'official_site', 'publisher' => '外部']);
    $before = $external->fresh()->toArray();
    $direct = $job->applicationRoutes()->create(['route_type' => 'direct', 'availability_status' => 'unavailable', 'application_url' => 'https://old.publish-company.jp']);
    requestAndApprove($job, $owner, $admin);
    expect($external->fresh()->toArray())->toBe($before)->and(Source::count())->toBe(2)->and($job->applicationRoutes()->sole()->id)->toBe($direct->id)->and($direct->fresh()->availability_status)->toBe('available');
});

test('explicit tool semantics never manufacture responsibility or experience', function ($usage, $expectation, $usageRole, $expectationRole) {
    [$owner,$job,$admin] = publishFixture();
    $job->toolUsages()->update(['usage_context' => $usage, 'experience_expectation' => $expectation]);
    requestAndApprove($job, $owner, $admin);
    expect($job->jobFacts()->where('fact_category', 'tool_usage')->sole()->context_role)->toBe($usageRole)
        ->and($job->jobFacts()->where('fact_category', 'tool_expectation')->sole()->context_role)->toBe($expectationRole);
})->with([
    ['primary', 'required', 'responsibility', 'required_experience'],
    ['occasional', 'preferred', 'responsibility', 'preferred_experience'],
    ['other_department', 'not_required', 'other_department', 'unknown'],
    ['not_used', 'undecided', 'unknown', 'unknown'],
    ['undecided', 'not_required', 'unknown', 'unknown'],
]);

test('first publication failure leaves no newly generated artifacts', function () {
    [$owner,$job,$admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    ApplicationRoute::creating(fn () => throw new RuntimeException('route rollback probe'));
    try {
        expect(fn () => app(CompanyJobPublishService::class)->approve($job, $admin, reviewToken($job)))->toThrow(RuntimeException::class);
        foreach (['sources', 'job_facts', 'job_published_profiles', 'application_routes'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        expect($job->fresh()->status)->toBe('draft')->and($job->fresh()->review_status)->toBe('pending_review')->and($job->fresh()->reviewed_at)->toBeNull();
    } finally {
        ApplicationRoute::flushEventListeners();
    }
});

test('every seeker read boundary retains the approved basic version during editing', function () {
    [$owner,$job,$admin] = publishFixture();
    requestAndApprove($job, $owner, $admin);
    $second = $job->company->jobPostings()->create(['title' => '別の公開求人', 'occupation' => '機械設計', 'region' => '兵庫県', 'source_url' => 'https://careers.publish-company.jp/second', 'status' => 'published']);
    $job->update(['title' => '秘密の未承認タイトル', 'occupation' => '電気設計', 'region' => '大阪府', 'description' => '秘密の未承認本文', 'salary_min' => 999, 'salary_max' => 1000]);
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'published-read-token', 'raw_text' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    foreach (['query.jobs', 'query.results'] as $route) {
        $this->get(route($route, ['userQuery' => $query->public_id]))->assertOk()->assertSee('機械設計エンジニア')->assertDontSee('秘密の未承認');
    }
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]))->assertOk()->assertSee('機械設計エンジニア')->assertDontSee('秘密の未承認');
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $second->id]]))->assertOk()->assertSee('機械設計エンジニア')->assertDontSee('秘密の未承認');
    foreach (['routes.show', 'routes.action'] as $route) {
        $this->get(route($route, $job))->assertOk()->assertSee('機械設計エンジニア')->assertDontSee('秘密の未承認');
    }
    $summary = app(RouteSummaryService::class)->summarize($query);
    expect(json_encode($summary, JSON_UNESCAPED_UNICODE))->not->toContain('秘密の未承認');
});

test('draft provenance is private and default review recipient is postmaster', function () {
    [$owner,$job] = publishFixture();
    $this->get(route('jobs.provenance', $job))->assertNotFound();
    app(CompanyJobReviewService::class)->request($job, $owner);
    Mail::assertSent(CompanyJobReviewRequested::class, fn ($mail) => $mail->hasTo('postmaster@jobdd.jp'));
});
