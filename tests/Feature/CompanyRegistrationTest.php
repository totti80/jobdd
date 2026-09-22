<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

function companyRegistrationInput(array $overrides = []): array
{
    return array_replace([
        'account_name' => 'maply-design',
        'email' => 'company@example.test',
        'password' => 'Password-12345!',
        'password_confirmation' => 'Password-12345!',
    ], $overrides);
}

test('company registration renders a single account name field and CSRF token', function () {
    $this->get(route('company.register'))->assertOk()
        ->assertSee('会社名またはユーザーID')->assertSee('name="account_name"', false)
        ->assertSee('name="_token"', false)->assertSee('name="password_confirmation"', false)
        ->assertDontSee('name="company_name"', false)->assertDontSee('name="user_id"', false);
});

test('company registration creates user company and ownership then authenticates and redirects', function () {
    Event::fake([Registered::class]);
    $this->withSession(['url.intended' => '/admin/agency-facts']);
    $oldSessionId = session()->getId();
    $oldToken = session()->token();
    $this->post(route('company.register.store'), companyRegistrationInput([
        'email' => 'COMPANY@EXAMPLE.TEST',
        'system_role' => 'platform_owner', 'role' => 'company_editor', 'company_id' => 999,
    ]))->assertSessionHasNoErrors()->assertRedirect(route('company.dashboard'));

    $user = User::sole();
    $company = Company::sole();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('maply-design')->and($company->name)->toBe('maply-design')
        ->and($user->email)->toBe('company@example.test')->and($user->system_role)->toBe('user')
        ->and(Hash::check('Password-12345!', $user->password))->toBeTrue()
        ->and($user->companies->sole()->id)->toBe($company->id)
        ->and(session()->getId())->not->toBe($oldSessionId)
        ->and(session()->token())->not->toBe($oldToken);
    $this->assertDatabaseHas('company_user', ['user_id' => $user->id, 'company_id' => $company->id, 'role' => 'company_owner']);
    $this->assertDatabaseCount('company_user', 1);
    $this->assertDatabaseCount('job_postings', 0);
    Event::assertDispatched(Registered::class, fn ($event) => $event->user->is($user));
    $this->get(route('company.dashboard'))->assertOk()->assertSee('企業マイページ');
});

test('company registration rejects invalid input without leaving records', function (array $overrides, string $field) {
    $this->from(route('company.register'))->post(route('company.register.store'), companyRegistrationInput($overrides))
        ->assertRedirect(route('company.register'))->assertSessionHasErrors($field);
    foreach (['users', 'companies', 'company_user'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->assertGuest();
})->with([
    [['account_name' => ''], 'account_name'],
    [['account_name' => str_repeat('a', 256)], 'account_name'],
    [['email' => 'not-an-email'], 'email'],
    [['email' => ''], 'email'],
    [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    [['password_confirmation' => 'different'], 'password'],
    [['password' => '', 'password_confirmation' => ''], 'password'],
]);

test('duplicate email is rejected and cannot attach to an existing account', function () {
    $existing = User::factory()->create(['email' => 'company@example.test']);
    $this->post(route('company.register.store'), companyRegistrationInput(['email' => 'COMPANY@EXAMPLE.TEST']))
        ->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('companies', 0);
    $this->assertDatabaseCount('company_user', 0);
    expect($existing->fresh()->name)->not->toBe('maply-design');
    $this->assertGuest();
});

test('matching company names create independent companies and never claim existing data', function () {
    $existing = Company::create(['name' => 'maply-design']);
    $this->post(route('company.register.store'), companyRegistrationInput())->assertRedirect(route('company.dashboard'));
    expect(User::sole()->companies->sole()->id)->not->toBe($existing->id);
    $this->assertDatabaseCount('companies', 2);
    expect($existing->users()->count())->toBe(0);
});

test('company registration rolls back every record on a company or pivot failure', function (string $stage) {
    Event::fake([Registered::class]);
    $this->withoutExceptionHandling();
    if ($stage === 'company') {
        Event::listen('eloquent.created: '.Company::class, function () {
            throw new RuntimeException('Simulated company failure');
        });
    } else {
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `company_user`')) {
                throw new RuntimeException('Simulated pivot failure');
            }
        });
    }
    expect(fn () => $this->post(route('company.register.store'), companyRegistrationInput()))->toThrow(RuntimeException::class);
    foreach (['users', 'companies', 'company_user'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Event::assertNotDispatched(Registered::class);
    $this->assertGuest();
})->with(['company', 'pivot']);

test('authenticated users cannot register another company through the guest flow', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('company.register'))->assertRedirect();
    $this->post(route('company.register.store'), companyRegistrationInput())->assertRedirect();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('companies', 0);
});

test('company registration is rate limited', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post(route('company.register.store'), [])->assertSessionHasErrors();
    }
    $this->post(route('company.register.store'), [])->assertStatus(429);
});

test('general Fortify registration stays separate from company registration', function () {
    $this->post(route('register.store'), [
        'name' => 'General user', 'email' => 'general@example.test',
        'password' => 'Password-12345!', 'password_confirmation' => 'Password-12345!',
    ])->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseCount('companies', 0);
    $this->assertDatabaseCount('company_user', 0);
    expect(User::sole()->system_role)->toBe('user');
});

test('company accounts can logout and login again through Fortify', function () {
    $this->post(route('company.register.store'), companyRegistrationInput())->assertRedirect(route('company.dashboard'));
    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
    $this->post(route('login.store'), ['email' => 'company@example.test', 'password' => 'Password-12345!'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs(User::sole());
    $this->get(route('company.dashboard'))->assertOk();
});
