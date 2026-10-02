<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Services\JobDiscoveryService;
use App\Services\RouteSummaryService;
use App\Support\CompanyJobDashboardPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

function lifecycleFixture(string $role = 'company_owner'): array
{
    [$owner, $job, $admin] = publishFixture($role);
    app(CompanyJobReviewService::class)->request($job, $owner);
    app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));

    return [$owner, $job->fresh(), $admin];
}

function lifecycleArtifacts(JobPosting $job): array
{
    return [
        'snapshot' => $job->publishedProfile()->sole()->toArray(),
        'facts' => $job->jobFacts()->orderBy('id')->get()->toArray(),
        'routes' => $job->applicationRoutes()->orderBy('id')->get()->toArray(),
        'sources' => DB::table('sources')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'authoring' => app(CompanyJobAuthoringData::class)->read($job->fresh()),
    ];
}

test('company members can pause and resume unchanged approved content without regenerating artifacts', function (string $role) {
    [$owner, $job] = lifecycleFixture($role);
    Mail::fake();
    $before = lifecycleArtifacts($job);
    $review = $job->only(['review_requested_at', 'reviewed_at', 'reviewed_by_user_id', 'review_note', 'published_at']);
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('この求人の公開を停止しますか？')->assertSee('求人データと過去に承認された公開内容は保持されます。')->assertSee('戻る');
    $this->post(route('company.jobs.pause', $job))->assertRedirect(route('company.dashboard'));
    expect($job->fresh()->status)->toBe('paused')->and($job->fresh()->review_status)->toBe('approved')
        ->and(lifecycleArtifacts($job))->toBe($before)->and($job->fresh()->only(array_keys($review)))->toEqual($review);
    $this->get(route('company.dashboard'))->assertOk()->assertSee('公開停止中')->assertSee('公開を再開')->assertDontSee('公開ページを見る')
        ->assertViewHas('counts', fn ($counts) => $counts['published'] === 0 && $counts['creating'] === 0);
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('公開を再開')->assertDontSee('再公開申請する');
    $this->post(route('company.jobs.resume', $job))->assertRedirect(route('company.dashboard'));
    expect($job->fresh()->status)->toBe('published')->and($job->fresh()->review_status)->toBe('approved')
        ->and(lifecycleArtifacts($job))->toBe($before);
    $this->get(route('jobs.provenance', $job))->assertOk();
    Mail::assertNothingSent();
})->with(['company_owner', 'company_editor']);

test('paused approved publication disappears from all seeker boundaries while artifacts remain', function () {
    [$owner, $job] = lifecycleFixture();
    $other = $job->company->jobPostings()->create(['title' => '別の公開求人', 'occupation' => '機械設計', 'region' => '兵庫県', 'source_url' => 'https://careers.publish-company.jp/second', 'status' => 'published']);
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'lifecycle-token', 'raw_text' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $before = lifecycleArtifacts($job);
    $route = $job->applicationRoutes()->sole();
    $this->actingAs($owner)->post(route('company.jobs.pause', $job))->assertRedirect();
    expect(app(JobDiscoveryService::class)->discover($query)->modelKeys())->toBe([$other->id]);
    foreach (['query.jobs', 'query.results'] as $name) {
        $this->get(route($name, ['userQuery' => $query->public_id]))->assertOk()->assertDontSee($job->title);
    }
    // The map is projected from the same candidate list.
    $list = $this->get(route('query.jobs', ['userQuery' => $query->public_id]));
    $list->assertViewHas('items', fn ($items) => ! collect($items)->contains(fn ($item) => $item['job']->id === $job->id));
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]))->assertNotFound();
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $other->id]]))->assertNotFound();
    foreach (['routes.show', 'routes.action', 'jobs.provenance'] as $name) {
        $this->get(route($name, $job))->assertNotFound();
    }
    $this->get(route('public.job', $job))->assertRedirect(route('jobs.start', ['guide' => 'unavailable']));
    $this->get(route('home'))->assertOk()->assertDontSee($job->title);
    $this->postJson('/interaction/contact-clicked', ['application_route_id' => $route->id, 'contact_type' => 'apply'])->assertUnprocessable();
    expect(app(RouteSummaryService::class)->summarize($query)->sum('candidate_count'))->toBe(0)
        ->and(lifecycleArtifacts($job))->toBe($before);
});

