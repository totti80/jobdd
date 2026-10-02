<?php

namespace App\Services;

use App\Mail\CompanyJobReviewRequested;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CompanyJobReviewService
{
    public function __construct(private CompanyJobPublishValidator $validator, private CompanyJobAuthoringData $authoring) {}

    public function request(JobPosting $posting, User $user): bool
    {
        return DB::transaction(function () use ($posting, $user) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($posting->id);
            Gate::forUser($user)->authorize('publish', $job);
            abort_unless(in_array($job->status, ['draft', 'published', 'paused']), 409);
            abort_if($job->status === 'paused' && ! $job->authoringEditable(), 409);
            if ($job->review_status === 'pending_review') {
                return false;
            }
            $this->validator->validate($job);
            $job->update(['review_status' => 'pending_review', 'review_requested_at' => now(), 'reviewed_at' => null, 'reviewed_by_user_id' => null, 'review_note' => null]);
            $mail = new CompanyJobReviewRequested($job->company->name, $job->title, $job->review_requested_at->format('Y/m/d H:i:s'), route('admin.job-reviews.show', $job));
            DB::afterCommit(function () use ($mail, $job) {
                try {
                    Mail::to(config('jobdd.review_notification_email'))->send($mail);
                } catch (Throwable $e) {
                    Log::error('Company job review notification failed; request remains pending.', ['job_posting_id' => $job->id, 'exception_class' => $e::class]);
                }
            });

            return true;
        });
    }

    public function requestChanges(JobPosting $posting, User $reviewer, string $note, string $token): void
    {
        abort_unless($reviewer->isPlatformOwner(), 403);
        abort_if(trim($note) === '', 422);
        DB::transaction(function () use ($posting, $reviewer, $note, $token) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($posting->id);
            abort_unless($job->review_status === 'pending_review', 409);
            abort_unless(hash_equals($this->authoring->token($job), $token), 409, '確認後に内容が更新されました。');
            $job->update(['review_status' => 'changes_requested', 'reviewed_at' => now(), 'reviewed_by_user_id' => $reviewer->id, 'review_note' => $note]);
        });
    }
}
