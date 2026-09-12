<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRoute extends Model
{
    protected $fillable = [
        'job_posting_id',
        'route_type',
        'agency_id',
        'platform_id',
        'application_url',
        'availability_status',
        'notes',
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

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
