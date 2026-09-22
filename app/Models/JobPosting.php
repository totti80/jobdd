<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobPosting extends Model
{
    protected $fillable = [
        'company_id',
        'status',
        'company_url_evidence',
        'title',
        'occupation',
        'industry',
        'region',
        'salary_min',
        'salary_max',
        'description',
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
        'company_url_evidence' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'unavailable_at' => 'datetime',
        'published_at' => 'datetime',
        'provider_updated_at' => 'datetime',
    ];

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
