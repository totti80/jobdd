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
        $isCompanyUser = $user && ! $user->isPlatformOwner() && $user->companies()
            ->wherePivotIn('role', ['company_owner', 'company_editor'])->exists();

        return redirect()->intended($isCompanyUser
            ? route('company.dashboard', absolute: false)
            : Fortify::redirects('login'));
    }
}
