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
            'applicationRoutes' => fn($query) => $query
                ->where('availability_status', 'available')
                ->whereNull('unavailable_at')
                ->with(['agency', 'platform']),
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

        try {
            InteractionLog::create([
                'user_query_id' => $request->integer('userQuery') ?: null,
                'event_type' => 'route_opened',
                'target_type' => 'job_posting',
                'target_id' => $jobPosting->id,
                'metadata' => [
                    'route_count' => $jobPosting->applicationRoutes->count(),
                ],
                'occurred_at' => now(),
            ]);
        } catch (\Throwable) {
            // Route display must not fail when analytics storage is unavailable.
        }

        $routeGroups = [
            'direct' => $jobPosting->applicationRoutes
                ->where('route_type', 'direct')
                ->values(),

            'agent' => $jobPosting->applicationRoutes
                ->where('route_type', 'agent')
                ->values(),

            'platform' => $jobPosting->applicationRoutes
                ->where('route_type', 'platform')
                ->values(),
        ];

        return view('routes.show', [
            'jobPosting' => $jobPosting,
            'routeGroups' => $routeGroups,
        ]);
    }

    public function action(\App\Models\JobPosting $jobPosting)
    {
        $jobPosting->load([
            'company',
            'applicationRoutes' => fn($query) => $query
                ->where('availability_status', 'available')
                ->whereNull('unavailable_at')
                ->with(['agency', 'platform']),
        ]);

        return view('routes.action', [
            'jobPosting' => $jobPosting,
        ]);
    }
}
