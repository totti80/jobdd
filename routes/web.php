<?php

use App\Http\Controllers\AgencyFactReviewController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CompanyJobBasicController;
use App\Http\Controllers\CompanyJobLifecycleController;
use App\Http\Controllers\CompanyJobPreviewController;
use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Controllers\CompanyStructuredJobController;
use App\Http\Controllers\ContactInquiryController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\JobDecisionController;
use App\Http\Controllers\JobReviewController;
use App\Http\Controllers\JobSearchController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RouteComparisonController;
use App\Http\Controllers\UserQueryController;
use App\Http\Controllers\UserQueryPreferenceController;
use App\Http\Middleware\EnsureCompanyMember;
use App\Http\Middleware\EnsurePlatformOwner;
use App\Http\Requests\ApplicationRouteInteractionRequest;
use App\Models\InteractionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsurePlatformOwner::class, 'verified'])->group(function () {
    Route::get('/job-reviews', [JobReviewController::class, 'index'])->name('job-reviews.index');
    Route::get('/job-reviews/{jobPosting}', [JobReviewController::class, 'show'])->name('job-reviews.show');
    Route::post('/job-reviews/{jobPosting}/approve', [JobReviewController::class, 'approve'])->name('job-reviews.approve');
    Route::post('/job-reviews/{jobPosting}/changes-requested', [JobReviewController::class, 'changesRequested'])->name('job-reviews.changes-requested');
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
        Route::get('/jobs/{jobPosting}/structured/step-{step}', [CompanyStructuredJobController::class, 'edit'])->where('step', '[1-5]')->name('jobs.structured.edit');
        Route::patch('/jobs/{jobPosting}/structured/step-{step}', [CompanyStructuredJobController::class, 'update'])->where('step', '[1-5]')->name('jobs.structured.update');
        Route::get('/jobs/{jobPosting}/preview', [CompanyJobPreviewController::class, 'show'])->name('jobs.preview');
        Route::post('/jobs/{jobPosting}/review-request', [CompanyJobPreviewController::class, 'requestReview'])->name('jobs.review-request');
        Route::post('/jobs/{jobPosting}/pause', [CompanyJobLifecycleController::class, 'pause'])->name('jobs.pause');
        Route::post('/jobs/{jobPosting}/resume', [CompanyJobLifecycleController::class, 'resume'])->name('jobs.resume');
        Route::get('/dashboard', CompanyDashboardController::class)->name('dashboard');
        Route::get('/jobs/create', [CompanyJobBasicController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [CompanyJobBasicController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{jobPosting}/basic', [CompanyJobBasicController::class, 'edit'])->name('jobs.basic.edit');
        Route::patch('/jobs/{jobPosting}/basic', [CompanyJobBasicController::class, 'update'])->name('jobs.basic.update');
    });
});

Route::post('/interaction/contact-clicked', function (ApplicationRouteInteractionRequest $request) {
    $validated = $request->validated();

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

Route::post('/interaction/route-selected', function (ApplicationRouteInteractionRequest $request) {
    $validated = $request->validated();

    try {
        InteractionLog::create([
            'user_query_id' => $validated['user_query_id'] ?? null,
            'event_type' => 'route_selected',
            'target_type' => 'application_route',
            'target_id' => $validated['application_route_id'],
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    } catch (Throwable) {
        // A logging failure must not block route selection.
    }

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

Route::get('/query/{userQuery:public_id}/preferences', [UserQueryPreferenceController::class, 'edit'])->name('query.preferences.edit');
Route::patch('/query/{userQuery:public_id}/preferences', [UserQueryPreferenceController::class, 'update'])->name('query.preferences.update');

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

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/preferences', [PublicPageController::class, 'preferences'])->name('public.preferences');
Route::get('/compare', [PublicPageController::class, 'compare'])->name('public.compare');
Route::get('/new-jobs/{job}', [PublicPageController::class, 'job'])->whereNumber('job')->name('public.job');
Route::get('/for-companies', [PublicPageController::class, 'company'])->name('public.company');
Route::view('/resources', 'public.resources')->name('public.resources');
Route::view('/helpful/cad-experience', 'public.helpful.cad-experience')->name('public.resources.cad-experience');
Route::view('/helpful/job-change-preparation', 'public.helpful.job-change-preparation')->name('public.resources.job-change-preparation');
Route::view('/helpful/how-to-read-job-postings', 'public.helpful.how-to-read-job-postings')->name('public.resources.how-to-read-job-postings');
Route::get('/contact', [ContactInquiryController::class, 'create'])->name('public.contact');
Route::post('/contact', [ContactInquiryController::class, 'store'])->middleware('throttle:3,1')->name('public.contact.store');

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

Route::get('/jobs/{jobPosting}/provenance', [JobReviewController::class, 'provenance'])->name('jobs.provenance');
