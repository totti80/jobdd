<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isPlatformOwner() ? true : null;
    }

    public function view(User $user, JobPosting $jobPosting): bool
    {
        return $user->managesCompany($jobPosting->company_id);
    }

    public function update(User $user, JobPosting $jobPosting): bool
    {
        return $user->managesCompany($jobPosting->company_id);
    }

    public function preview(User $user, JobPosting $jobPosting): bool
    {
        return $user->managesCompany($jobPosting->company_id);
    }

    public function publish(User $user, JobPosting $jobPosting): bool
    {
        return $user->managesCompany($jobPosting->company_id);
    }
}
