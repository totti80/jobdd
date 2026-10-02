<?php

use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Support\CompanyJobDashboardPresenter;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

test('dashboard derives the complete lifecycle and snapshot difference without mutating content', function () {
    [$owner, $job, $admin] = publishFixture();
    $presenter = app(CompanyJobDashboardPresenter::class);
    expect($presenter->present($job)['primary'])->toBe('Preview');
    $job->update(['description' => null]);
    expect($presenter->present($job)['primary'])->toBe('入力を再開');
    $job->update(['description' => '機械の設計を担当します。']);
    app(CompanyJobReviewService::class)->request($job, $owner);
    $job->refresh();
    expect($presenter->present($job)['primary'])->toBe('審査状況を見る');
    $job->update(['review_status' => 'changes_requested', 'review_note' => '<script>note()</script>']);
    $state = $presenter->present($job);
    expect($state['primary'])->toBe('修正する')->and($state['review'])->toBe('修正をお願いします');
    app(CompanyJobReviewService::class)->request($job, $owner);
    $job->refresh();
    app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job));
    $job->refresh();
    $snapshot = $job->publishedProfile->profile_data;
    expect($presenter->present($job)['primary'])->toBe('編集')->and($presenter->present($job)['changed'])->toBeFalse();
    $job->update(['title' => '更新中の求人']);
    $state = $presenter->present($job);
    expect($state['primary'])->toBe('編集を続ける')->and($state['changed'])->toBeTrue();
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('更新作業中');
    $job->update(['review_status' => 'pending_review']);
    expect($presenter->present($job)['review'])->toBe('更新内容を審査中');
    $this->get(route('company.dashboard'))->assertOk()->assertSee('現在公開中の内容はそのまま表示されています。');
    $job->update(['review_status' => 'changes_requested']);
    expect($presenter->present($job)['review'])->toBe('更新内容の修正をお願いします');
    expect($job->publishedProfile->fresh()->profile_data)->toBe($snapshot);
});

test('pending review blocks basic and all structured saves while preview remains available', function (bool $published) {
    [$owner, $job, $admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    if ($published) {
        $job->refresh();
        app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job));
        app(CompanyJobReviewService::class)->request($job->fresh(), $owner);
    }
    $job->refresh();
    $before = app(CompanyJobAuthoringData::class)->read($job);
    $this->actingAs($owner)->patch(route('company.jobs.basic.update', $job), ['title' => '改変'])->assertStatus(409);
    foreach (range(1, 5) as $step) {
        $this->patch(route('company.jobs.structured.update', [$job, $step]), [])->assertStatus(409);
    }
    $this->get(route('company.jobs.basic.edit', $job))->assertOk()->assertSee('審査中は編集できません')->assertSee('disabled', false);
    $this->get(route('company.jobs.structured.edit', [$job, 1]))->assertOk()->assertSee('審査中は編集できません');
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertDontSee('Level 1を編集')->assertDontSee('Level 2を編集');
    expect(app(CompanyJobAuthoringData::class)->read($job->fresh()))->toBe($before);
    $job->update(['review_status' => 'changes_requested']);
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '修正再開'])->assertRedirect();
})->with([false, true]);

test('invalid dashboard lifecycle is flagged rather than treated as a normal CTA', function (string $review) {
    [$owner, $job] = publishFixture();
    $job->update(['review_status' => $review, 'description' => null]);
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('状態不整合')->assertSee('状態を確認する');
    expect(app(CompanyJobDashboardPresenter::class)->present($job)['invalid'])->toBeTrue();
})->with(['approved', 'pending_review']);

test('company entry screens explain self service and controlled publication', function () {
    $this->get(route('login'))->assertOk()->assertSee('企業ログイン')->assertSee('ログイン状態を保持')->assertSee('新規企業登録')->assertSee('公開審査');
    $this->get(route('company.register'))->assertOk()->assertSee('新規企業登録')->assertSee('企業登録後、すぐに求人の作成を始められます。')->assertSee('企業アカウントを作成')->assertDontSee('新規企業登録（申請）');
});

test('incomplete basic draft can save and proceed to level two', function () {
    [$owner, $job] = publishFixture();
    $this->actingAs($owner)->patch(route('company.jobs.basic.update', $job), ['title' => '入力途中', 'description' => null, 'occupation' => null, 'navigation' => 'next'])->assertSessionHasNoErrors()->assertRedirect(route('company.jobs.structured.edit', [$job, 1]));
    expect($job->fresh()->description)->toBeNull()->and($job->fresh()->status)->toBe('draft');
});
