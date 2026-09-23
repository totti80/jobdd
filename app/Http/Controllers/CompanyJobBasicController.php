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
            ...$request->validated(),
            'status' => 'draft',
            'review_status' => 'not_submitted',
        ]);

        return redirect()->route('company.jobs.basic.edit', $job)->with('status', 'Level 1を保存しました。');
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
            abort_unless($job->status === 'draft', 409, '公開済み求人の編集は現在利用できません。');
            $job->update($request->validated());
        });

        return redirect()->route('company.jobs.basic.edit', $jobPosting)->with('status', 'Level 1を保存しました。');
    }
}
