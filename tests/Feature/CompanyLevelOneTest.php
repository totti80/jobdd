<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function levelOneMember(string $role = 'company_owner'): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => '自社テスト企業']);
    $user->companies()->attach($company, ['role' => $role]);

    return [$user, $company];
}

function levelOneInput(array $overrides = []): array
{
    return array_replace([
        'title' => '機械設計エンジニア', 'occupation' => '機械設計', 'region' => '兵庫県神戸市',
        'salary_min' => 450, 'salary_max' => 750, 'employment_type' => '正社員',
        'description' => '産業機械の設計を担当します。', 'source_url' => 'https://careers.sample-company.jp/apply',
        'application_requirements' => '機械設計の実務経験2年以上',
    ], $overrides);
}

test('dashboard scopes jobs and counts to a single membership for owners and editors', function (string $role) {
    [$user, $company] = levelOneMember($role);
    $other = Company::create(['name' => '他社秘密企業']);
    $other->jobPostings()->create(['title' => '他社秘密求人']);
    foreach (JobPosting::REVIEW_STATUS_LABELS as $status => $label) {
        $company->jobPostings()->create(['title' => '自社-'.$status, 'status' => $status === 'approved' ? 'published' : 'draft', 'review_status' => $status]);
    }
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $this->actingAs($user)->get(route('company.dashboard'))->assertOk()->assertSee($company->name)
        ->assertDontSee('他社秘密')->assertSee('求人を作成')
        ->assertViewHas('counts', fn ($counts) => (int) $counts['published'] === 1 && (int) $counts['draft'] === 3)
        ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 4 && $jobs->every(fn ($job) => $job->company_id === $company->id))
        ->assertSee('未申請')->assertSee('審査中')->assertSee('差戻し')->assertSee('承認済み');
    foreach (array_filter($queries, fn ($sql) => str_contains($sql, 'from `job_postings`')) as $sql) {
        expect($sql)->toContain('`job_postings`.`company_id` = ?');
    }
})->with(['company_owner', 'company_editor']);

test('dashboard empty state and deterministic first membership are safe', function () {
    [$user, $company] = levelOneMember();
    $second = Company::create(['name' => '別所属企業']);
    $user->companies()->attach($second, ['role' => 'company_editor']);
    $second->jobPostings()->create(['title' => '別所属求人']);
    $this->actingAs($user)->get(route('company.dashboard'))->assertOk()->assertSee('求人はまだありません')
        ->assertDontSee('別所属求人')->assertViewHas('company', fn ($value) => $value->is($company));
    $this->get(route('company.jobs.create'))->assertOk();
    $this->assertDatabaseCount('job_postings', 1);
});

