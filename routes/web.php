<?php

use App\Http\Controllers\IconController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserQueryController;
use App\Http\Controllers\RouteComparisonController;
use App\Models\InteractionLog;
use Illuminate\Http\Request;
use App\Http\Controllers\AgencyFactReviewController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('/admin/agency-facts/{agencyFact}/verify', [AgencyFactReviewController::class, 'verify'])
        ->name('admin.agency-facts.verify');

    Route::patch('/admin/agency-facts/{agencyFact}/reject', [AgencyFactReviewController::class, 'reject'])
        ->name('admin.agency-facts.reject');

    Route::get('/admin/agency-facts', [AgencyFactReviewController::class, 'index'])
        ->name('admin.agency-facts.index');
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
    } catch (\Throwable) {
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

Route::get('/query/{userQuery:public_id}/jobs', \App\Http\Controllers\JobDecisionController::class)
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

require __DIR__ . '/settings.php';
