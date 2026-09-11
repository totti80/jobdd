<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\ScoreResult;
use App\Models\UserQuery;
use App\Services\QueryParserService;
use App\Services\ScoreService;
use Illuminate\Http\Request;
use App\Models\InteractionLog;
use App\Models\JobPosting;

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
        ]);

        $parsed = $parser->parse($validated['raw_text']);

        $userQuery = UserQuery::create([
            'raw_text' => $validated['raw_text'],
            ...$parsed,
        ]);

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
            ->route('query.results', $userQuery);
    }

    public function results(UserQuery $userQuery)
    {
        $results = $userQuery->scoreResults()
            ->with([
                'agency.facts.source',
            ])
            ->orderByDesc('score')
            ->take(3)
            ->get();

        $jobPostings = JobPosting::query()
            ->with([
                'company',
                'applicationRoutes.platform',
                'applicationRoutes.agency',
            ])
            ->whereHas('company', function ($query) {
                $query->where('name', '!=', 'A製作所');
            })
            
            ->when(
                $userQuery->occupation,
                fn($query, $occupation) =>
                $query->where('occupation', $occupation)
            )
            ->when(
                $userQuery->region,
                fn($query, $region) =>
                $query->where('region', 'like', $region . '%')
            )
            ->when(
                $userQuery->salary_min,
                fn($query, $salaryMin) =>
                $query->where(function ($q) use ($salaryMin) {
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

        $agentEvidenceJobs = JobPosting::query()
            ->with([
                'company',
                'applicationRoutes' => fn($query) =>
                $query->where('route_type', 'agent')
                    ->whereIn('agency_id', $agentIds)
                    ->where('availability_status', 'available'),
            ])
            ->whereHas(
                'applicationRoutes',
                fn($query) =>
                $query->where('route_type', 'agent')
                    ->whereIn('agency_id', $agentIds)
                    ->where('availability_status', 'available')
            )
            ->when(
                $userQuery->occupation,
                fn($query, $occupation) =>
                $query->where('occupation', $occupation)
            )
            ->when(
                $userQuery->region,
                fn($query, $region) =>
                $query->where('region', 'like', $region . '%')
            )
            ->when(
                $userQuery->salary_min,
                fn($query, $salaryMin) =>
                $query->whereNotNull('salary_max')
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
                        fn($job) =>
                        $job->applicationRoutes
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
        ]);
    }
}
