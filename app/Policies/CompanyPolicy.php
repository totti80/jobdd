<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isPlatformOwner() ? true : null;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->managesCompany($company->id);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->managesCompany($company->id);
    }
}
