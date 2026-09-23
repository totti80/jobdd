<?php

namespace App\Models;

use App\Services\PublishedJobQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobPosting extends Model
{
    public const STATUS_LABELS = ['draft' => '下書き', 'published' => '公開中', 'paused' => '公開停止', 'closed' => '募集終了'];

    public const REVIEW_STATUS_LABELS = [
        'not_submitted' => '未申請', 'pending_review' => '審査中',
        'changes_requested' => '差戻し', 'approved' => '承認済み',
    ];

    protected $fillable = [
        'company_id',
        'status',
        'review_status',
        'review_requested_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'review_note',
        'company_url_evidence',
        'title',
        'occupation',
        'industry',
        'region',
        'salary_min',
        'salary_max',
        'description',
        'application_requirements',
        'employment_type',
        'source_url',
        'provider_key',
        'external_id',
        'first_seen_at',
        'last_seen_at',
        'unavailable_at',
        'published_at',
        'provider_updated_at',
    ];

    protected $casts = [
        'review_requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'company_url_evidence' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'unavailable_at' => 'datetime',
        'published_at' => 'datetime',
        'provider_updated_at' => 'datetime',
    ];

    public function scopeForPublic(Builder $query): Builder
    {
        return PublishedJobQuery::apply($query);
    }

    public function authoringEditable(): bool
    {
        return $this->status === 'draft' || ($this->status === 'published' && ($this->publishedProfile?->profile_data['schema_version'] ?? null) === 1);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applicationRoutes(): HasMany
    {
        return $this->hasMany(ApplicationRoute::class);
    }

    public function jobFacts(): HasMany
    {
        return $this->hasMany(JobFact::class);
    }

    public function structuredProfile(): HasOne
    {
        return $this->hasOne(JobStructuredProfile::class);
    }

    public function toolUsages(): HasMany
    {
        return $this->hasMany(JobToolUsage::class);
    }

    public function typicalDayItems(): HasMany
    {
        return $this->hasMany(JobTypicalDayItem::class);
    }

    public function publishedProfile(): HasOne
    {
        return $this->hasOne(JobPublishedProfile::class);
    }
}
