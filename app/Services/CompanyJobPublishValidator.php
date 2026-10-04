<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Support\CompanyJobPublicationRequirements;
use App\Support\StructuredJobOptions as Options;
use Illuminate\Support\Facades\Validator;

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
        $rules = CompanyJobPublicationRequirements::rules($data);
        $labels = ['level_one.title' => '求人タイトル', 'level_one.occupation' => '職種', 'level_one.region' => '勤務地', 'level_one.employment_type' => '雇用形態', 'level_one.description' => '仕事内容', 'level_one.source_url' => '応募URL', 'level_one.application_requirements' => '最低限の応募条件', 'level_one.salary_min' => '年収下限', 'level_one.salary_max' => '年収上限', 'tool_usages' => '使用ツール', 'tool_usages.*.tool_name' => 'ツール名', 'tool_usages.*.tool_key' => 'ツール候補', 'tool_usages.*.usage_context' => 'ツール使用場面', 'tool_usages.*.experience_expectation' => 'ツール経験要件', 'typical_day_items' => 'ある一日の流れ', 'typical_day_items.*.time_label' => '時間帯', 'typical_day_items.*.activity' => '作業内容'];
        foreach (Options::FIELDS as $fields) {
            foreach ($fields as $field => $label) {
                $labels['structured_profile.'.$field] = $label;
            }
        }
        foreach (['what_made' => '代表案件で作ったもの', 'phases' => '代表案件の担当工程', 'duration' => '代表案件の期間', 'team' => '代表案件のチーム構成', 'difficult_point' => '代表案件の難しかった点'] as $field => $label) {
            $labels['structured_profile.representative_project.'.$field] = $label;
        }
        $labels['tool_usages.*.usage_notes'] = 'ツールの補足';

        return Validator::make($data, $rules, ['required' => ':attributeを入力してください。', 'required_without' => ':attributeまたはもう一方の年収を入力してください。', 'in' => ':attributeの選択内容を確認してください。'], $labels);
    }
}
