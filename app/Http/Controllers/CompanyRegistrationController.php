<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRegistrationRequest;
use App\Services\CompanyRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyRegistrationController extends Controller
{
    public function create(): View
    {
        return view('company.auth.register');
    }

    public function store(CompanyRegistrationRequest $request, CompanyRegistrationService $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $service->register($data['account_name'], $data['email'], $data['password']);

        event(new Registered($user));
        Auth::guard(config('fortify.guard'))->login($user);
        $request->session()->regenerate();

        return redirect()->route('company.dashboard');
    }
}
