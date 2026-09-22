<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTypicalDayItem extends Model
{
    protected $fillable = [
        'job_posting_id',
        'time_label',
        'activity',
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
