<?php

namespace App\Http\Controllers;

use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Support\SeekerPreferences;
use App\Support\StructuredJobOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserQueryPreferenceController extends Controller
{
    public function edit(Request $request, UserQuery $userQuery)
    {
        $this->authorizeQuery($request, $userQuery);
        $context = $this->context($request);

        return $this->form($userQuery, $context, SeekerPreferences::read($userQuery));
    }

    public function update(Request $request, UserQuery $userQuery)
    {
        $this->authorizeQuery($request, $userQuery);
        $context = $this->context($request);
        $validator = Validator::make($request->only(['design_phases', ...array_keys(SeekerPreferences::RELATIONS), 'work_style']), SeekerPreferences::rules(), [
            'design_phases.*' => '担当工程は表示された選択肢から重複なく選んでください。',
            'design_phases.*.*' => '担当工程は表示された選択肢から重複なく選んでください。',
            'work_style.*' => '仕事の進め方は2,000文字以内の文章で入力してください。',
            'customer_contact.*' => '顧客との関わりは表示された選択肢から選んでください。',
            'manufacturing_relation.*' => '製造との関わりは表示された選択肢から選んでください。',
            'site_relation.*' => '現場との関わりは表示された選択肢から選んでください。',
        ]);
        if ($validator->fails()) {
            return $this->form($userQuery, $context, SeekerPreferences::values($request->all()), $validator->errors()->all(), 422);
        }
        $preferences = array_filter(SeekerPreferences::values($validator->validated()), fn ($value) => filled($value));
        DB::transaction(function () use ($request, $userQuery, $preferences) {
            $query = UserQuery::query()->lockForUpdate()->findOrFail($userQuery->id);
            $this->authorizeQuery($request, $query);
            $skills = $query->detailed_skills;
            // Never discard a legacy value whose shape cannot be safely merged.
            abort_unless($skills === null || is_array($skills), 409, '保存済み情報の形式を確認する必要があります。既存の希望は変更していません。');
            $skills ??= [];
            unset($skills[SeekerPreferences::STORAGE_KEY]);
            if ($preferences !== []) {
                $skills[SeekerPreferences::STORAGE_KEY] = $preferences;
            }
            $query->update(['detailed_skills' => $skills ?: null]);
        });

        return redirect($this->returnUrl($userQuery, $context))->header('Cache-Control', 'private, no-store');
    }

    private function authorizeQuery(Request $request, UserQuery $query): void
    {
        $token = $request->session()->get('jobdd_query_token_'.$query->public_id);
        abort_unless(is_string($token) && $token !== '' && is_string($query->session_token)
            && $query->session_token !== '' && hash_equals($query->session_token, $token), 404);
    }

    private function context(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.intdiv(PHP_INT_MAX, 20)],
            'tools' => ['sometimes', 'array', 'list', 'max:7'],
            'tools.*' => ['required', 'string', 'distinct', Rule::in(array_keys(JobDecisionUseCaseService::TOOLS))],
            'return_job' => ['sometimes', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ]);
        abort_if($validator->fails(), 422, '戻り先のページ・ツール指定を確認してください。');

        return $validator->validated();
    }

    private function returnUrl(UserQuery $query, array $context): string
    {
        return route(isset($context['return_job']) ? 'query.jobs.show' : 'query.jobs', [
            'userQuery' => $query->public_id, 'page' => $context['page'] ?? 1, 'tools' => $context['tools'] ?? [],
            ...(isset($context['return_job']) ? ['job' => $context['return_job']] : []),
        ]);
    }

    private function form(UserQuery $userQuery, array $context, array $values, array $inputErrors = [], int $status = 200)
    {
        return response()->view('query.preferences', [
            'query' => $userQuery->only(['public_id', 'occupation', 'region', 'salary_min', 'salary_max']),
            'custom_tools' => $userQuery->detailed_skills['custom_tools'] ?? null,
            'page' => (int) ($context['page'] ?? 1), 'selected_tools' => $context['tools'] ?? [],
            'values' => $values, 'inputErrors' => $inputErrors,
            'phases' => StructuredJobOptions::PHASES, 'relations' => SeekerPreferences::RELATIONS, 'tendencies' => SeekerPreferences::TENDENCIES,
            'saveUrl' => route('query.preferences.update', ['userQuery' => $userQuery->public_id, ...$context]),
            'returnUrl' => $this->returnUrl($userQuery, $context),
        ], $status)->header('Cache-Control', 'private, no-store');
    }
}
