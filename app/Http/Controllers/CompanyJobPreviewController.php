<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishValidator;
use App\Services\CompanyJobReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyJobPreviewController extends Controller
{
    public function show(JobPosting $jobPosting, CompanyJobAuthoringData $authoring, CompanyJobPublishValidator $validator)
    {
        Gate::authorize('preview', $jobPosting);

        return response()->view('company.jobs.preview', ['job' => $jobPosting, 'data' => $authoring->read($jobPosting), 'publishErrors' => $validator->errors($jobPosting)])->header('Cache-Control', 'private, no-store');
    }

    public function requestReview(Request $request, JobPosting $jobPosting, CompanyJobReviewService $review)
    {
        $created = $review->request($jobPosting, $request->user());

        return redirect()->route('company.jobs.preview', $jobPosting)->with('status', $created ? '公開申請を受け付けました。運営の確認をお待ちください。' : 'この求人はすでに審査中です。');
    }
}
