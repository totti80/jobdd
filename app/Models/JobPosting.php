<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosting extends Model
{
    protected $fillable = [
        'company_id',
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
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'unavailable_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applicationRoutes(): HasMany
    {
        return $this->hasMany(ApplicationRoute::class);
    }
}
