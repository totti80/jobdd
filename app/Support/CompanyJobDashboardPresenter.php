<?php

namespace App\Support;

use App\Models\JobPosting;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishValidator;
use App\Services\StructuredProfileCompletionService;
use Illuminate\Support\Facades\Log;

class CompanyJobDashboardPresenter
{
    public function __construct(private CompanyJobAuthoringData $authoring, private CompanyJobPublishValidator $validator, private StructuredProfileCompletionService $completion) {}

    public function present(JobPosting $job): array
    {
        $ready = $this->validator->errors($job) === [];
        $published = $job->status === 'published';
        $snapshot = $job->publishedProfile?->profile_data;
        $changed = false;
        if ($published && ($snapshot['schema_version'] ?? null) === 1) {
            // Provenance is publish metadata, not employer authoring content.
            unset($snapshot['provenance']);
            $changed = $this->authoring->read($job) != $snapshot;
        }
        $invalid = ($job->status === 'draft' && ($job->review_status === 'approved' || ($job->review_status === 'pending_review' && ! $ready)))
            || ($published && $job->review_status === 'not_submitted')
            || ! in_array($job->status, ['draft', 'published'])
            || ! in_array($job->review_status, ['not_submitted', 'pending_review', 'changes_requested', 'approved']);
        $preview = route('company.jobs.preview', $job);
        $edit = route('company.jobs.basic.edit', $job);
        $review = ['not_submitted' => '未申請', 'pending_review' => $published ? '更新内容を審査中' : '審査中', 'changes_requested' => $published ? '更新内容の修正をお願いします' : '修正をお願いします', 'approved' => '承認済み'][$job->review_status] ?? '状態を確認してください';
        $secondary = [];
        if ($invalid) {
            Log::warning('Company dashboard lifecycle inconsistency', ['job_posting_id' => $job->id, 'status' => $job->status, 'review_status' => $job->review_status]);
            $primary = '状態を確認する';
            $url = $preview;
        } elseif ($job->review_status === 'pending_review') {
            $primary = '審査状況を見る';
            $url = $preview.'#review-status';
            $secondary[] = ['label' => 'Preview', 'url' => $preview];
        } elseif ($job->review_status === 'changes_requested') {
            $primary = '修正する';
            $url = $edit;
            $secondary[] = ['label' => $published ? '修正内容を見る' : '審査内容を見る', 'url' => $preview.'#review-status'];
        } elseif ($published) {
            $primary = $changed ? '編集を続ける' : '編集';
            $url = $edit;
        } else {
            $primary = $ready ? 'Preview' : '入力を再開';
            $url = $ready ? $preview : $edit;
        }
        if ($published) {
            $secondary[] = ['label' => $changed || $job->review_status !== 'approved' ? '現在の公開ページを見る' : '公開ページを見る', 'url' => route('public.job', $job)];
        }
        if (! $invalid && ! $job->authoringEditable() && $job->review_status !== 'pending_review') {
            $primary = '内容を見る';
            $url = $preview;
        }

        return ['publication' => $published ? '公開中' : ($job->status === 'draft' ? '未公開' : '公開状況を確認してください'), 'review' => $review, 'changed' => $changed, 'invalid' => $invalid, 'primary' => $primary, 'url' => $url, 'secondary' => $secondary, 'completion' => $this->completion->calculate($job)['percentage']];
    }
}
