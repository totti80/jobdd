<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyWorkspace
{
    public function companyFor(User $user): ?Company
    {
        // Phase A has no company switcher. Never pick an unrelated company for admins.
        return $user->companies()->wherePivotIn('role', ['company_owner', 'company_editor'])
            ->orderBy('companies.id')->first();
    }
}
