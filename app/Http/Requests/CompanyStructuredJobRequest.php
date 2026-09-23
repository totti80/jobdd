<?php

namespace App\Http\Requests;

use App\Support\StructuredJobOptions as Options;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyStructuredJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('jobPosting'));
    }

    public function rules(): array
    {
        $step = (int) $this->route('step');
        $rules = ['navigation' => ['nullable', Rule::in(['save', 'next', 'back'])]];
        foreach (Options::FIELDS[$step] as $field => $label) {
            if ($field === 'representative_project') {
                continue;
            }
            $options = Options::options($field);
            if (in_array($field, ['design_phases', 'collaborators'])) {
                $rules[$field] = ['nullable', 'array', 'max:20'];
                $rules[$field.'.*'] = ['string', 'distinct', Rule::in(array_keys($options))];
            } else {
                $rules[$field] = $options ? ['nullable', 'string', Rule::in(array_keys($options))] : ['nullable', 'string', 'max:10000'];
            }
        }
        if ($step === 2) {
            $rules['tools'] = ['nullable', 'array', 'max:30'];
            $rules['tools.*'] = ['array:tool_key,tool_name,usage_context,experience_expectation,usage_notes'];
            foreach (['tool_key', 'tool_name', 'usage_context', 'experience_expectation', 'usage_notes'] as $field) {
                $options = Options::options($field);
                $rules['tools.*.'.$field] = $options ? ['nullable', 'string', Rule::in(array_keys($options))] : ['nullable', 'string', $field === 'usage_notes' ? 'max:10000' : 'max:255'];
            }
        }
        if ($step === 5) {
            $rules['typical_day'] = ['nullable', 'array', 'max:30'];
            $rules['typical_day.*'] = ['array:time_label,activity'];
            $rules['typical_day.*.time_label'] = ['nullable', 'string', 'max:255'];
            $rules['typical_day.*.activity'] = ['nullable', 'string', 'max:10000'];
            $rules['representative_project'] = ['nullable', 'array:what_made,phases,duration,team,difficult_point'];
            foreach (['what_made', 'duration', 'team', 'difficult_point'] as $field) {
                $rules['representative_project.'.$field] = ['nullable', 'string', 'max:10000'];
            }
            $rules['representative_project.phases'] = ['nullable', 'array', 'max:20'];
            $rules['representative_project.phases.*'] = ['string', 'distinct', Rule::in(array_keys(Options::PHASES))];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return Options::FIELDS[(int) $this->route('step')] + [
            'tools' => '使用ツール', 'tools.*.tool_key' => 'ツールの候補',
            'tools.*.tool_name' => 'ツール名', 'tools.*.usage_context' => '使用場面',
            'tools.*.experience_expectation' => '経験の期待', 'tools.*.usage_notes' => 'ツールの補足',
            'typical_day' => 'ある一日の流れ', 'typical_day.*.time_label' => '時間帯',
            'typical_day.*.activity' => '作業内容',
        ];
    }
}
