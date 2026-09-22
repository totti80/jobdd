<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->isPlatformOwner() || $user->companies()
            ->wherePivotIn('role', ['company_owner', 'company_editor'])->exists()), 403);

        return $next($request);
    }
}
