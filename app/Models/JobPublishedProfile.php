<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPublishedProfile extends Model
{
    protected $fillable = [
        'job_posting_id',
        'profile_data',
        'published_at',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'published_at' => 'datetime',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
