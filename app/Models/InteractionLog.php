<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractionLog extends Model
{
    protected $fillable = [
        'user_query_id',
        'event_type',
        'target_type',
        'target_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function userQuery(): BelongsTo
    {
        return $this->belongsTo(UserQuery::class);
    }
}