test('paused changes require review lock correction and admin approval before republication', function (string $role) {
    [$owner, $job, $admin] = lifecycleFixture($role);
    $before = lifecycleArtifacts($job);
    $this->actingAs($owner)->post(route('company.jobs.pause', $job))->assertRedirect();
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '停止中の更新タイトル'])->assertRedirect();
    $this->patch(route('company.jobs.structured.update', [$job, 4]), ['work_style' => '停止中の新しい働き方', 'difficult_points' => '新しい難しさ'])->assertRedirect();
    expect($job->fresh()->status)->toBe('paused')->and(app(CompanyJobDashboardPresenter::class)->present($job->fresh())['changed'])->toBeTrue();
    $this->get(route('company.dashboard'))->assertOk()->assertSee('更新作業中')->assertSee('編集を続ける')->assertDontSee('公開を再開');
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('再公開申請する')->assertDontSee('公開を再開');
    $this->post(route('company.jobs.resume', $job))->assertStatus(409);
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->status)->toBe('paused')->and($job->fresh()->review_status)->toBe('pending_review');
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('再公開審査中')->assertSee('現在、この求人は求職者向けには表示されていません。更新内容の承認後に再公開されます。')->assertDontSee('Level 1を編集');
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '改ざん'])->assertStatus(409);
    foreach (range(1, 5) as $step) {
        $this->patch(route('company.jobs.structured.update', [$job, $step]), [])->assertStatus(409);
    }
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $this->actingAs($admin)->get(route('admin.job-reviews.index'))->assertOk()->assertSee('再公開申請')->assertSee('公開停止中');
    $this->get(route('admin.job-reviews.show', $job))->assertOk()->assertSee('現在は公開停止中です。');
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token, 'review_note' => '停止中の応募条件を確認してください'])->assertRedirect();
    expect($job->fresh()->status)->toBe('paused')->and($job->fresh()->review_status)->toBe('changes_requested');
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('修正する')->assertSee('停止中の応募条件を確認してください');
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('停止中の応募条件を確認してください')->assertSee('再公開申請する');
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '修正済み再公開タイトル'])->assertRedirect();
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->publishedProfile()->sole()->toArray())->toBe($before['snapshot'])
        ->and($job->jobFacts()->orderBy('id')->get()->toArray())->toBe($before['facts'])
        ->and($job->applicationRoutes()->orderBy('id')->get()->toArray())->toBe($before['routes']);
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertRedirect();
    expect($job->fresh()->status)->toBe('published')->and($job->fresh()->review_status)->toBe('approved')
        ->and($job->publishedProfile()->sole()->profile_data['level_one']['title'])->toBe('修正済み再公開タイトル')
        ->and($job->publishedProfile()->sole()->id)->toBe($before['snapshot']['id'])
        ->and($job->applicationRoutes()->sole()->id)->toBe($before['routes'][0]['id']);
})->with(['company_owner', 'company_editor']);

test('emergency pause withdraws pending review and preserves content history and stale approval protection', function () {
    [$owner, $job, $admin] = lifecycleFixture();
    $job->update(['title' => '緊急停止対象の更新']);
    app(CompanyJobReviewService::class)->request($job, $owner);
    // History fields present on an existing record must survive the stop.
    $job->refresh()->update(['reviewed_at' => now()->subDay(), 'reviewed_by_user_id' => $admin->id, 'review_note' => '過去の確認内容']);
    $before = lifecycleArtifacts($job);
    $history = $job->only(['review_requested_at', 'reviewed_at', 'reviewed_by_user_id', 'review_note']);
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('審査中の更新申請は取り下げられます。');
    $this->post(route('company.jobs.pause', $job))->assertRedirect();
    expect($job->fresh()->status)->toBe('paused')->and($job->fresh()->review_status)->toBe('not_submitted')
        ->and($job->fresh()->only(array_keys($history)))->toEqual($history)->and(lifecycleArtifacts($job))->toBe($before);
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('再公開申請する');
    $this->post(route('company.jobs.resume', $job))->assertStatus(409);
    $this->get(route('jobs.provenance', $job))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.job-reviews.index'))->assertOk()->assertDontSee($job->title);
    $this->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertStatus(409);
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token, 'review_note' => '古い申請'])->assertStatus(409);
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => app(CompanyJobAuthoringData::class)->token($job->fresh())])->assertRedirect();
    expect($job->fresh()->status)->toBe('published');
});