test('dashboard paginates without leaking another company and escapes stored content', function () {
    [$user, $company] = levelOneMember();
    for ($i = 0; $i < 21; $i++) {
        $company->jobPostings()->create(['title' => '求人'.$i, 'status' => 'draft']);
    }
    $company->jobPostings()->create(['title' => '<script>alert(1)</script>', 'status' => 'draft']);
    $this->actingAs($user)->get(route('company.dashboard'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertViewHas('jobs', fn ($jobs) => $jobs->count() === 20 && $jobs->total() === 22);
    $this->get(route('company.dashboard', ['page' => 2]))->assertOk()
        ->assertViewHas('jobs', fn ($jobs) => $jobs->count() === 2);
});

test('platform owner without membership is never assigned an arbitrary company', function () {
    $user = User::factory()->create(['system_role' => 'platform_owner']);
    Company::create(['name' => '秘密企業']);
    $this->actingAs($user)->get(route('company.dashboard'))->assertOk()->assertSee('管理対象の企業が設定されていません')->assertDontSee('秘密企業');
    $this->get(route('company.jobs.create'))->assertForbidden();
    $this->post(route('company.jobs.store'), levelOneInput())->assertForbidden();
    $this->assertDatabaseCount('job_postings', 0);
});

test('owner and editor create a draft only on valid first save with fixed server ownership', function (string $role) {
    [$user, $company] = levelOneMember($role);
    $other = Company::create(['name' => '他社']);
    $this->actingAs($user)->get(route('company.jobs.create'))->assertOk()->assertSee('最低限の応募条件')->assertSee('保存してSTEP 1へ進む');
    $this->assertDatabaseCount('job_postings', 0);
    $this->post(route('company.jobs.store'), levelOneInput([
        'company_id' => $other->id, 'status' => 'published', 'review_status' => 'approved',
        'reviewed_by_user_id' => $user->id, 'review_note' => '偽承認', 'published_at' => now()->toDateTimeString(),
    ]))->assertSessionHasNoErrors()->assertRedirect(route('company.jobs.basic.edit', JobPosting::sole()));
    $job = JobPosting::sole();
    expect($job->company_id)->toBe($company->id)->and($job->status)->toBe('draft')
        ->and($job->review_status)->toBe('not_submitted')->and($job->reviewed_by_user_id)->toBeNull()
        ->and($job->review_note)->toBeNull()->and($job->published_at)->toBeNull();
    foreach (levelOneInput() as $key => $value) {
        expect($job->$key)->toBe($value);
    }
    foreach (['job_structured_profiles', 'job_published_profiles', 'job_facts', 'application_routes', 'sources'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->get(route('company.jobs.basic.edit', $job))->assertOk()->assertSee('機械設計の実務経験2年以上');
})->with(['company_owner', 'company_editor']);

test('a title is sufficient for an incomplete draft', function () {
    [$user] = levelOneMember();
    $this->actingAs($user)->post(route('company.jobs.store'), ['title' => '入力途中'])->assertSessionHasNoErrors()->assertRedirect();
    expect(JobPosting::sole()->description)->toBeNull()->and(JobPosting::sole()->salary_min)->toBeNull();
});

test('invalid Level 1 input creates nothing and retains safe old input', function (array $changes, string $field) {
    [$user] = levelOneMember();
    $this->actingAs($user)->from(route('company.jobs.create'))->post(route('company.jobs.store'), levelOneInput($changes))
        ->assertRedirect(route('company.jobs.create'))->assertSessionHasErrors($field)->assertSessionHasInput('description');
    $this->assertDatabaseCount('job_postings', 0);
})->with([
    [['title' => ''], 'title'], [['title' => str_repeat('a', 256)], 'title'],
    [['occupation' => 'invalid'], 'occupation'], [['salary_min' => -1], 'salary_min'],
    [['salary_max' => 400], 'salary_max'], [['salary_min' => '1.5'], 'salary_min'],
    [['source_url' => 'javascript:alert(1)'], 'source_url'], [['source_url' => 'ftp://example.org'], 'source_url'],
    [['application_requirements' => str_repeat('a', 10001)], 'application_requirements'],
]);

test('owners and editors can edit draft fields without changing lifecycle or ownership', function (string $role) {
    [$user, $company] = levelOneMember($role);
    $other = Company::create(['name' => '他社']);
    $job = $company->jobPostings()->create(['title' => '初期', 'status' => 'draft', 'review_status' => 'changes_requested', 'review_note' => '既存審査メモ']);
    $this->actingAs($user)->patch(route('company.jobs.basic.update', $job), levelOneInput([
        'title' => '更新済み', 'company_id' => $other->id, 'status' => 'published', 'review_status' => 'approved', 'review_note' => '改ざん',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('company.jobs.basic.edit', $job));
    expect($job->fresh()->title)->toBe('更新済み')->and($job->fresh()->company_id)->toBe($company->id)
        ->and($job->fresh()->status)->toBe('draft')->and($job->fresh()->review_status)->toBe('changes_requested')
        ->and($job->fresh()->review_note)->toBe('既存審査メモ');
})->with(['company_owner', 'company_editor']);

test('other company and guests cannot read or update basic fields', function () {
    [$user] = levelOneMember();
    $other = Company::create(['name' => '他社']);
    $job = $other->jobPostings()->create(['title' => '秘密', 'status' => 'draft']);
    $this->get(route('company.jobs.basic.edit', $job))->assertRedirect(route('login'));
    $this->patch(route('company.jobs.basic.update', $job), levelOneInput())->assertRedirect(route('login'));
    $this->get(route('company.jobs.create'))->assertRedirect(route('login'));
    $this->post(route('company.jobs.store'), levelOneInput())->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('company.jobs.basic.edit', $job))->assertForbidden();
    $this->patch(route('company.jobs.basic.update', $job), [])->assertForbidden();
    expect($job->fresh()->title)->toBe('秘密');
    expect(Gate::forUser($user)->allows('create', [JobPosting::class, $other]))->toBeFalse();
});

test('platform owner can edit a draft without a company membership', function () {
    $user = User::factory()->create(['system_role' => 'platform_owner']);
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '初期', 'status' => 'draft']);
    $this->actingAs($user)->get(route('company.jobs.basic.edit', $job))->assertOk();
    $this->patch(route('company.jobs.basic.update', $job), levelOneInput())->assertRedirect();
    expect($job->fresh()->title)->toBe('機械設計エンジニア')
        ->and(Gate::forUser($user)->allows('create', [JobPosting::class, $company]))->toBeTrue();
});

test('published records and snapshots are immutable through the basic editor even for admins', function (bool $admin) {
    [$user, $company] = levelOneMember();
    if ($admin) {
        $user->system_role = 'platform_owner';
        $user->save();
    }
    $job = $company->jobPostings()->create(['title' => '公開中', 'status' => 'published', 'review_status' => 'pending_review']);
    $snapshot = $job->publishedProfile()->create(['profile_data' => ['title' => '前回公開内容']]);
    $before = $job->fresh()->getAttributes();
    $this->actingAs($user)->get(route('company.jobs.basic.edit', $job))->assertOk()->assertSee('確認のみ可能')->assertDontSee('type="submit" class="min-h-12', false);
    $this->patch(route('company.jobs.basic.update', $job), levelOneInput())->assertStatus(409);
    expect($job->fresh()->getAttributes())->toEqual($before)->and($snapshot->fresh()->profile_data)->toBe(['title' => '前回公開内容']);
})->with([false, true]);

test('review model casts and nullable reviewer FK preserve jobs after reviewer deletion', function () {
    $user = User::factory()->create(['system_role' => 'platform_owner']);
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '審査', 'review_requested_at' => '2026-09-23 09:00:00',
        'reviewed_at' => '2026-09-23 10:00:00', 'reviewed_by_user_id' => $user->id]);
    expect($job->fresh()->review_requested_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->fresh()->reviewed_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->reviewedBy->is($user))->toBeTrue();
    $user->delete();
    expect($job->fresh())->not->toBeNull()->and($job->fresh()->reviewed_by_user_id)->toBeNull();
    expect(fn () => $job->update(['reviewed_by_user_id' => 999999]))->toThrow(QueryException::class);
});

test('company pages render labelled responsive forms for desktop and mobile clients', function (string $agent) {
    [$user, $company] = levelOneMember();
    $this->actingAs($user)->withHeader('User-Agent', $agent);
    $this->get(route('company.dashboard'))->assertOk()->assertSee('name="viewport"', false)->assertSee('求人はまだありません');
    $response = $this->get(route('company.jobs.create'))->assertOk()->assertSee('name="viewport"', false)
        ->assertSee('sm:grid-cols-2', false)->assertSee('下書き保存')->assertSee('保存してSTEP 1へ進む');
    foreach (array_keys(levelOneInput()) as $field) {
        $response->assertSee('for="'.$field.'"', false)->assertSee('name="'.$field.'"', false);
    }
    $response->assertDontSee('name="status"', false)->assertDontSee('name="review_status"', false);
})->with(['Mozilla/5.0 (X11; Linux x86_64)', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile']);
