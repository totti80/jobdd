<?php

namespace App\Services;

use App\Support\StructuredJobOptions as Options;
use Illuminate\Support\Str;

class CompanyJobFactTransformer
{
    public function transform(array $data): array
    {
        $profile = $data['structured_profile'];
        $facts = [];
        $add = function ($category, $key, $value, $normalized = null, $evidence = null) use (&$facts) {
            if (! filled($value)) {
                return;
            }
            $facts[] = ['fact_category' => $category, 'fact_key' => $key, 'fact_value' => $value, 'normalized_value' => $normalized ?? $value,
                'context_role' => JobFactDictionary::companyRole($category, $key, $value), 'extraction_method' => 'company_self_reported', 'verification_status' => 'self_reported', 'evidence_text' => $evidence ?? $value];
        };
        foreach (JobFactDictionary::companyDefinitions() as $category => $definitions) {
            if (in_array($category, ['design_phase', 'tool_usage', 'tool_expectation', 'collaboration', 'project_example'])) {
                continue;
            }
            foreach ($definitions as $key => $role) {
                $add($category, $key, $profile[$key] ?? null);
            }
        }
        foreach ($profile['design_phases'] ?? [] as $key) {
            $add('design_phase', $key, Options::PHASES[$key], $key);
        }
        foreach ($profile['collaborators'] ?? [] as $key) {
            $add('collaboration', $key, Options::COLLABORATORS[$key], $key);
        }
        foreach (['customer_contact', 'manufacturing_relation', 'site_relation'] as $key) {
            $frequency = $profile[$key.'_frequency'] ?? null;
            $note = $profile[$key.'_note'] ?? null;
            if (filled($frequency) || filled($note)) {
                $add('collaboration', $key, $note ?: Options::FREQUENCIES[$frequency], $frequency, trim((Options::FREQUENCIES[$frequency] ?? '').'：'.$note));
            }
        }
        foreach ($data['tool_usages'] as $tool) {
            $key = $tool['tool_key'] ?: 'other_tool';
            $evidence = json_encode($tool, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $add('tool_usage', $key, $tool['usage_context'], $tool['tool_name'], $evidence);
            $add('tool_expectation', $key, $tool['experience_expectation'], $tool['tool_name'], $evidence);
        }
        if (collect($profile['representative_project'] ?? [])->contains(fn ($v) => filled($v))) {
            $value = json_encode($profile['representative_project'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $add('project_example', 'representative_project', Str::limit($value, 10000));
        }

        return $facts;
    }
}
