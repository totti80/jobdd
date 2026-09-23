<?php

use App\Http\Controllers\AgencyFactReviewController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompanyJobBasicController;
use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\JobDecisionController;
use App\Http\Controllers\JobSearchController;
use App\Http\Controllers\RouteComparisonController;
use App\Http\Controllers\UserQueryController;
use App\Http\Middleware\EnsureCompanyMember;
use App\Http\Middleware\EnsurePlatformOwner;
use App\Models\InteractionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsurePlatformOwner::class, 'verified'])->group(function () {
    Route::patch('/agency-facts/{agencyFact}/verify', [AgencyFactReviewController::class, 'verify'])
        ->name('agency-facts.verify');

    Route::patch('/agency-facts/{agencyFact}/reject', [AgencyFactReviewController::class, 'reject'])
        ->name('agency-facts.reject');

    Route::get('/agency-facts', [AgencyFactReviewController::class, 'index'])
        ->name('agency-facts.index');
});

Route::prefix('company')->name('company.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/register', [CompanyRegistrationController::class, 'create'])->name('register');
        Route::post('/register', [CompanyRegistrationController::class, 'store'])
            ->middleware('throttle:6,1')->name('register.store');
    });

    Route::middleware(['auth', EnsureCompanyMember::class])->group(function () {
        Route::get('/dashboard', CompanyDashboardController::class)->name('dashboard');
        Route::get('/jobs/create', [CompanyJobBasicController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [CompanyJobBasicController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{jobPosting}/basic', [CompanyJobBasicController::class, 'edit'])->name('jobs.basic.edit');
        Route::patch('/jobs/{jobPosting}/basic', [CompanyJobBasicController::class, 'update'])->name('jobs.basic.update');
    });
});

Route::post('/interaction/contact-clicked', function (Request $request) {
    $validated = $request->validate([
        'user_query_id' => ['nullable', 'integer', 'exists:user_queries,id'],
        'application_route_id' => ['required', 'integer', 'exists:application_routes,id'],
    ]);

    try {
        InteractionLog::create([
            'user_query_id' => $validated['user_query_id'] ?? null,
            'event_type' => 'contact_clicked',
            'target_type' => 'application_route',
            'target_id' => $validated['application_route_id'],
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    } catch (Throwable) {
        // Logging must never block an external application link.
    }

    return response()->noContent();
})->name('interaction.contact-clicked');

Route::post('/interaction/route-selected', function (Request $request) {
    $validated = $request->validate([
        'user_query_id' => ['nullable', 'integer', 'exists:user_queries,id'],
        'application_route_id' => ['required', 'integer', 'exists:application_routes,id'],
    ]);

    InteractionLog::create([
        'user_query_id' => $validated['user_query_id'] ?? null,
        'event_type' => 'route_selected',
        'target_type' => 'application_route',
        'target_id' => $validated['application_route_id'],
        'metadata' => null,
        'occurred_at' => now(),
    ]);

    return response()->noContent();
})->name('interaction.route-selected');

Route::post('/interaction/evidence-opened', function (Request $request) {
    $validated = $request->validate([
        'user_query_id' => ['nullable', 'integer', 'exists:user_queries,id'],
        'agency_id' => ['required', 'integer', 'exists:agencies,id'],
    ]);

    InteractionLog::create([
        'user_query_id' => $validated['user_query_id'] ?? null,
        'event_type' => 'evidence_opened',
        'target_type' => 'agency',
        'target_id' => $validated['agency_id'],
        'metadata' => null,
        'occurred_at' => now(),
    ]);

    return response()->noContent();
})->name('interaction.evidence-opened');

Route::get('/routes/{jobPosting}', [RouteComparisonController::class, 'show'])
    ->name('routes.show');

Route::get('/routes/{jobPosting}/action', [RouteComparisonController::class, 'action'])
    ->name('routes.action');

Route::get('/jobs/start', [JobSearchController::class, 'create'])->name('jobs.start');
Route::post('/jobs/start', [JobSearchController::class, 'store'])->name('jobs.store');

Route::get('/query/{userQuery:public_id}/agencies', [JobDecisionController::class, 'agencies'])
    ->name('query.agencies');

Route::get('/query/{userQuery:public_id}/jobs/compare', [JobDecisionController::class, 'compare'])
    ->name('query.jobs.compare');
Route::get('/query/{userQuery:public_id}/jobs/{job}', [JobDecisionController::class, 'show'])
    ->whereNumber('job')->name('query.jobs.show');

Route::get('/query/{userQuery:public_id}/jobs', JobDecisionController::class)
    ->name('query.jobs');

Route::get('/query', [UserQueryController::class, 'create'])->name('query.create');
Route::post('/query', [UserQueryController::class, 'store'])->name('query.store');

Route::get('/results/{userQuery:public_id}', [UserQueryController::class, 'results'])
    ->name('query.results');

Route::get('/', [UserQueryController::class, 'create'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [IconController::class, 'index'])
        ->name('dashboard');

    Route::get('/icon/{id}/edit', [IconController::class, 'edit'])
        ->name('icon.edit');

    Route::get('/icon/create', [IconController::class, 'create'])
        ->name('icon.create');

    Route::post('/icon/store', [IconController::class, 'store'])
        ->name('icon.store');

    Route::delete('/icon/{id}', [IconController::class, 'destroy'])
        ->name('icon.destroy');

    Route::patch('/icon/{id}', [IconController::class, 'update'])
        ->name('icon.update');
});

require __DIR__.'/settings.php';
