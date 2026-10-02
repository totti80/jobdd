<?php

namespace App\Http\Controllers;

use App\Services\CompanyWorkspace;
use App\Support\CompanyJobDashboardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyDashboardController extends Controller
{
    public function __invoke(Request $request, CompanyWorkspace $workspace, CompanyJobDashboardPresenter $presenter): View
    {
        $company = $workspace->companyFor($request->user());
        $jobs = null;
        $counts = [];
        if ($company) {
            Gate::authorize('view', $company);
            $counts = array_fill_keys(['published', 'draft', 'creating', 'pending_review', 'changes_requested'], 0);
            foreach ($company->jobPostings()->selectRaw('status, review_status, COUNT(*) as total')->groupBy('status', 'review_status')->get() as $group) {
                if (in_array($group->status, ['published', 'draft'])) {
                    $counts[$group->status] += (int) $group->total;
                }
                if ($group->status === 'draft' && $group->review_status === 'not_submitted') {
                    $counts['creating'] += (int) $group->total;
                }
                if (in_array($group->review_status, ['pending_review', 'changes_requested'])) {
                    $counts[$group->review_status] += (int) $group->total;
                }
            }
            $jobs = $company->jobPostings()->with(['publishedProfile', 'structuredProfile', 'toolUsages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'), 'typicalDayItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);
            $jobs->getCollection()->each(fn ($job) => $job->setRelation('company', $company));
        }

        $states = $jobs?->getCollection()->mapWithKeys(fn ($job) => [$job->id => $presenter->present($job)])->all() ?? [];

        return view('company.dashboard', compact('company', 'jobs', 'counts', 'states'));
    }
}
