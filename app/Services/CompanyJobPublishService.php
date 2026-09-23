<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyJobPublishService
{
    public function __construct(private CompanyJobAuthoringData $authoring, private CompanyJobPublishValidator $validator, private CompanyJobFactTransformer $transformer) {}

    public function approve(JobPosting $posting, User $reviewer, string $token): void
    {
        abort_unless($reviewer->isPlatformOwner(), 403);
        DB::transaction(function () use ($posting, $reviewer, $token) {
            $job = JobPosting::query()->lockForUpdate()->findOrFail($posting->id);
            abort_unless($job->review_status === 'pending_review', 409, 'この申請は審査待ちではありません。');
            abort_unless(hash_equals($this->authoring->token($job), $token), 409, '確認後に内容が更新されました。再読み込みして確認してください。');
            abort_unless(in_array($job->status, ['draft', 'published']), 409);
            $this->validator->validate($job);
            $data = $this->authoring->read($job);
            $now = now();
            // Reserved internal provenance path, never the employer's application/import URL.
            $source = Source::firstOrCreate(['source_type' => 'company_self_reported', 'url' => route('jobs.provenance', $job)], ['publisher' => $job->company->name, 'title' => $job->company->name.'によるJobDD登録情報', 'fetched_at' => $now]);
            $source->update(['publisher' => $job->company->name, 'title' => $job->company->name.'によるJobDD登録情報', 'fetched_at' => $now]);
            $job->jobFacts()->where('extraction_method', 'company_self_reported')->delete();
            foreach ($this->transformer->transform($data) as $fact) {
                $job->jobFacts()->create([...$fact, 'source_id' => $source->id, 'observed_at' => $now]);
            }
            $data['provenance'] = ['source_id' => $source->id, 'source_type' => 'company_self_reported', 'url' => $source->url, 'publisher' => $source->publisher, 'title' => $source->title, 'verification_status' => 'self_reported', 'published_at' => $now->toISOString(), 'reviewed_by_user_id' => $reviewer->id];
            $job->publishedProfile()->updateOrCreate([], ['profile_data' => $data, 'published_at' => $now]);
            $job->applicationRoutes()->updateOrCreate(['route_type' => 'direct', 'agency_id' => null, 'platform_id' => null], ['application_url' => $job->source_url, 'availability_status' => 'available', 'unavailable_at' => null]);
            $job->update(['status' => 'published', 'published_at' => $job->published_at ?? $now, 'review_status' => 'approved', 'reviewed_at' => $now, 'reviewed_by_user_id' => $reviewer->id, 'review_note' => null]);
        });
    }
}
