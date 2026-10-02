<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('company members can view their independent account information and scripts', function (string $role) {
    $user = User::factory()->create(['name' => '担当者表示名', 'email' => 'member@example.test']);
    $company = Company::create(['name' => '所属会社名']);
    $user->companies()->attach($company, ['role' => $role]);
    $this->actingAs($user)->get(route('company.account-settings'))->assertOk()
        ->assertSee('所属会社名')->assertSee('担当者表示名')->assertSee('member@example.test')->assertSee($role)
        ->assertSee('wire:submit="updatePassword"', false)->assertSee('livewire', false)->assertSee('flux', false)
        ->assertSee('name="_token"', false)->assertDontSee('wire:model="email"', false)
        ->assertDontSee('wire:model="name"', false)->assertDontSee('Delete account');
})->with(['company_owner', 'company_editor']);

test('password update changes only the authenticated user password', function (string $role) {
    $user = User::factory()->create(['password' => 'Old-password-123!']);
    $other = User::factory()->create();
    $company = Company::create(['name' => '会社']);
    $user->companies()->attach($company, ['role' => $role]);
    $company->jobPostings()->create(['title' => '設計', 'status' => 'draft']);
    $snapshot = fn () => collect(['companies', 'company_user', 'job_postings'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    $before = $snapshot();
    $userBefore = $user->fresh()->getAttributes();
    $otherBefore = $other->fresh()->getAttributes();
    $this->actingAs($user);
    Livewire::test('pages::company.account-settings')
        ->set('current_password', 'Old-password-123!')->set('password', 'New-password-456!')
        ->set('password_confirmation', 'New-password-456!')->call('updatePassword')
        ->assertHasNoErrors()->assertSet('current_password', '')->assertSet('password', '')->assertSet('password_confirmation', '');
    expect(Hash::check('New-password-456!', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('Old-password-123!', $user->fresh()->password))->toBeFalse();
    expect($snapshot())->toBe($before);
    expect($other->fresh()->getAttributes())->toBe($otherBefore);
    expect(collect($user->fresh()->getAttributes())->except(['password', 'updated_at'])->all())
        ->toBe(collect($userBefore)->except(['password', 'updated_at'])->all());
})->with(['company_owner', 'company_editor']);

test('account password update rejects invalid credentials and resets sensitive fields', function (string $current, string $password, string $confirmation, string $field) {
    $user = User::factory()->create(['password' => 'Old-password-123!']);
    $company = Company::create(['name' => '会社']);
    $user->companies()->attach($company, ['role' => 'company_owner']);
    $before = $user->password;
    $this->actingAs($user);
    Livewire::test('pages::company.account-settings')->set('current_password', $current)->set('password', $password)
        ->set('password_confirmation', $confirmation)->call('updatePassword')->assertHasErrors([$field])
        ->assertSet('current_password', '')->assertSet('password', '')->assertSet('password_confirmation', '');
    expect($user->fresh()->password)->toBe($before);
})->with([
    ['wrong', 'New-password-456!', 'New-password-456!', 'current_password'],
    ['', 'New-password-456!', 'New-password-456!', 'current_password'],
    ['Old-password-123!', 'New-password-456!', 'different', 'password'],
    ['Old-password-123!', 'a', 'a', 'password'],
    ['Old-password-123!', '', '', 'password'],
]);

test('account settings rejects guests and users without company membership', function () {
    $this->get(route('company.account-settings'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('company.account-settings'))->assertForbidden();
    Livewire::test('pages::company.account-settings')->call('updatePassword')->assertForbidden();
});

test('revoked company membership cannot update a mounted account component', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => '会社']);
    $user->companies()->attach($company, ['role' => 'company_editor']);
    $this->actingAs($user);
    $component = Livewire::test('pages::company.account-settings');
    $user->companies()->detach($company);
    $component->call('updatePassword')->assertForbidden();
});
