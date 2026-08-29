<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreResult extends Model
{
    protected $fillable = [
        'user_query_id',
        'agency_id',
        'score',
        'occupation_score',
        'region_score',
        'experience_score',
        'salary_score',
        'job_score',
        'reason',
        'score_model_version',
    ];

    public function userQuery(): BelongsTo
    {
        return $this->belongsTo(UserQuery::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
