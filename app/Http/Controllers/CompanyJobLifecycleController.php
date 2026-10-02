<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\CompanyJobLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyJobLifecycleController extends Controller
{
    public function pause(Request $request, JobPosting $jobPosting, CompanyJobLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->pause($jobPosting, $request->user());

        return redirect()->route('company.dashboard')->with('status', '求人の公開を停止しました。');
    }

    public function resume(Request $request, JobPosting $jobPosting, CompanyJobLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->resume($jobPosting, $request->user());

        return redirect()->route('company.dashboard')->with('status', '求人の公開を再開しました。');
    }
}
