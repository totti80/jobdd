<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobPublishValidator;
use App\Services\CompanyJobReviewService;
use App\Support\CompanyJobDashboardPresenter;
use Illuminate\Http\Request;

class JobReviewController extends Controller
{
    public function index()
    {
        return response()->view('admin.job-reviews.index', ['jobs' => JobPosting::query()->where('review_status', 'pending_review')->with('company')->orderBy('review_requested_at')->orderBy('id')->paginate(20)])->header('Cache-Control', 'private, no-store');
    }

    public function show(JobPosting $jobPosting, CompanyJobAuthoringData $authoring, CompanyJobPublishValidator $validator, CompanyJobDashboardPresenter $presenter)
    {
        return response()->view('admin.job-reviews.show', ['job' => $jobPosting, 'data' => $authoring->read($jobPosting), 'reviewToken' => $authoring->token($jobPosting), 'publishErrors' => $validator->errors($jobPosting), 'state' => $presenter->present($jobPosting)])->header('Cache-Control', 'private, no-store');
    }

    public function approve(Request $request, JobPosting $jobPosting, CompanyJobPublishService $publish)
    {
        $input = $request->validate(['review_token' => ['required', 'string', 'size:64']]);
        $publish->approve($jobPosting, $request->user(), $input['review_token']);

        return redirect()->route('admin.job-reviews.show', $jobPosting)->with('status', '承認して公開しました。');
    }

    public function changesRequested(Request $request, JobPosting $jobPosting, CompanyJobReviewService $review)
    {
        $input = $request->validate(['review_token' => ['required', 'string', 'size:64'], 'review_note' => ['required', 'string', 'max:10000']], [], ['review_note' => '差戻し理由']);
        $review->requestChanges($jobPosting, $request->user(), $input['review_note'], $input['review_token']);

        return redirect()->route('admin.job-reviews.show', $jobPosting)->with('status', '修正を依頼しました。企業側で確認内容を確認し、編集を再開できます。');
    }

    public function provenance(JobPosting $jobPosting)
    {
        abort_unless($jobPosting->status === 'published' && $jobPosting->publishedProfile && ($jobPosting->publishedProfile->profile_data['schema_version'] ?? null) === 1, 404);

        return view('jobs.provenance', ['data' => $jobPosting->publishedProfile->profile_data]);
    }
}
