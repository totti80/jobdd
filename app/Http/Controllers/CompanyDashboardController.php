<?php

namespace App\Http\Controllers;

use App\Services\CompanyWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyDashboardController extends Controller
{
    public function __invoke(Request $request, CompanyWorkspace $workspace): View
    {
        $company = $workspace->companyFor($request->user());
        $jobs = null;
        $counts = [];
        if ($company) {
            Gate::authorize('view', $company);
            $counts = $company->jobPostings()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $jobs = $company->jobPostings()->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);
        }

        return view('company.dashboard', compact('company', 'jobs', 'counts'));
    }
}
