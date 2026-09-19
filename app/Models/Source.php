<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'source_type',
        'title',
        'publisher',
        'url',
        'fetched_at',
    ];

    public function agencyFacts(): HasMany
    {
        return $this->hasMany(AgencyFact::class);
    }

    public function jobFacts(): HasMany
    {
        return $this->hasMany(JobFact::class);
    }
}
