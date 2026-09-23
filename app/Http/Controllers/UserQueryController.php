<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\InteractionLog;
use App\Models\JobPosting;
use App\Models\ScoreResult;
use App\Models\UserQuery;
use App\Services\QueryParserService;
use App\Services\RouteSummaryService;
use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserQueryController extends Controller
{
    public function create()
    {
        return view('query.create');
    }

    public function store(
        Request $request,
        QueryParserService $parser,
        ScoreService $scoreService
    ) {
        $validated = $request->validate([
            'raw_text' => ['required', 'string', 'max:2000'],
            'occupation' => ['nullable', 'in:機械設計'],
            'prefecture' => ['nullable', 'in:兵庫県'],
            'salary' => ['nullable', 'regex:/^(こだわらない|[0-9]+万円以上)$/u'],
            'experience' => ['nullable', 'regex:/^(未経験|[0-9]+年)$/u'],
        ]);

        $parsed = $parser->parse($validated['raw_text']);

        if (! empty($validated['occupation'])) {
            $parsed['occupation'] = $validated['occupation'];
            $parsed['validation_domain'] = '製造・プラント系';
        }

        if (! empty($validated['prefecture'])) {
            $parsed['region'] = $validated['prefecture'];
        }

        if (! empty($validated['experience']) && $validated['experience'] !== '未経験') {
            $parsed['experience_years'] = (int) $validated['experience'];
        }

        if (! empty($validated['salary']) && $validated['salary'] !== 'こだわらない') {
            $parsed['salary_min'] = (int) $validated['salary'];
        }

        $userQuery = UserQuery::create([
            'public_id' => (string) Str::uuid(),
            'session_token' => Str::random(64),
            'raw_text' => $validated['raw_text'],
            ...$parsed,
        ]);

        $request->session()->put(
            'jobdd_query_token_'.$userQuery->public_id,
            $userQuery->session_token
        );

        $agencies = Agency::query()
            ->whereIn('id', [7, 8, 9])
            ->get();

        foreach ($agencies as $agency) {
            $scoreData = $scoreService->calculate($userQuery, $agency);

            ScoreResult::create([
                'user_query_id' => $userQuery->id,
                'agency_id' => $agency->id,
                ...$scoreData,
            ]);
        }

        return redirect()
            ->route('query.results', ['userQuery' => $userQuery->public_id]);
    }

    public function results(Request $request, UserQuery $userQuery, RouteSummaryService $routeSummaryService)
    {
        $token = $request->session()->get('jobdd_query_token_'.$userQuery->public_id);
        abort_unless(is_string($token) && $token !== '' && is_string($userQuery->session_token)
            && $userQuery->session_token !== '' && hash_equals($userQuery->session_token, $token), 404);

        $results = $userQuery->scoreResults()
            ->with([
                'agency.facts.source',
            ])
            ->orderByDesc('score')
            ->take(3)
            ->get();

        $jobPostings = JobPosting::query()->forPublic()->where('status', 'published')
            ->with([
                'company',
                'applicationRoutes.platform',
                'applicationRoutes.agency',
            ])
            ->where(function ($query) {
                $query->where('published_company_name', '!=', 'A製作所')
                    ->orWhere(fn ($legacy) => $legacy->whereNull('published_company_name')->whereHas('company', fn ($company) => $company->where('name', '!=', 'A製作所')));
            })

            ->when(
                $userQuery->occupation,
                fn ($query, $occupation) => $query->where('occupation', $occupation)
            )
            ->when(
                $userQuery->region,
                fn ($query, $region) => $query->where('region', 'like', $region.'%')
            )
            ->when(
                $userQuery->salary_min,
                fn ($query, $salaryMin) => $query->where(function ($q) use ($salaryMin) {
                    $q->whereNull('salary_max')
                        ->orWhere('salary_max', '>=', $salaryMin);
                })
            )
            ->latest('updated_at')
            ->take(3)
            ->get();

        // Agentごとの「今回条件に近い公開求人Evidence」
        $agentIds = $results
            ->pluck('agency_id')
            ->filter()
            ->unique()
            ->values();

        $agentEvidenceJobs = JobPosting::query()->forPublic()->where('status', 'published')
            ->with([
                'company',
                'applicationRoutes' => fn ($query) => $query->where('route_type', 'agent')
                    ->whereIn('agency_id', $agentIds)
                    ->where('availability_status', 'available'),
            ])
            ->whereHas(
                'applicationRoutes',
                fn ($query) => $query->where('route_type', 'agent')
                    ->whereIn('agency_id', $agentIds)
                    ->where('availability_status', 'available')
            )
            ->when(
                $userQuery->occupation,
                fn ($query, $occupation) => $query->where('occupation', $occupation)
            )
            ->when(
                $userQuery->region,
                fn ($query, $region) => $query->where('region', 'like', $region.'%')
            )
            ->when(
                $userQuery->salary_min,
                fn ($query, $salaryMin) => $query->whereNotNull('salary_max')
                    ->where('salary_max', '>=', $salaryMin)
            )
            ->latest('updated_at')
            ->get();

        $agentJobEvidence = collect();

        foreach ($agentIds as $agencyId) {
            $agentJobEvidence->put(
                $agencyId,
                $agentEvidenceJobs
                    ->filter(
                        fn ($job) => $job->applicationRoutes
                            ->contains('agency_id', $agencyId)
                    )
                    ->values()
            );
        }

        InteractionLog::create([
            'user_query_id' => $userQuery->id,
            'event_type' => 'results_viewed',
            'target_type' => 'user_query',
            'target_id' => $userQuery->id,
            'metadata' => [
                'result_count' => $results->count(),
            ],
            'occurred_at' => now(),
        ]);

        return view('query.results', [
            'userQuery' => $userQuery,
            'results' => $results,
            'jobPostings' => $jobPostings,
            'agentJobEvidence' => $agentJobEvidence,
            'routeSummaries' => $routeSummaryService->summarize($userQuery),
        ]);
    }
}
