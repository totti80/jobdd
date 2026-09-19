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
        'detailed_skills',
        'priorities',
    ];

    protected $casts = [
        'public_id' => 'string',
        'detailed_skills' => 'array',
        'priorities' => 'array',
    ];

    public function scoreResults(): HasMany
    {
        return $this->hasMany(ScoreResult::class);
    }
}
