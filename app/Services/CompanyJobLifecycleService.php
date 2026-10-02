<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompanyJobLifecycleService
{
    public function __construct(private CompanyJobAuthoringData $authoring) {}

    public function pause(JobPosting $posting, User $user): void
    {
        DB::transaction(function () use ($posting, $user) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($posting->id);
            Gate::forUser($user)->authorize('update', $job);
            abort_unless($job->status === 'published', 409, '公開中の求人のみ停止できます。');
            $changes = ['status' => 'paused'];
            if ($job->review_status === 'pending_review') {
                // Withdraw the pending request; retain all previous review metadata.
                $changes['review_status'] = 'not_submitted';
            }
            $job->update($changes);
        });
    }

    public function resume(JobPosting $posting, User $user): void
    {
        DB::transaction(function () use ($posting, $user) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($posting->id);
            Gate::forUser($user)->authorize('update', $job);
            abort_unless($job->status === 'paused' && $job->review_status === 'approved', 409, '承認済みの公開停止中求人のみ再開できます。');
            abort_unless($this->authoring->matchesPublishedSnapshot($job), 409, '内容を変更した求人はPreviewから再公開申請してください。');
            // Re-expose the last approved publication without regenerating any artifacts.
            $job->update(['status' => 'published']);
        });
    }
}
