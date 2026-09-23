<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\JobPosting;
use App\Services\CompanyWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyJobBasicRequest extends FormRequest
{
    private ?Company $workspaceCompany = null;

    public function company(): Company
    {
        $this->workspaceCompany ??= app(CompanyWorkspace::class)->companyFor($this->user());
        abort_unless($this->workspaceCompany, 403);

        return $this->workspaceCompany;
    }

    public function authorize(): bool
    {
        $job = $this->route('jobPosting');

        return $job instanceof JobPosting
            ? Gate::allows('update', $job)
            : Gate::allows('create', [JobPosting::class, $this->company()]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', Rule::in(['機械設計', '電気設計'])],
            'region' => ['nullable', 'string', 'max:255'],
            'salary_min' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'salary_max' => ['nullable', 'integer', 'min:1', 'max:10000', Rule::when($this->filled('salary_min'), ['gte:salary_min'])],
            'employment_type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'application_requirements' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => '求人タイトル', 'occupation' => '職種', 'region' => '勤務地',
            'salary_min' => '想定年収の下限', 'salary_max' => '想定年収の上限',
            'employment_type' => '雇用形態', 'description' => '仕事内容（概要）',
            'source_url' => '応募URL', 'application_requirements' => '最低限の応募条件',
        ];
    }
}
