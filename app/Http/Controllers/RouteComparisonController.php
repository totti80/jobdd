<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\InteractionLog;
use Illuminate\Http\Request;

class RouteComparisonController extends Controller
{
    public function show(Request $request, JobPosting $jobPosting)
    {

        $jobPosting->load([
            'company',
            'applicationRoutes.agency',
            'applicationRoutes.platform',
        ]);

        $jobPosting->setRelation(
            'applicationRoutes',
            $jobPosting->applicationRoutes
                ->sortBy(fn($route) => match ($route->route_type) {
                    'direct' => 1,
                    'agent' => 2,
                    'platform' => 3,
                    default => 99,
                })
                ->values()
        );

        InteractionLog::create([
            'user_query_id' => $request->integer('userQuery'),
            'event_type' => 'route_opened',
            'target_type' => 'job_posting',
            'target_id' => $jobPosting->id,
            'metadata' => [
                'route_count' => $jobPosting->applicationRoutes->count(),
            ],
            'occurred_at' => now(),
        ]);


        return view('routes.show', [
            'jobPosting' => $jobPosting,
        ]);
    }
}
