<?php

use App\Mail\CompanyJobReviewRequested;
use App\Models\Company;
use App\Models\User;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Services\StructuredProfileCompletionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

function hardeningApprove($job, $owner, $admin): void
{
    app(CompanyJobReviewService::class)->request($job, $owner);
    app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));
    $job->refresh();
}

function hardeningQuery(): UserQuery
{
    return UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'private-query-token', 'raw_text' => 'private free text', 'occupation' => '機械設計', 'region' => '兵庫県']);
}

test('Phase A HTTP vertical slice registers authors reviews republishes and reaches seeker routes', function () {
    $this->post(route('company.register.store'), ['account_name' => '通し検証株式会社', 'email' => 'slice@example.test', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!', 'system_role' => 'platform_owner', 'role' => 'company_editor'])->assertRedirect(route('company.dashboard'));
    $owner = User::where('email', 'slice@example.test')->sole();
    expect($owner->system_role)->toBe('user')->and(Hash::check('Strong-password-123!', $owner->password))->toBeTrue()
        ->and($owner->companies()->sole()->pivot->role)->toBe('company_owner');
    $this->get(route('company.dashboard'))->assertOk();
    $basic = ['title' => '通し検証の機械設計', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400, 'salary_max' => 650, 'employment_type' => '正社員', 'description' => '生産設備を設計する仕事です。', 'source_url' => 'https://careers.slice-company.jp/apply', 'application_requirements' => '図面が読めること'];
    $this->post(route('company.jobs.store'), $basic)->assertRedirect();
    $job = $owner->companies()->sole()->jobPostings()->sole();
    expect($job->status)->toBe('draft')->and($job->review_status)->toBe('not_submitted');
    $steps = [
        1 => ['design_target' => '搬送設備', 'design_phases' => ['concept', 'detailed_design'], 'initial_assignment' => '部品設計から担当', 'future_scope' => '構想にも関わる可能性'],
        2 => ['tools' => [['tool_key' => 'autocad', 'tool_name' => 'AutoCAD', 'usage_context' => 'primary', 'experience_expectation' => 'preferred']]],
        3 => ['collaborators' => ['design_team', 'manufacturing'], 'customer_contact_frequency' => 'several_times_month', 'customer_contact_note' => '仕様確認', 'manufacturing_relation_frequency' => 'almost_daily', 'manufacturing_relation_note' => '組立調整', 'site_relation_frequency' => 'rarely', 'site_relation_note' => '別担当が対応', 'work_style' => 'チームで相談'],
        4 => ['difficult_points' => '精度と納期の両立'],
        5 => ['typical_day' => [['time_label' => '午前', 'activity' => '設計会議'], ['time_label' => '午後', 'activity' => '図面作成']], 'representative_project' => ['what_made' => '搬送装置', 'phases' => ['concept'], 'team' => '3名']],
    ];
    foreach ($steps as $step => $input) {
        $this->get(route('company.jobs.structured.edit', [$job, $step]))->assertOk();
        $this->patch(route('company.jobs.structured.update', [$job, $step]), $input)->assertRedirect();
    }
    $this->patch(route('company.jobs.structured.update', [$job, 2]), $steps[2])->assertRedirect();
    $this->patch(route('company.jobs.structured.update', [$job, 5]), $steps[5])->assertRedirect();
    expect($job->toolUsages()->count())->toBe(1)->and($job->typicalDayItems()->orderBy('sort_order')->pluck('activity')->all())->toBe(['設計会議', '図面作成']);
    expect(app(StructuredProfileCompletionService::class)->calculate($job->fresh())['percentage'])->toBe(100);
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('搬送設備');
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->review_status)->toBe('pending_review')->and($job->fresh()->status)->toBe('draft');
    Mail::assertSent(CompanyJobReviewRequested::class, fn ($mail) => $mail->hasTo('postmaster@jobdd.jp') && str_contains($mail->render(), '通し検証株式会社') && ! str_contains($mail->render(), '/approve'));
    $admin = User::factory()->create(['system_role' => 'platform_owner']);
    $this->actingAs($admin)->get(route('admin.job-reviews.index'))->assertOk()->assertSee($basic['title']);
    $review = $this->get(route('admin.job-reviews.show', $job))->assertOk();
    $this->post(route('admin.job-reviews.changes-requested', $job), ['review_token' => $review->viewData('reviewToken'), 'review_note' => '内容を再確認してください'])->assertRedirect();
    expect($job->fresh()->review_status)->toBe('changes_requested')->and($job->fresh()->status)->toBe('draft')->and($job->publishedProfile()->count())->toBe(0);
    $this->actingAs($owner)->get(route('company.dashboard'))->assertOk()->assertSee('内容を再確認してください');
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->fresh()->review_note)->toBeNull();
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => app(CompanyJobAuthoringData::class)->token($job->fresh())])->assertRedirect();
    $job->refresh();
    expect($job->status)->toBe('published')->and($job->review_status)->toBe('approved')->and($job->reviewed_by_user_id)->toBe($admin->id)->and($job->reviewed_at)->not->toBeNull();
    $external = $job->jobFacts()->create(['fact_category' => 'tool', 'fact_key' => 'autocad', 'fact_value' => 'AutoCAD', 'extraction_method' => 'rule', 'verification_status' => 'unverified', 'evidence_text' => '既存の外部根拠']);
    $snapshot = $job->publishedProfile()->sole()->profile_data;
    $factCount = $job->jobFacts()->where('extraction_method', 'company_self_reported')->count();
    $sourceId = $snapshot['provenance']['source_id'];
    $routeId = $job->applicationRoutes()->sole()->id;
    $this->actingAs($owner)->patch(route('company.jobs.basic.update', $job), [...$basic, 'title' => '更新した機械設計'])->assertRedirect();
    $this->patch(route('company.jobs.structured.update', [$job, 1]), [...$steps[1], 'design_target' => '更新した搬送設備'])->assertRedirect();
    $this->get(route('company.jobs.preview', $job))->assertOk()->assertSee('更新した搬送設備');
    $this->post(route('company.jobs.review-request', $job))->assertRedirect();
    expect($job->publishedProfile()->sole()->profile_data)->toBe($snapshot);
    $this->get(route('jobs.provenance', $job))->assertOk()->assertDontSee('更新した機械設計');
    $this->actingAs($admin)->post(route('admin.job-reviews.approve', $job), ['review_token' => app(CompanyJobAuthoringData::class)->token($job->fresh())])->assertRedirect();
    expect($job->publishedProfile()->count())->toBe(1)->and($job->publishedProfile()->sole()->profile_data['structured_profile']['design_target'])->toBe('更新した搬送設備')
        ->and($job->jobFacts()->where('extraction_method', 'company_self_reported')->count())->toBe($factCount)
        ->and($external->fresh()->evidence_text)->toBe('既存の外部根拠')
        ->and($job->publishedProfile()->sole()->profile_data['provenance']['source_id'])->toBe($sourceId)
        ->and($job->applicationRoutes()->sole()->id)->toBe($routeId);
    auth()->logout();
    $this->get(route('jobs.start'))->assertOk();
    $this->post(route('jobs.store'), ['occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400, 'tools' => ['autocad']])->assertRedirect();
    $query = UserQuery::sole();
    $this->get(route('query.jobs', $query->public_id))->assertOk()->assertSee('更新した機械設計');
    $detailUrl = route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]);
    $before = $this->get($detailUrl)->assertOk()->assertSee('更新した搬送設備')->viewData('items')[0]['fit'];
    $this->get(route('query.preferences.edit', $query->public_id))->assertOk();
    $this->patch(route('query.preferences.update', $query->public_id), ['design_phases' => ['detailed_design'], 'customer_contact' => 'want_less', 'work_style' => '相談しながら進めたい'])->assertRedirect();
    expect($this->get($detailUrl)->assertOk()->viewData('items')[0]['fit'])->toBe($before);
    $legacy = Company::create(['name' => '既存企業'])->jobPostings()->create([...$basic, 'title' => '既存の機械設計', 'status' => 'published']);
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $legacy->id]]))->assertOk()->assertSee('あなたの希望')->assertSee('企業提供情報')->assertSee('外部情報');
    $this->get(route('jobs.provenance', $job))->assertOk()->assertSee('JobDD公開確認済み');
    $this->get(route('routes.show', $job))->assertOk()->assertSee($basic['source_url'], false);
    foreach (['route-selected', 'contact-clicked'] as $event) {
        $this->postJson(route('interaction.'.$event), ['application_route_id' => $routeId, 'user_query_id' => $query->id])->assertNoContent();
    }
    expect(DB::table('interaction_logs')->where('event_type', 'contact_clicked')->value('metadata'))->toBeNull();
});

