<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobToolUsage extends Model
{
    protected $fillable = [
        'job_posting_id',
        'tool_key',
        'tool_name',
        'usage_context',
        'experience_expectation',
        'usage_notes',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
