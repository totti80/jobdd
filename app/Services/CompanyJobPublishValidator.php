<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Support\StructuredJobOptions as Options;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyJobPublishValidator
{
    public function __construct(private CompanyJobAuthoringData $authoring) {}

    public function errors(JobPosting $job): array
    {
        return $this->validator($job)->errors()->all();
    }

    public function validate(JobPosting $job): void
    {
        $this->validator($job)->validate();
    }

    private function validator(JobPosting $job): \Illuminate\Validation\Validator
    {
        $data = $this->authoring->read($job);
        $rules = [];
        foreach (['title', 'occupation', 'region', 'employment_type', 'description', 'application_requirements'] as $field) {
            $rules['level_one.'.$field] = ['required', 'string', 'max:'.(in_array($field, ['description', 'application_requirements']) ? 10000 : 255)];
        }
        $rules['level_one.occupation'][] = Rule::in(['機械設計', '電気設計']);
        $rules['level_one.source_url'] = ['required', 'url:http,https', 'max:2048'];
        $rules['level_one.salary_min'] = ['nullable', 'required_without:level_one.salary_max', 'integer', 'between:1,10000'];
        $rules['level_one.salary_max'] = ['nullable', 'required_without:level_one.salary_min', 'integer', 'between:1,10000'];
        if (filled($job->salary_min) && filled($job->salary_max)) {
            $rules['level_one.salary_max'][] = 'gte:level_one.salary_min';
        }
        foreach (['design_target', 'initial_assignment', 'work_style', 'difficult_points', 'customer_contact_note', 'manufacturing_relation_note', 'site_relation_note'] as $field) {
            $rules['structured_profile.'.$field] = ['required', 'string', 'max:10000'];
        }
        foreach (['design_phases', 'collaborators'] as $field) {
            $rules['structured_profile.'.$field] = ['required', 'array', 'min:1'];
            $rules['structured_profile.'.$field.'.*'] = ['required', Rule::in(array_keys(Options::options($field)))];
        }
        foreach (['customer_contact_frequency', 'manufacturing_relation_frequency', 'site_relation_frequency'] as $field) {
            $rules['structured_profile.'.$field] = ['required', Rule::in(array_keys(Options::FREQUENCIES))];
        }
        $rules['tool_usages'] = ['required', 'array', 'min:1', 'max:30'];
        $rules['tool_usages.*.tool_name'] = ['required', 'string', 'max:255'];
        $rules['tool_usages.*.tool_key'] = ['nullable', Rule::in(array_keys(Options::TOOLS))];
        $rules['tool_usages.*.usage_context'] = ['required', Rule::in(array_keys(Options::USAGES))];
        $rules['tool_usages.*.experience_expectation'] = ['required', Rule::in(array_keys(Options::EXPECTATIONS))];
        $rules['typical_day_items'] = ['required', 'array', 'min:1', 'max:30'];
        $rules['typical_day_items.*.time_label'] = ['required', 'string', 'max:255'];
        $rules['typical_day_items.*.activity'] = ['required', 'string', 'max:10000'];
        $labels = ['level_one.title' => '求人タイトル', 'level_one.occupation' => '職種', 'level_one.region' => '勤務地', 'level_one.employment_type' => '雇用形態', 'level_one.description' => '仕事内容', 'level_one.source_url' => '応募URL', 'level_one.application_requirements' => '最低限の応募条件', 'level_one.salary_min' => '年収下限', 'level_one.salary_max' => '年収上限', 'tool_usages' => '使用ツール', 'tool_usages.*.tool_name' => 'ツール名', 'tool_usages.*.tool_key' => 'ツール候補', 'tool_usages.*.usage_context' => 'ツール使用場面', 'tool_usages.*.experience_expectation' => 'ツール経験要件', 'typical_day_items' => 'ある一日の流れ', 'typical_day_items.*.time_label' => '時間帯', 'typical_day_items.*.activity' => '作業内容'];
        foreach (Options::FIELDS as $fields) {
            foreach ($fields as $field => $label) {
                $labels['structured_profile.'.$field] = $label;
            }
        }

        return Validator::make($data, $rules, ['required' => ':attributeを入力してください。', 'required_without' => ':attributeまたはもう一方の年収を入力してください。', 'in' => ':attributeの選択内容を確認してください。'], $labels);
    }
}
