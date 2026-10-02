<?php

namespace App\Http\Responses;

use Laravel\Fortify\Fortify;

class LoginResponse extends \Laravel\Fortify\Http\Responses\LoginResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return parent::toResponse($request);
        }

        $user = $request->user(config('fortify.guard'));
        $destination = Fortify::redirects('login');
        if ($user?->isPlatformOwner()) {
            $destination = route('admin.job-reviews.index', absolute: false);
        } elseif ($user && $user->companies()
            ->wherePivotIn('role', ['company_owner', 'company_editor'])->exists()) {
            $destination = route('company.dashboard', absolute: false);
        }

        return redirect()->intended($destination);
    }
}
