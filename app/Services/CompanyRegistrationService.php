<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyRegistrationService
{
    public function register(string $accountName, string $email, string $password): User
    {
        return DB::transaction(function () use ($accountName, $email, $password): User {
            $user = new User([
                'name' => $accountName,
                'email' => $email,
                'password' => $password,
            ]);
            // Never accept system or company roles from registration input.
            $user->system_role = 'user';
            $user->save();

            $company = Company::create(['name' => $accountName]);
            $user->companies()->attach($company, ['role' => 'company_owner']);

            return $user;
        });
    }
}
