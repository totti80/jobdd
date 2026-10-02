<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyJobBasicRequest;
use App\Models\JobPosting;
use App\Services\CompanyWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyJobBasicController extends Controller
{
    public function create(Request $request, CompanyWorkspace $workspace): View
    {
        $company = $workspace->companyFor($request->user());
        abort_unless($company, 403);
        Gate::authorize('create', [JobPosting::class, $company]);

        return view('company.jobs.basic', ['company' => $company, 'job' => new JobPosting]);
    }

    public function store(CompanyJobBasicRequest $request): RedirectResponse
    {
        $job = $request->company()->jobPostings()->create([
            ...$request->safe()->except('navigation'),
            'status' => 'draft',
            'review_status' => 'not_submitted',
        ]);

        return ($request->input('navigation') === 'next' ? redirect()->route('company.jobs.structured.edit', [$job, 1]) : redirect()->route('company.jobs.basic.edit', $job))->with('status', 'Level 1を保存しました。');
    }

    public function edit(JobPosting $jobPosting): View
    {
        Gate::authorize('view', $jobPosting);

        return view('company.jobs.basic', ['company' => $jobPosting->company, 'job' => $jobPosting]);
    }

    public function update(CompanyJobBasicRequest $request, JobPosting $jobPosting): RedirectResponse
    {
        DB::transaction(function () use ($request, $jobPosting) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($jobPosting->id);
            Gate::authorize('update', $job);
            abort_if($job->review_status === 'pending_review', 409, '審査中は編集できません。差戻し後に編集を再開できます。');
            abort_unless($job->authoringEditable(), 409, '公開Snapshotのない既存求人は編集できません。');
            $job->update($request->safe()->except('navigation'));
        });

        return ($request->input('navigation') === 'next' ? redirect()->route('company.jobs.structured.edit', [$jobPosting, 1]) : redirect()->route('company.jobs.basic.edit', $jobPosting))->with('status', 'Level 1を保存しました。');
    }
}
