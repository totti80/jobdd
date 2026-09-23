<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobToolUsage;
use App\Models\User;
use App\Services\StructuredProfileCompletionService;

function authoringFixture(string $role = 'company_owner'): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Authoring test']);
    $user->companies()->attach($company, ['role' => $role]);

    return [$user, $company->jobPostings()->create(['title' => '設計', 'status' => 'draft', 'review_status' => 'not_submitted'])];
}
function authoringUrl(JobPosting $job, int $step): string
{
    return route('company.jobs.structured.edit', [$job, $step]);
}

test('all structured GETs are read only and owners and editors can save incomplete steps', function ($role) {
    [$user, $job] = authoringFixture($role);
    $this->actingAs($user);
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertOk()->assertSee('STEP '.$step);
    }
    $this->assertDatabaseCount('job_structured_profiles', 0);
    $this->assertDatabaseCount('job_tool_usages', 0);
    $this->assertDatabaseCount('job_typical_day_items', 0);
    foreach (range(1, 5) as $step) {
        $this->patch(authoringUrl($job, $step), [])->assertRedirect(authoringUrl($job, $step));
    }
    $this->assertDatabaseCount('job_structured_profiles', 1);
    expect($job->fresh()->status)->toBe('draft')->and($job->fresh()->review_status)->toBe('not_submitted');
    foreach (['job_published_profiles', 'job_facts', 'sources', 'application_routes'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with(['company_owner', 'company_editor']);

test('all steps enforce guest foreign company and platform access', function () {
    [$owner, $job] = authoringFixture();
    [$foreign] = authoringFixture();
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertRedirect(route('login'));
        $this->patch(authoringUrl($job, $step))->assertRedirect(route('login'));
    }
    $this->actingAs($foreign);
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertForbidden();
        $this->patch(authoringUrl($job, $step))->assertForbidden();
    }
    $this->actingAs(User::factory()->create(['system_role' => 'platform_owner']));
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertOk();
        $this->patch(authoringUrl($job, $step))->assertRedirect();
    }
    $this->get('/company/jobs/'.$job->id.'/structured/step-6')->assertNotFound();
});

test('published jobs remain read only on every step', function () {
    [$user, $job] = authoringFixture();
    $job->update(['status' => 'published', 'review_status' => 'approved']);
    $this->actingAs($user);
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertOk()->assertSee('閲覧のみ');
        $this->patch(authoringUrl($job, $step))->assertStatus(409);
    }
    $this->assertDatabaseCount('job_structured_profiles', 0);
});

test('steps save their own fields and navigation persists before redirect', function () {
    [$user, $job] = authoringFixture();
    $this->actingAs($user)->patch(authoringUrl($job, 1), ['design_target' => '産業機械', 'design_phases' => ['concept', 'analysis'], 'initial_assignment' => '製図', 'navigation' => 'next', 'difficult_points' => 'injected', 'status' => 'published'])->assertRedirect(authoringUrl($job, 2));
    $this->patch(authoringUrl($job, 4), ['difficult_points' => '仕様調整', 'navigation' => 'back'])->assertRedirect(authoringUrl($job, 3));
    expect($job->fresh()->structuredProfile->design_target)->toBe('産業機械')->and($job->fresh()->structuredProfile->difficult_points)->toBe('仕様調整');
    $this->patch(authoringUrl($job, 1), ['design_target' => '変更', 'navigation' => 'back'])->assertRedirect(route('company.jobs.basic.edit', $job));
    expect($job->fresh()->structuredProfile->design_phases)->toBe([])->and($job->fresh()->structuredProfile->difficult_points)->toBe('仕様調整');
    $this->patch(authoringUrl($job, 5), ['representative_project' => ['what_made' => '装置', 'phases' => ['testing'], 'duration' => '半年', 'team' => '3名', 'difficult_point' => '精度'], 'navigation' => 'next'])->assertRedirect(authoringUrl($job, 5))->assertSessionHas('status', 'STEP 5を保存しました。公開前確認は準備中です。');
    expect($job->fresh()->structuredProfile->representative_project['phases'])->toBe(['testing']);
    $this->post(route('logout'));
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    $this->get(authoringUrl($job, 1))->assertSee('変更');
    $this->get(authoringUrl($job, 5))->assertSee('装置');
});

test('tool and day replacement is scoped ordered repeatable and clearable', function () {
    [$user, $job] = authoringFixture();
    [, $other] = authoringFixture();
    $other->toolUsages()->create(['tool_name' => 'keep']);
    $this->actingAs($user);
    $tools = ['tools' => [4 => ['tool_key' => 'autocad', 'tool_name' => 'AutoCAD', 'usage_context' => 'primary', 'experience_expectation' => 'required'], 9 => ['tool_name' => '自社ツール', 'usage_context' => 'undecided', 'experience_expectation' => 'not_required'], 10 => ['tool_name' => '']]];
    foreach (range(1, 2) as $_) {
        $this->patch(authoringUrl($job, 2), $tools)->assertRedirect();
    }
    expect($job->toolUsages()->orderBy('sort_order')->pluck('tool_name')->all())->toBe(['AutoCAD', '自社ツール']);
    expect($job->toolUsages()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1]);
    $days = ['typical_day' => [['time_label' => '出社後', 'activity' => '打合せ'], ['time_label' => '午後', 'activity' => '設計']]];
    foreach (range(1, 2) as $_) {
        $this->patch(authoringUrl($job, 5), $days)->assertRedirect();
    }
    expect($job->typicalDayItems()->count())->toBe(2);
    $this->patch(authoringUrl($job, 2), ['tools' => [['tool_name' => '修正']]])->assertRedirect();
    expect($job->toolUsages()->sole()->tool_name)->toBe('修正')->and($other->toolUsages()->sole()->tool_name)->toBe('keep');
    $this->patch(authoringUrl($job, 2), [])->assertRedirect();
    $this->patch(authoringUrl($job, 5), [])->assertRedirect();
    expect($job->toolUsages()->count())->toBe(0)->and($job->typicalDayItems()->count())->toBe(0);
});

