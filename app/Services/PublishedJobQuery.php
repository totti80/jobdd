<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only public projection; never use this scope for authoring or writes. */
class PublishedJobQuery
{
    public static function apply(Builder $query): Builder
    {
        $base = DB::table('job_postings as authoring')->leftJoin('job_published_profiles as published', fn ($join) => $join->on('published.job_posting_id', '=', 'authoring.id'));
        // A present but unreadable Snapshot must never expose editable fallback values.
        $base->where(fn ($where) => $where->whereNull('published.id')->orWhere('published.profile_data->schema_version', 1));
        $base->selectRaw("CASE WHEN published.id IS NULL THEN NULL ELSE COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(published.profile_data, '$.company.name')), 'null'), '会社名未確認') END AS published_company_name");
        foreach (['id', ...(new JobPosting)->getFillable(), 'created_at', 'updated_at'] as $field) {
            if (in_array($field, CompanyJobAuthoringData::BASIC_FIELDS)) {
                $json = "CASE WHEN JSON_TYPE(JSON_EXTRACT(published.profile_data, '$.level_one.{$field}')) = 'NULL' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(published.profile_data, '$.level_one.{$field}')) END";
                if (in_array($field, ['salary_min', 'salary_max'])) {
                    $json = "CAST({$json} AS SIGNED)";
                }
                $base->selectRaw("CASE WHEN published.id IS NULL THEN authoring.`{$field}` ELSE {$json} END AS `{$field}`");
            } elseif ($field === 'updated_at') {
                $base->selectRaw('COALESCE(published.published_at, authoring.updated_at) AS updated_at');
            } else {
                $base->addSelect('authoring.'.$field);
            }
        }

        return $query->fromSub($base, 'job_postings');
    }
}
