<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\ScoreResult;
use App\Models\UserQuery;
use App\Services\QueryParserService;
use App\Services\ScoreService;
use Illuminate\Http\Request;
use App\Models\InteractionLog;

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

        $agencies = Agency::all();

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
        ]);
    }
}
