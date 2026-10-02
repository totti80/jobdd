<?php

use App\Models\Company;
use App\Models\User;

test('login preserves system role destinations and routes company members to their dashboard', function (string $systemRole, ?string $companyRole, string $destination) {
    $user = User::factory()->create(['system_role' => $systemRole]);
    if ($companyRole !== null) {
        $user->companies()->attach(Company::create(['name' => '企業']), ['role' => $companyRole]);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect($destination);
    $this->assertAuthenticatedAs($user);
    $this->get($destination)->assertOk();
})->with([
    ['user', 'company_owner', '/company/dashboard'],
    ['user', 'company_editor', '/company/dashboard'],
    ['user', null, '/dashboard'],
    ['user', 'unknown', '/dashboard'],
    ['platform_owner', null, '/admin/job-reviews'],
    ['platform_owner', 'company_owner', '/admin/job-reviews'],
    ['platform_owner', 'company_editor', '/admin/job-reviews'],
]);

test('login keeps intended URLs and their authorization checks', function (string $role, string $target) {
    $user = User::factory()->create();
    $company = Company::create(['name' => '自社']);
    $user->companies()->attach($company, ['role' => $role]);
    $ownJob = $company->jobPostings()->create(['title' => '自社求人']);
    $otherJob = Company::create(['name' => '他社'])->jobPostings()->create(['title' => '他社求人']);
    $url = match ($target) {
        'own' => route('company.jobs.basic.edit', $ownJob),
        'other' => route('company.jobs.basic.edit', $otherJob),
        'admin' => route('admin.job-reviews.index'),
        default => route('dashboard'),
    };

    $this->get($url)->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($url)->assertSessionMissing('url.intended');
    if (in_array($target, ['other', 'admin'])) {
        $this->get($url)->assertForbidden();
    } else {
        $this->get($url)->assertOk();
    }
})->with(['company_owner', 'company_editor'])->with(['own', 'other', 'admin', 'dashboard']);

test('two factor login uses role destinations and preserves intended URLs', function (string $systemRole, ?string $role, string $destination, ?string $intended) {
    $user = User::factory()->withTwoFactor()->create(['system_role' => $systemRole]);
    if ($role !== null) {
        $user->companies()->attach(Company::create(['name' => '企業']), ['role' => $role]);
    }
    if ($intended !== null) {
        $this->withSession(['url.intended' => $intended]);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect($intended ?? $destination);
    $this->assertAuthenticatedAs($user);
})->with([
    ['user', 'company_owner', '/company/dashboard'],
    ['user', 'company_editor', '/company/dashboard'],
    ['user', null, '/dashboard'],
    ['platform_owner', null, '/admin/job-reviews'],
    ['platform_owner', 'company_owner', '/admin/job-reviews'],
])->with([null, '/dashboard']);

test('company login preserves the JSON response', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::create(['name' => '企業']), ['role' => 'company_owner']);

    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertExactJson(['two_factor' => false]);
    $this->assertAuthenticatedAs($user);
});

test('non company users retain their intended destination', function (string $systemRole, string $destination) {
    $user = User::factory()->create(['system_role' => $systemRole]);

    $this->get($destination)->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($destination)->assertSessionMissing('url.intended');
    $this->get($destination)->assertOk();
})->with([
    ['user', '/settings/profile'],
    ['platform_owner', '/admin/job-reviews'],
    ['platform_owner', '/dashboard'],
]);