test('lifecycle actions reject guests outsiders invalid states and unsafe HTTP methods', function () {
    [$owner, $job] = lifecycleFixture();
    $before = lifecycleArtifacts($job);
    foreach (['pause', 'resume'] as $action) {
        $this->post(route('company.jobs.'.$action, $job))->assertRedirect(route('login'));
    }
    $outsider = User::factory()->create();
    $outsider->companies()->attach(Company::create(['name' => '他社']), ['role' => 'company_editor']);
    foreach (['pause', 'resume'] as $action) {
        $this->actingAs($outsider)->post(route('company.jobs.'.$action, $job))->assertForbidden();
    }
    $this->actingAs($owner)->post(route('company.jobs.resume', $job))->assertStatus(409);
    foreach (['pause', 'resume'] as $action) {
        $this->get(route('company.jobs.'.$action, $job))->assertStatus(405);
        $this->post(route('company.jobs.'.$action, 999999))->assertNotFound();
    }
    $this->post(route('company.jobs.pause', $job))->assertRedirect();
    $this->post(route('company.jobs.pause', $job))->assertStatus(409);
    foreach (['draft', 'closed'] as $status) {
        $job->update(['status' => $status]);
        foreach (['pause', 'resume'] as $action) {
            $this->post(route('company.jobs.'.$action, $job))->assertStatus(409);
        }
    }
    $job->update(['status' => 'paused']);
    foreach (['not_submitted', 'pending_review', 'changes_requested'] as $review) {
        $job->update(['review_status' => $review]);
        $this->post(route('company.jobs.resume', $job))->assertStatus(409);
    }
    expect(lifecycleArtifacts($job))->toBe($before);
});

test('resume fails closed without a readable snapshot and detects structured and company changes', function (string $change) {
    [$owner, $job] = lifecycleFixture();
    $this->actingAs($owner)->post(route('company.jobs.pause', $job))->assertRedirect();
    match ($change) {
        'missing' => $job->publishedProfile()->delete(),
        'schema' => $job->publishedProfile()->update(['profile_data' => ['schema_version' => 999]]),
        'company' => $job->company()->update(['name' => '変更後の社名']),
        'structured' => $job->structuredProfile()->update(['design_target' => '変更後の設計対象']),
        'tools' => $job->toolUsages()->update(['usage_notes' => '変更後のツール情報']),
        'day' => $job->typicalDayItems()->update(['activity' => '変更後の一日']),
    };
    $this->post(route('company.jobs.resume', $job))->assertStatus(409);
    expect($job->fresh()->status)->toBe('paused');
})->with(['missing', 'schema', 'company', 'structured', 'tools', 'day']);

test('failed paused republication rolls back publication artifacts and review state', function () {
    [$owner, $job, $admin] = lifecycleFixture();
    $this->actingAs($owner)->post(route('company.jobs.pause', $job))->assertRedirect();
    $job->update(['title' => '再公開失敗のテスト']);
    app(CompanyJobReviewService::class)->request($job, $owner);
    $before = lifecycleArtifacts($job);
    JobPosting::updating(function ($model) {
        if ($model->isDirty('status') && $model->status === 'published') {
            throw new RuntimeException('paused publish rollback');
        }
    });
    try {
        expect(fn () => app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh())))
            ->toThrow(RuntimeException::class, 'paused publish rollback');
        expect($job->fresh()->status)->toBe('paused')->and($job->fresh()->review_status)->toBe('pending_review')
            ->and(lifecycleArtifacts($job))->toBe($before);
    } finally {
        JobPosting::flushEventListeners();
    }
});

test('platform owner retains lifecycle permissions without company membership', function () {
    [, $job, $admin] = lifecycleFixture();
    $this->actingAs($admin)->post(route('company.jobs.pause', $job))->assertRedirect();
    $this->post(route('company.jobs.resume', $job))->assertRedirect();
    expect($job->fresh()->status)->toBe('published');
});
