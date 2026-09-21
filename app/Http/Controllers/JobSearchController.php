<?php

namespace App\Http\Controllers;

use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JobSearchController extends Controller
{
    public const OCCUPATIONS = ['機械設計', '電気設計'];

    public const REGIONS = ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'];

    public function create()
    {
        return $this->form();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->only(['occupation', 'region', 'salary_min', 'tools', 'custom_tools']), [
            'occupation' => ['required', 'string', Rule::in(self::OCCUPATIONS)],
            'region' => ['required', 'string', Rule::in(self::REGIONS)],
            'salary_min' => ['nullable', 'integer', 'between:1,10000'],
            'tools' => ['sometimes', 'array', 'list', 'max:7'],
            'tools.*' => ['required', 'string', 'distinct', Rule::in(array_keys(JobDecisionUseCaseService::TOOLS))],
            'custom_tools' => ['nullable', 'string', 'max:500'],
        ], [
            'occupation.*' => '職種は機械設計・電気設計から選んでください。',
            'region.*' => '希望地域は近畿6府県から選んでください。',
            'salary_min.*' => '希望年収は1〜10,000万円の整数で入力するか、空欄にしてください。',
            'tools.*' => 'ツールは表示されている7種類から重複なく選んでください。',
            'tools.*.*' => 'ツールは表示されている7種類から重複なく選んでください。',
            'custom_tools.*' => 'その他のCAD・ツールは500文字以内の文字列で入力してください。',
        ]);
        if ($validator->fails()) {
            // Render directly: no old input or error payload needs to be flashed to the session.
            $values = [];
            foreach (['occupation', 'region', 'salary_min', 'custom_tools'] as $field) {
                $values[$field] = is_scalar($request->input($field)) ? (string) $request->input($field) : '';
            }
            $values['tools'] = is_array($request->input('tools'))
                ? array_values(array_intersect(array_keys(JobDecisionUseCaseService::TOOLS), array_filter($request->input('tools'), 'is_string')))
                : [];

            $fieldErrors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $key = explode('.', $field)[0];
                $fieldErrors[$key] = array_merge($fieldErrors[$key] ?? [], $messages);
            }

            return $this->form($values, $validator->errors()->all(), 422, $fieldErrors);
        }

        $input = $validator->validated();
        $query = UserQuery::create([
            'public_id' => (string) Str::uuid(),
            'session_token' => Str::random(64),
            'raw_text' => '新JobDD 条件入力',
            'occupation' => $input['occupation'],
            'region' => $input['region'],
            'salary_min' => $input['salary_min'] ?? null,
            // Keep free-form intent separate from the seven confirmed tool requirements.
            'detailed_skills' => isset($input['custom_tools']) ? ['custom_tools' => $input['custom_tools']] : null,
        ]);
        $request->session()->put('jobdd_query_token_'.$query->public_id, $query->session_token);

        return redirect()->route('query.jobs', ['userQuery' => $query->public_id, 'tools' => $input['tools'] ?? []])
            ->header('Cache-Control', 'private, no-store');
    }

    private function form(array $values = [], array $inputErrors = [], int $status = 200, array $fieldErrors = [])
    {
        return response()->view('query.start', [
            'occupations' => self::OCCUPATIONS, 'regions' => self::REGIONS,
            'tools' => JobDecisionUseCaseService::TOOLS, 'values' => $values, 'inputErrors' => $inputErrors,
            'fieldErrors' => $fieldErrors,
        ], $status)->header('Cache-Control', 'private, no-store');
    }
}
