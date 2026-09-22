<?php

use App\Models\Agency;
use App\Models\AgencyFact;
use App\Models\Company;
use App\Models\Source;
use App\Models\User;

beforeEach(function () {
    $agency = Agency::create(['name' => 'テストAgent']);
    $source = Source::create(['source_type' => 'official_site', 'url' => 'https://example.test']);
    $this->fact = AgencyFact::create([
        'agency_id' => $agency->id, 'source_id' => $source->id,
        'fact_type' => 'service', 'fact_key' => 'support', 'fact_value' => '相談支援',
        'verification_status' => 'pending',
    ]);
});

test('platform owner can view and operate existing admin actions', function () {
    $user = User::factory()->create(['system_role' => 'platform_owner']);
    $this->actingAs($user)->get(route('admin.agency-facts.index'))->assertOk()->assertSee('相談支援');
    $this->patch(route('admin.agency-facts.verify', $this->fact))->assertRedirect(route('admin.agency-facts.index'));
    expect($this->fact->fresh()->verification_status)->toBe('verified');
    $this->patch(route('admin.agency-facts.reject', $this->fact))->assertRedirect(route('admin.agency-facts.index'));
    expect($this->fact->fresh()->verification_status)->toBe('rejected');
});

test('non platform users are forbidden from every existing admin action', function (?string $role) {
    $user = User::factory()->unverified()->create();
    if ($role !== null) {
        $company = Company::create(['name' => '企業']);
        $user->companies()->attach($company, ['role' => $role]);
    }
    $this->actingAs($user)->get(route('admin.agency-facts.index'))->assertForbidden();
    foreach (['verify', 'reject'] as $action) {
        $this->patch(route('admin.agency-facts.'.$action, $this->fact))->assertForbidden();
    }
    expect($this->fact->fresh()->verification_status)->toBe('pending');
})->with([null, 'company_owner', 'company_editor']);

test('guests are redirected from all existing admin routes without modifying facts', function () {
    $this->get(route('admin.agency-facts.index'))->assertRedirect(route('login'));
    foreach (['verify', 'reject'] as $action) {
        $this->patch(route('admin.agency-facts.'.$action, $this->fact))->assertRedirect(route('login'));
    }
    expect($this->fact->fresh()->verification_status)->toBe('pending');
});
