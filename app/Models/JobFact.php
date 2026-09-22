<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobFact extends Model
{
  protected $fillable = [
    'job_posting_id',
    'source_id',
    'context_role',
    'fact_category',
    'fact_key',
    'fact_value',
    'normalized_value',
    'extraction_method',
    'verification_status',
    'evidence_text',
    'observed_at',
  ];

  protected $casts = [
    'observed_at' => 'datetime',
  ];

  public function jobPosting(): BelongsTo
  {
    return $this->belongsTo(JobPosting::class);
  }

  public function source(): BelongsTo
  {
    return $this->belongsTo(Source::class);
  }
}
