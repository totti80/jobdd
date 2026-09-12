<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserQuery extends Model
{
    protected $fillable = [
        'public_id',
        'session_token',
        'raw_text',
        'occupation',
        'industry',
        'region',
        'experience_years',
        'salary_min',
        'salary_max',
        'validation_domain',
    ];

    protected $casts = [
        'public_id' => 'string',
    ];

    public function scoreResults(): HasMany
    {
        return $this->hasMany(ScoreResult::class);
    }
}
