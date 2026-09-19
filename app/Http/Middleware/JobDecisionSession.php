<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\StartSession;

/** Preserve existing session authorization without writing on the read-only decision page. */
class JobDecisionSession extends StartSession
{
    public function handle($request, Closure $next)
    {
        if (! $request->routeIs('query.jobs')) {
            return parent::handle($request, $next);
        }

        // No save, garbage collection, session lock or sliding lifetime refresh for this GET.
        $request->setLaravelSession($this->startSession($request, $this->getSession($request)));

        return $next($request);
    }
}
