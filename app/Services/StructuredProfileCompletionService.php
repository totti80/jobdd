<?php

namespace App\Services;

use App\Models\JobPosting;

class StructuredProfileCompletionService
{
    public function calculate(JobPosting $job): array
    {
        $job->loadMissing(['structuredProfile', 'toolUsages', 'typicalDayItems']);
        $profile = $job->structuredProfile;
        $checks = [];
        foreach (['title', 'occupation', 'region', 'employment_type', 'description', 'source_url', 'application_requirements'] as $field) {
            $checks[$field] = filled($job->$field);
        }
        $checks['salary'] = filled($job->salary_min) || filled($job->salary_max);
        foreach (['design_target', 'design_phases', 'initial_assignment', 'collaborators', 'work_style', 'difficult_points'] as $field) {
            $checks[$field] = filled($profile?->$field);
        }
        foreach (['tool_name', 'usage_context', 'experience_expectation'] as $field) {
            $checks[$field] = $job->toolUsages->isNotEmpty() && $job->toolUsages->every(fn ($tool) => filled($tool->$field));
        }
        foreach (['customer_contact', 'manufacturing_relation', 'site_relation'] as $field) {
            $checks[$field] = filled($profile?->{$field.'_frequency'}) && filled($profile?->{$field.'_note'});
        }
        $checks['typical_day'] = $job->typicalDayItems->isNotEmpty() && $job->typicalDayItems->every(fn ($item) => filled($item->time_label) && filled($item->activity));
        $completed = count(array_filter($checks));

        return ['completed' => $completed, 'total' => count($checks), 'percentage' => (int) round(100 * $completed / count($checks))];
    }
}
