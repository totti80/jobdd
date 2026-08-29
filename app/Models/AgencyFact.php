<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyFact extends Model
{
    protected $fillable = [
        'agency_id',
        'source_id',
        'fact_type',
        'fact_key',
        'fact_value',
        'verification_status',
        'observed_at',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