test('invalid internal keys types lengths and injected row ids never save', function ($step, $data, $field) {
    [$user, $job] = authoringFixture();
    $this->actingAs($user)->from(authoringUrl($job, $step))->patch(authoringUrl($job, $step), $data)->assertSessionHasErrors($field);
    $this->get(authoringUrl($job, $step))->assertOk();
    $this->assertDatabaseCount('job_structured_profiles', 0);
})->with([
    [1, ['design_phases' => ['構想']], 'design_phases.0'],
    [1, ['design_target' => ['invalid']], 'design_target'],
    [1, ['design_target' => str_repeat('a', 10001)], 'design_target'],
    [2, ['tools' => [['tool_key' => 'unknown']]], 'tools.0.tool_key'],
    [2, ['tools' => [['usage_context' => 'unknown']]], 'tools.0.usage_context'],
    [2, ['tools' => [['experience_expectation' => 'unknown']]], 'tools.0.experience_expectation'],
    [2, ['tools' => [['id' => 123, 'tool_name' => 'bad']]], 'tools.0'],
    [3, ['collaborators' => ['unknown']], 'collaborators.0'],
    [3, ['site_relation_frequency' => '毎日'], 'site_relation_frequency'],
    [5, ['representative_project' => ['phases' => ['unknown']]], 'representative_project.phases.0'],
    [5, ['typical_day' => [['time_label' => str_repeat('a', 256)]]], 'typical_day.0.time_label'],
]);

test('row failure rolls back profile and replacement together', function () {
    [$user, $job] = authoringFixture();
    $job->structuredProfile()->create(['required_experience' => 'old']);
    $job->toolUsages()->create(['tool_name' => 'old tool']);
    JobToolUsage::creating(function ($tool) {
        if ($tool->tool_name === 'fail') {
            throw new RuntimeException('simulated row failure');
        }
    });
    try {
        $this->actingAs($user)->patch(authoringUrl($job, 2), ['required_experience' => 'new', 'tools' => [['tool_name' => 'fail']]])->assertStatus(500);
        expect($job->fresh()->structuredProfile->required_experience)->toBe('old')->and($job->toolUsages()->sole()->tool_name)->toBe('old tool');
    } finally {
        JobToolUsage::flushEventListeners();
    }
});

test('completion recalculates all 21 criteria without persistence', function () {
    [$user, $job] = authoringFixture();
    $service = app(StructuredProfileCompletionService::class);
    expect($service->calculate($job)['completed'])->toBe(1);
    $job->update(['occupation' => '機械設計', 'region' => '東京', 'salary_min' => 400, 'employment_type' => '正社員', 'description' => '設計', 'source_url' => 'https://example.com', 'application_requirements' => '経験']);
    $job->structuredProfile()->create(['design_target' => '機械', 'design_phases' => ['concept'], 'initial_assignment' => '設計', 'collaborators' => ['design_team'], 'work_style' => 'チーム', 'difficult_points' => '調整', 'customer_contact_frequency' => 'undecided', 'customer_contact_note' => '調整中', 'manufacturing_relation_frequency' => 'rarely', 'manufacturing_relation_note' => '製造部門経由', 'site_relation_frequency' => 'rarely', 'site_relation_note' => '現地支援なし']);
    $job->toolUsages()->create(['tool_name' => 'CAD', 'usage_context' => 'primary', 'experience_expectation' => 'not_required']);
    $job->typicalDayItems()->create(['time_label' => '午前', 'activity' => '設計']);
    expect($service->calculate($job->fresh()))->toBe(['completed' => 21, 'total' => 21, 'percentage' => 100]);
    $job->toolUsages()->create(['tool_name' => '未定']);
    expect($service->calculate($job->fresh())['completed'])->toBe(19);
});

test('level one next saves before entering structured authoring', function () {
    [$user, $job] = authoringFixture();
    $this->actingAs($user)->patch(route('company.jobs.basic.update', $job), ['title' => '更新', 'navigation' => 'next'])->assertRedirect(authoringUrl($job, 1));
    expect($job->fresh()->title)->toBe('更新');
    $this->post(route('company.jobs.store'), ['title' => '新規', 'navigation' => 'next'])->assertRedirect(authoringUrl(JobPosting::latest('id')->first(), 1));
});

test('validation redisplays unchecked groups without restoring saved selections and escapes text', function () {
    [$user, $job] = authoringFixture();
    $job->structuredProfile()->create(['design_phases' => ['concept'], 'design_target' => '<script>alert(1)</script>']);
    $this->actingAs($user)->get(authoringUrl($job, 1))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->from(authoringUrl($job, 1))->patch(authoringUrl($job, 1), ['design_target' => str_repeat('x', 10001)])->assertSessionHasErrors('design_target');
    $response = $this->get(authoringUrl($job, 1))->assertOk();
    expect($response->getContent())->not->toMatch('/value="concept"\\s+checked/');
});

test('non members and revoked members cannot access structured authoring', function () {
    [$user, $job] = authoringFixture();
    $user->companies()->detach();
    $this->actingAs($user);
    foreach (range(1, 5) as $step) {
        $this->get(authoringUrl($job, $step))->assertForbidden();
        $this->patch(authoringUrl($job, $step), [])->assertForbidden();
    }
    $this->assertDatabaseCount('job_structured_profiles', 0);
});
