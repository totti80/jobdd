<?php

use App\Mail\CompanyJobReviewRequested;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

test('preview presents publication review and actions for every lifecycle state', function (string $status, string $review, bool $changed, string $heading, bool $canRequest) {
    [$owner, $job, $admin] = publishFixture();
    if ($status === 'published') {
        app(CompanyJobReviewService::class)->request($job, $owner);
        $job->refresh();
        app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job));
        $job->refresh();
    }
    $job->update(['review_status' => $review, 'review_requested_at' => now(), 'review_note' => $review === 'changes_requested' ? '<script>確認内容</script>' : null]);
    if ($changed) {
        $job->update(['title' => '更新内容の機械設計']);
    }
    $before = app(CompanyJobAuthoringData::class)->read($job->fresh());
    $snapshot = $job->publishedProfile?->profile_data;
    $response = $this->actingAs($owner)->get(route('company.jobs.preview', $job))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee($heading)->assertSee('申請日時')->assertSee('id="review-status"', false)->assertSee('id="preview-content"', false);
    expect(substr_count($response->getContent(), '<h1 '))->toBe(1);
    if ($canRequest) {
        $response->assertSee('action="'.route('company.jobs.review-request', $job).'"', false)->assertSee('承認後に求職者向けへ公開されます。');
    } else {
        $response->assertDontSee('action="'.route('company.jobs.review-request', $job).'"', false);
    }
    if ($review === 'pending_review') {
        $response->assertDontSee('Level 1を編集')->assertDontSee('Level 2を編集')->assertSee('審査状況を見る')->assertSee('審査中は編集できません');
    }
    if ($review === 'changes_requested') {
        $response->assertSee('JobDDからの確認内容')->assertSee('&lt;script&gt;確認内容&lt;/script&gt;', false)->assertDontSee('<script>確認内容</script>', false)->assertSee('修正する')->assertSee('修正内容を見る');
    }
    if ($status === 'published') {
        $response->assertSee('公開ページを見る');
    }
    expect(app(CompanyJobAuthoringData::class)->read($job->fresh()))->toBe($before)->and($job->publishedProfile?->fresh()?->profile_data)->toBe($snapshot);
})->with([
    ['draft', 'not_submitted', false, '公開申請前の確認', true],
    ['draft', 'pending_review', false, '公開審査中', false],
    ['draft', 'changes_requested', false, '修正をお願いします', true],
    ['published', 'approved', false, '公開されています', false],
    ['published', 'approved', true, '更新作業中', true],
    ['published', 'pending_review', true, '更新内容を審査中', false],
    ['published', 'changes_requested', true, '更新内容の修正をお願いします', true],
]);

test('invalid application input is explained with edit destinations and a disabled request', function () {
    [$owner, $job] = publishFixture();
    $job->update(['source_url' => null, 'application_requirements' => null]);
    $this->actingAs($owner)->get(route('company.jobs.preview', $job))->assertOk()->assertSee('公開申請に必要な入力')->assertSee('応募URL')->assertSee('最低限の応募条件')->assertSee('Level 1の基本情報を修正')->assertSee('Level 2の仕事の中身を修正')->assertSee('disabled', false);
    $this->from(route('company.jobs.preview', $job))->post(route('company.jobs.review-request', $job))->assertRedirect(route('company.jobs.preview', $job))->assertSessionHasErrors(['level_one.source_url', 'level_one.application_requirements']);
    expect($job->fresh()->review_status)->toBe('not_submitted')->and($job->fresh()->review_requested_at)->toBeNull();
    Mail::assertNothingSent();
});

test('company request admin correction reapplication and approval form one controlled publish flow', function () {
    [$owner, $job, $admin] = publishFixture();
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertRedirect(route('company.jobs.preview', $job))->assertSessionHas('status', '公開申請を受け付けました。現在、JobDD運営が確認中です。');
    expect($job->fresh()->status)->toBe('draft')->and($job->fresh()->review_status)->toBe('pending_review')->and($job->fresh()->review_requested_at)->not->toBeNull();
    Mail::assertSent(CompanyJobReviewRequested::class, fn ($mail) => $mail->hasTo('postmaster@jobdd.jp'));
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('承認されるまで求職者向けには公開されません。');
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '改ざん'])->assertStatus(409);
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $detail = $this->actingAs($admin)->get(route('admin.job-reviews.show', $job))->assertOk()->assertSee('今回申請された内容')->assertSee('id="approve-confirm"', false)->assertSee('修正を依頼');
    expect(substr_count($detail->getContent(), 'name="review_token"'))->toBe(2)->and(substr_count($detail->getContent(), '<h1 '))->toBe(1);
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $token, 'review_note' => '応募条件を明確にしてください。'])->assertRedirect();
    $this->actingAs($owner)->get(route('company.jobs.preview', $job))->assertOk()->assertSee('JobDDからの確認内容')->assertSee('応募条件を明確にしてください。');
    $this->patch(route('company.jobs.basic.update', $job), ['title' => $job->title, 'application_requirements' => '図面の読解経験'])->assertRedirect();
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertRedirect()->assertSessionHas('status', '承認して公開しました。');
    $this->get(route('admin.job-reviews.show', $job))->assertOk()->assertDontSee('action="'.route('admin.job-reviews.approve', $job).'"', false)->assertDontSee('action="'.route('admin.job-reviews.changes-requested', $job).'"', false);
    $this->post(route('admin.job-reviews.approve', $job), ['review_token' => $token])->assertStatus(409);
    expect($job->fresh()->status)->toBe('published')->and($job->fresh()->review_status)->toBe('approved')->and($job->publishedProfile()->sole()->profile_data['level_one']['application_requirements'])->toBe('図面の読解経験');
});

test('admin queue exposes company job state dates and the existing review preview destinations', function () {
    [$owner, $job, $admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    $this->actingAs($admin)->get(route('admin.job-reviews.index'))->assertOk()->assertSee($job->company->name)->assertSee($job->title)->assertSee($job->occupation)->assertSee('公開申請日時')->assertSee('最終更新')->assertSee('未公開')->assertSee('審査中')->assertSee('審査する')->assertSee(route('admin.job-reviews.show', $job).'#preview-content', false);
});
