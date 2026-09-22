<?php

use App\Http\Middleware\EnsureCompanyMember;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Test-only bound routes exercise HTTP authorization without adding Batch 3 CRUD.
    Route::middleware(['web', 'auth', EnsureCompanyMember::class])->group(function () {
        Route::get('/company/ownership-test/companies/{company}', fn (Company $company) => response()->noContent())
            ->can('view', 'company');
        Route::get('/company/ownership-test/jobs/{jobPosting}', fn (JobPosting $jobPosting) => response()->noContent())
            ->can('view', 'jobPosting');
    });
});

test('company members can only authorize their own companies and jobs', function (string $role) {
    $user = User::factory()->create();
    $own = Company::create(['name' => '自社']);
    $other = Company::create(['name' => '他社']);
    $user->companies()->attach($own, ['role' => $role]);
    $ownJob = $own->jobPostings()->create(['title' => '自社求人']);
    $otherJob = $other->jobPostings()->create(['title' => '他社求人']);
    foreach (['view', 'update'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $own))->toBeTrue()
            ->and(Gate::forUser($user)->allows($ability, $other))->toBeFalse();
    }
    foreach (['view', 'update', 'preview', 'publish'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $ownJob))->toBeTrue()
            ->and(Gate::forUser($user)->allows($ability, $otherJob))->toBeFalse();
    }
    $this->actingAs($user)->get('/company/ownership-test/companies/'.$own->id)->assertNoContent();
    $this->get('/company/ownership-test/companies/'.$other->id)->assertForbidden();
    $this->get('/company/ownership-test/jobs/'.$ownJob->id)->assertNoContent();
    $this->get('/company/ownership-test/jobs/'.$otherJob->id)->assertForbidden();
    $this->get(route('company.dashboard'))->assertOk();

    // A previously loaded relation must not keep granting access after revocation.
    $user->load('companies');
    $user->companies()->detach($own);
    expect(Gate::forUser($user)->allows('update', $ownJob))->toBeFalse();
    $this->get(route('company.dashboard'))->assertForbidden();
})->with(['company_owner', 'company_editor']);

test('platform owner can authorize every company and job without memberships', function () {
    $user = User::factory()->create(['system_role' => 'platform_owner']);
    foreach (['企業A', '企業B'] as $name) {
        $company = Company::create(['name' => $name]);
        $job = $company->jobPostings()->create(['title' => '求人']);
        foreach (['view', 'update'] as $ability) {
            expect(Gate::forUser($user)->allows($ability, $company))->toBeTrue();
        }
        foreach (['view', 'update', 'preview', 'publish'] as $ability) {
            expect(Gate::forUser($user)->allows($ability, $job))->toBeTrue();
        }
        $this->actingAs($user)->get('/company/ownership-test/companies/'.$company->id)->assertNoContent();
        $this->get('/company/ownership-test/jobs/'.$job->id)->assertNoContent();
    }
    $this->get(route('company.dashboard'))->assertOk();
    $this->assertDatabaseCount('company_user', 0);
});

test('ordinary users and unknown membership roles have no company access', function (?string $role) {
    $user = User::factory()->create();
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '求人']);
    if ($role !== null) {
        $user->companies()->attach($company, ['role' => $role]);
    }
    expect(Gate::forUser($user)->allows('view', $company))->toBeFalse()
        ->and(Gate::forUser($user)->allows('publish', $job))->toBeFalse();
    $this->actingAs($user)->get(route('company.dashboard'))->assertForbidden();
    $this->get('/company/ownership-test/companies/'.$company->id)->assertForbidden();
    $this->get('/company/ownership-test/jobs/'.$job->id)->assertForbidden();
})->with([null, 'unknown']);

test('guests cannot access protected company routes or policies', function () {
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '求人']);
    $this->get(route('company.dashboard'))->assertRedirect(route('login'));
    $this->get('/company/ownership-test/companies/'.$company->id)->assertRedirect(route('login'));
    $this->get('/company/ownership-test/jobs/'.$job->id)->assertRedirect(route('login'));
    expect(Gate::allows('view', $company))->toBeFalse()->and(Gate::allows('view', $job))->toBeFalse();
});
