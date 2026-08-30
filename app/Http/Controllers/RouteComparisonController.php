<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;

class RouteComparisonController extends Controller
{
    public function show(JobPosting $jobPosting)
    {
        $jobPosting->load([
            'company',
            'applicationRoutes.agency',
            'applicationRoutes.platform',
        ]);

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

        return view('routes.show', [
            'jobPosting' => $jobPosting,
        ]);
    }
}