test('public reads retain approved company and hide unsupported snapshots everywhere', function ($version) {
    [$owner, $job, $admin] = publishFixture();
    hardeningApprove($job, $owner, $admin);
    $job->company()->update(['name' => '未承認会社名']);
    $job->update(['title' => '未承認タイトル', 'review_status' => 'changes_requested']);
    if ($version !== 1) {
        $snapshot = $job->publishedProfile()->sole();
        $snapshot->update(['profile_data' => [...$snapshot->profile_data, 'schema_version' => $version]]);
    }
    $query = hardeningQuery();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    foreach (['query.jobs', 'query.results'] as $name) {
        $response = $this->get(route($name, $query->public_id))->assertOk()->assertDontSee('未承認会社名')->assertDontSee('未承認タイトル');
        if ($version === 1) {
            $response->assertSee('公開テスト株式会社');
        }
    }
    foreach (['routes.show', 'routes.action', 'jobs.provenance'] as $name) {
        $response = $this->get(route($name, $job));
        if ($version === 1) {
            $response->assertOk()->assertSee('公開テスト株式会社')->assertDontSee('未承認会社名');
        } else {
            $response->assertNotFound();
        }
    }
})->with([1, 99]);

test('interaction targets exclude private jobs and unavailable routes without writing logs', function ($state) {
    [$owner, $job, $admin] = publishFixture();
    hardeningApprove($job, $owner, $admin);
    $route = $job->applicationRoutes()->sole();
    match ($state) {
        'draft', 'paused', 'closed' => $job->update(['status' => $state]),
        'unavailable' => $route->update(['availability_status' => 'unavailable']),
        'timestamp' => $route->update(['unavailable_at' => now()]),
        'unsupported' => $job->publishedProfile()->update(['profile_data' => ['schema_version' => 99]]),
    };
    foreach (['route-selected', 'contact-clicked'] as $event) {
        $this->postJson(route('interaction.'.$event), ['application_route_id' => $route->id])->assertUnprocessable()->assertJsonValidationErrors('application_route_id');
    }
    $this->assertDatabaseCount('interaction_logs', 0);
})->with(['draft', 'paused', 'closed', 'unavailable', 'timestamp', 'unsupported']);

