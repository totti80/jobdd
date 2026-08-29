<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\ScoreResult;
use App\Models\UserQuery;
use App\Services\QueryParserService;
use App\Services\ScoreService;
use Illuminate\Http\Request;

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

        return view('query.results', [
            'userQuery' => $userQuery,
            'results' => $results,
        ]);
    }
}