<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Support\StructuredJobOptions;

class CompanyJobAuthoringData
{
    public const BASIC_FIELDS = ['title', 'occupation', 'region', 'salary_min', 'salary_max', 'employment_type', 'description', 'source_url', 'application_requirements'];

    public function read(JobPosting $job): array
    {
        $job->loadMissing(['company', 'structuredProfile', 'toolUsages' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'), 'typicalDayItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $fields = array_merge(...array_map('array_keys', StructuredJobOptions::FIELDS));

        return ['schema_version' => 1, 'company' => ['id' => $job->company_id, 'name' => $job->company?->name],
            'level_one' => $job->only(self::BASIC_FIELDS),
            'structured_profile' => $job->structuredProfile?->only($fields) ?? [],
            'tool_usages' => $job->toolUsages->map(fn ($r) => $r->only(['tool_key', 'tool_name', 'usage_context', 'experience_expectation', 'usage_notes', 'sort_order']))->all(),
            'typical_day_items' => $job->typicalDayItems->map(fn ($r) => $r->only(['time_label', 'activity', 'sort_order']))->all()];
    }

    public function token(JobPosting $job): string
    {
        return hash_hmac('sha256', json_encode([$job->id, $job->review_requested_at?->toISOString(), $this->read($job)], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function matchesPublishedSnapshot(JobPosting $job): bool
    {
        $snapshot = $job->publishedProfile?->profile_data;
        if (($snapshot['schema_version'] ?? null) !== 1) {
            return false;
        }
        // Provenance is publish metadata, not employer authoring content.
        unset($snapshot['provenance']);

        return $this->read($job) == $snapshot;
    }
}