test('legacy results rejects missing or empty session secrets', function () {
    $query = hardeningQuery();
    $query->update(['session_token' => '']);
    $this->get(route('query.results', $query->public_id))->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => ''])->get(route('query.results', $query->public_id))->assertNotFound();
});

test('legacy public application surfaces do not link unsafe URLs', function () {
    $query = hardeningQuery();
    $job = Company::create(['name' => '既存会社'])->jobPostings()->create(['title' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県', 'status' => 'published', 'source_url' => 'javascript:alert(1)']);
    $job->applicationRoutes()->create(['route_type' => 'direct', 'availability_status' => 'available', 'application_url' => 'javascript:alert(2)']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])->get(route('query.results', $query->public_id))->assertOk()->assertDontSee('href="javascript:', false);
    $this->get(route('routes.action', $job))->assertOk()->assertDontSee('href="javascript:', false);
});

test('HTTP authorization matrix protects own foreign draft published and review operations', function ($role) {
    [$owner, $ownPublished, $admin] = publishFixture();
    [$other, $foreignPublished] = publishFixture();
    hardeningApprove($ownPublished, $owner, $admin);
    hardeningApprove($foreignPublished, $other, $admin);
    $ownDraft = $ownPublished->company->jobPostings()->create(['title' => '自社Draft', 'status' => 'draft']);
    $foreignDraft = $foreignPublished->company->jobPostings()->create(['title' => '他社Draft', 'status' => 'draft']);
    $editor = User::factory()->create();
    $editor->companies()->attach($ownPublished->company_id, ['role' => 'company_editor']);
    $user = match ($role) {
        'owner' => $owner, 'editor' => $editor, 'other' => $other, 'admin' => $admin, 'normal' => User::factory()->create(), default => null
    };
    if ($user) {
        $this->actingAs($user);
    }
    foreach ([$ownDraft, $ownPublished, $foreignDraft, $foreignPublished] as $job) {
        $allowed = $role === 'admin' || (in_array($role, ['owner', 'editor']) && $job->company_id === $ownPublished->company_id) || ($role === 'other' && $job->company_id === $foreignPublished->company_id);
        foreach (['company.jobs.basic.edit', 'company.jobs.preview'] as $name) {
            $response = $this->get(route($name, $job));
            if (! $user) {
                $response->assertRedirect(route('login'));
            } elseif ($allowed) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
        $response = $this->patch(route('company.jobs.basic.update', $job), ['title' => '権限内の編集']);
        if (! $user) {
            $response->assertRedirect(route('login'));
        } elseif ($allowed) {
            $response->assertRedirect();
        } else {
            $response->assertForbidden();
        }
    }
    foreach (['admin.job-reviews.index', 'admin.job-reviews.show'] as $name) {
        $response = $this->get(route($name, $name === 'admin.job-reviews.show' ? $ownPublished : []));
        if (! $user) {
            $response->assertRedirect(route('login'));
        } elseif ($role === 'admin') {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
    foreach (['approve', 'changes-requested'] as $action) {
        // Non-pending requests are 409 only after the platform role has been authorized.
        $response = $this->post(route('admin.job-reviews.'.$action, $ownPublished), ['review_token' => app(CompanyJobAuthoringData::class)->token($ownPublished->fresh()), 'review_note' => '確認']);
        if (! $user) {
            $response->assertRedirect(route('login'));
        } elseif ($role === 'admin') {
            $response->assertConflict();
        } else {
            $response->assertForbidden();
        }
    }
})->with(['guest', 'normal', 'owner', 'editor', 'other', 'admin']);

test('CSRF is enforced for authoring review preferences and interactions outside the unit bypass', function () {
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
    [$owner, $job, $admin] = publishFixture();
    hardeningApprove($job, $owner, $admin);
    $query = hardeningQuery();
    $this->actingAs($owner)->withSession(['_token' => 'expected-csrf', 'jobdd_query_token_'.$query->public_id => $query->session_token]);
    foreach ([['PATCH', route('company.jobs.basic.update', $job)], ['POST', route('company.jobs.review-request', $job)], ['POST', route('admin.job-reviews.approve', $job)], ['PATCH', route('query.preferences.update', $query->public_id)], ['POST', route('interaction.contact-clicked')]] as [$method, $url]) {
        $this->call($method, $url)->assertStatus(419);
    }
    $this->postJson(route('interaction.contact-clicked'), ['application_route_id' => $job->applicationRoutes()->sole()->id], ['X-CSRF-TOKEN' => 'expected-csrf'])->assertNoContent();
});

test('dashboard and review queue reads stay bounded as visible jobs grow', function () {
    [$owner, $job, $admin] = publishFixture();
    $measure = function ($user, $name) {
        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route($name))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return count(array_filter($queries, fn ($sql) => preg_match('/^select\b/i', $sql)));
    };
    $job->update(['review_status' => 'pending_review', 'review_requested_at' => now()]);
    $before = [$measure($owner, 'company.dashboard'), $measure($admin, 'admin.job-reviews.index')];
    for ($i = 0; $i < 24; $i++) {
        $job->company->jobPostings()->create(['title' => '追加求人'.$i, 'status' => 'draft', 'review_status' => 'pending_review', 'review_requested_at' => now()]);
    }
    $after = [$measure($owner, 'company.dashboard'), $measure($admin, 'admin.job-reviews.index')];
    expect($after)->toBe($before);
    expect($after[0])->toBeLessThanOrEqual(8)->and($after[1])->toBeLessThanOrEqual(4);
});
