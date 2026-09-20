<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobDetailUseCaseService;
use App\Services\JobSelectionUseCaseService;
use App\Support\AgencyDecisionPresenter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JobDecisionController extends Controller
{
    public function __invoke(Request $request, UserQuery $userQuery, JobDecisionUseCaseService $useCase)
    {
        [$input, $requirements] = $this->input($request, $userQuery);
        try {
            $data = $useCase->run($userQuery, (int) ($input['page'] ?? 1), $requirements);
        } catch (InvalidArgumentException $e) {
            abort(422, '比較条件を確認してください。職種は機械設計・電気設計、地域は近畿6府県に対応しています。');
        }

        return response()->view('query.jobs', $data)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, UserQuery $userQuery, string $job, JobDetailUseCaseService $useCase)
    {
        [$input, $requirements] = $this->input($request, $userQuery);
        abort_if(! ctype_digit($job) || strlen($job) > strlen((string) PHP_INT_MAX)
            || (strlen($job) === strlen((string) PHP_INT_MAX) && strcmp($job, (string) PHP_INT_MAX) > 0), 404);
        try {
            $data = $useCase->run($userQuery, (int) $job, $requirements);
        } catch (InvalidArgumentException $e) {
            abort(422, '比較条件を確認してください。');
        }

        return response()->view('query.job-show', [...$data, 'page' => (int) ($input['page'] ?? 1)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function compare(Request $request, UserQuery $userQuery, JobSelectionUseCaseService $useCase)
    {
        [$input, $requirements] = $this->input($request, $userQuery, true);
        try {
            $data = $useCase->run($userQuery, array_map('intval', $input['jobs']), $requirements);
        } catch (InvalidArgumentException $e) {
            abort(422, '比較条件を確認してください。');
        }

        return response()->view('query.job-compare', [...$data, 'page' => (int) ($input['page'] ?? 1)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function agencies(Request $request, UserQuery $userQuery, AgencyDecisionPresenter $presenter)
    {
        [$input, $requirements] = $this->input($request, $userQuery);
        $items = Agency::query()->orderBy('id')
            ->with(['facts' => fn ($facts) => $facts->orderBy('id'), 'facts.source'])->get()
            ->filter(fn ($agency) => $presenter->candidate($agency))
            ->map(fn ($agency) => $presenter->present($agency, $userQuery))->values();

        return response()->view('query.agencies', [
            'query' => $userQuery->only(['public_id', 'occupation', 'region', 'salary_min', 'salary_max']),
            'selected_tools' => array_column($requirements['desired'], 'fact_key'),
            'page' => (int) ($input['page'] ?? 1),
            'items' => $items,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function input(Request $request, UserQuery $userQuery, bool $compare = false): array
    {
        $token = $request->session()->get('jobdd_query_token_'.$userQuery->public_id);
        abort_unless(is_string($token) && $token !== '' && is_string($userQuery->session_token)
            && $userQuery->session_token !== '' && hash_equals($userQuery->session_token, $token), 404);
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.intdiv(PHP_INT_MAX, 20)],
            'tools' => ['sometimes', 'array', 'list', 'max:7'],
            'tools.*' => ['required', 'string', 'distinct', Rule::in(array_keys(JobDecisionUseCaseService::TOOLS))],
        ];
        if ($compare) {
            $rules['jobs'] = ['required', 'array', 'list', 'min:2', 'max:3'];
            $rules['jobs.*'] = ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX, 'distinct'];
        }
        $validator = Validator::make($request->query(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->view('query.input-error', [
                'public_id' => $userQuery->public_id,
                'message' => $compare
                    ? '比較する求人を重複なく2〜3件選んでください。ページ番号・ツールの指定も確認してください。'
                    : 'ページ番号またはツールの指定を確認してください。',
            ], 422)->header('Cache-Control', 'private, no-store'));
        }
        $input = $validator->validated();
        $requirements = ['desired' => array_map(fn ($key) => ['fact_key' => $key, 'action' => 'use'], $input['tools'] ?? []), 'hard_axes' => []];

        return [$input, $requirements];
    }
}
