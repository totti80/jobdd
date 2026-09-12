<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectReverseLookupCandidate extends Model
{
  protected $fillable = [
    'company_id',
    'region',
    'occupation',
    'discovery_source',
    'matching_job_count',
    'website_url',
    'direct_status',
    'official_recruit_url',
    'checked_at',
    'last_seen_at',
  ];

  protected $casts = [
    'checked_at' => 'datetime',
    'last_seen_at' => 'datetime',
  ];

  public function company(): BelongsTo
  {
    return $this->belongsTo(Company::class);
  }
}
