<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStructuredProfile extends Model
{
    protected $fillable = [
        'job_posting_id',
        'design_target',
        'product_context',
        'design_phases',
        'initial_assignment',
        'future_scope',
        'required_experience',
        'preferred_experience',
        'collaborators',
        'customer_contact_frequency',
        'customer_contact_note',
        'manufacturing_relation_frequency',
        'manufacturing_relation_note',
        'site_relation_frequency',
        'site_relation_note',
        'work_style',
        'project_duration',
        'concurrent_projects',
        'difficult_points',
        'onboarding_challenges',
        'fit_work_style',
        'misfit_work_style',
        'representative_project',
        'hard_to_convey',
    ];

    protected $casts = [
        'design_phases' => 'array',
        'collaborators' => 'array',
        'representative_project' => 'array',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
