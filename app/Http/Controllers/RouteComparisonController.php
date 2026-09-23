<?php

namespace App\Http\Controllers;

use App\Models\InteractionLog;
use App\Models\JobPosting;
use Illuminate\Http\Request;

class RouteComparisonController extends Controller
{
    public function show(Request $request, JobPosting $jobPosting)
    {

        $jobPosting = JobPosting::query()->forPublic()->where('status', 'published')->findOrFail($jobPosting->id);

        $jobPosting->load([
            'company',
            'applicationRoutes' => fn ($query) => $query
                ->where('availability_status', 'available')
                ->whereNull('unavailable_at')
                ->with(['agency', 'platform']),
        ]);

        $snapshot = $jobPosting->publishedProfile()->first();
        abort_if($snapshot && ($snapshot->profile_data['schema_version'] ?? null) !== 1, 404);
        if ($snapshot && $jobPosting->company) {
            $jobPosting->company->name = $snapshot->profile_data['company']['name'] ?? '会社名未確認';
        }

        $jobPosting->setRelation(
            'applicationRoutes',
            $jobPosting->applicationRoutes
                ->sortBy(fn ($route) => match ($route->route_type) {
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

    public function action(JobPosting $jobPosting)
    {
        $jobPosting = JobPosting::query()->forPublic()->where('status', 'published')->findOrFail($jobPosting->id);

        $jobPosting->load([
            'company',
            'applicationRoutes' => fn ($query) => $query
                ->where('availability_status', 'available')
                ->whereNull('unavailable_at')
                ->with(['agency', 'platform']),
        ]);

        return view('routes.action', [
            'jobPosting' => $jobPosting,
        ]);
    }
}
