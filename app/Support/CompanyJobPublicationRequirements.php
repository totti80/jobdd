<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Publication requirements only; draft saving and completeness are separate. */
class CompanyJobPublicationRequirements
{
    private const REQUIRED = ['title', 'occupation', 'region', 'employment_type', 'description', 'application_requirements', 'source_url'];

    private const SALARY = ['salary_min', 'salary_max'];

    private const ROW_REQUIRED = ['tool_usages.*.tool_name', 'tool_usages.*.usage_context', 'tool_usages.*.experience_expectation', 'typical_day_items.*.time_label', 'typical_day_items.*.activity'];

    public static function requirement(string $path): string
    {
        if (str_starts_with($path, 'level_one.')) {
            $field = substr($path, strlen('level_one.'));
            if (in_array($field, self::SALARY, true)) {
                return '必須（下限・上限のどちらか）';
            }
            if (in_array($field, self::REQUIRED, true)) {
                return '必須';
            }
        }

        return in_array($path, self::ROW_REQUIRED, true) ? '任意（行を入力する場合は必須）' : '任意';
    }

    public static function rules(array $data): array
    {
        $rules = [];
        foreach (self::REQUIRED as $field) {
            $rules['level_one.'.$field] = ['required', 'string', 'max:'.(in_array($field, ['description', 'application_requirements']) ? 10000 : 255)];
        }
        $rules['level_one.occupation'][] = Rule::in(['機械設計', '電気設計']);
        $rules['level_one.source_url'] = ['required', 'url:http,https', 'max:2048'];
        foreach (self::SALARY as $field) {
            $other = $field === 'salary_min' ? 'salary_max' : 'salary_min';
            $rules['level_one.'.$field] = ['nullable', 'required_without:level_one.'.$other, 'integer', 'between:1,10000'];
        }
        if (filled($data['level_one']['salary_min'] ?? null) && filled($data['level_one']['salary_max'] ?? null)) {
            $rules['level_one.salary_max'][] = 'gte:level_one.salary_min';
        }
        foreach (StructuredJobOptions::FIELDS as $fields) {
            foreach ($fields as $field => $label) {
                if ($field === 'representative_project') {
                    continue;
                }
                $options = StructuredJobOptions::options($field);
                $path = 'structured_profile.'.$field;
                if (in_array($field, ['design_phases', 'collaborators'], true)) {
                    $rules[$path] = ['nullable', 'array', 'max:20'];
                    $rules[$path.'.*'] = ['required', 'string', 'distinct', Rule::in(array_keys($options))];
                } else {
                    $rules[$path] = $options ? ['nullable', 'string', Rule::in(array_keys($options))] : ['nullable', 'string', 'max:10000'];
                }
            }
        }
        $project = 'structured_profile.representative_project';
        $rules[$project] = ['nullable', 'array:what_made,phases,duration,team,difficult_point'];
        foreach (['what_made', 'duration', 'team', 'difficult_point'] as $field) {
            $rules[$project.'.'.$field] = ['nullable', 'string', 'max:10000'];
        }
        $rules[$project.'.phases'] = ['nullable', 'array', 'max:20'];
        $rules[$project.'.phases.*'] = ['required', 'string', 'distinct', Rule::in(array_keys(StructuredJobOptions::PHASES))];

        $rules['tool_usages'] = ['nullable', 'array', 'max:30'];
        $rules['tool_usages.*'] = ['array'];
        $rules['tool_usages.*.tool_name'] = ['string', 'max:255'];
        $rules['tool_usages.*.tool_key'] = ['nullable', 'string', Rule::in(array_keys(StructuredJobOptions::TOOLS))];
        $rules['tool_usages.*.usage_context'] = ['string', Rule::in(array_keys(StructuredJobOptions::USAGES))];
        $rules['tool_usages.*.experience_expectation'] = ['string', Rule::in(array_keys(StructuredJobOptions::EXPECTATIONS))];
        $rules['tool_usages.*.usage_notes'] = ['nullable', 'string', 'max:10000'];
        $rules['typical_day_items'] = ['nullable', 'array', 'max:30'];
        $rules['typical_day_items.*'] = ['array'];
        $rules['typical_day_items.*.time_label'] = ['string', 'max:255'];
        $rules['typical_day_items.*.activity'] = ['string', 'max:10000'];
        foreach (self::ROW_REQUIRED as $path) {
            array_unshift($rules[$path], 'required');
        }

        return $rules;
    }
}
