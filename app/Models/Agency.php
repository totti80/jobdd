<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'name',
        'website_url',
        'occupation',
        'region',
        'experience_min',
        'experience_max',
        'salary_min',
        'salary_max',
        'job_count',
        'evidence_level',
    ];

    public function scoreResults(): HasMany
    {
        return $this->hasMany(ScoreResult::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(AgencyFact::class);
    }
}
