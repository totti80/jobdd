<?php

namespace App\Http\Controllers;

use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class JobDecisionController extends Controller
{
    public function __invoke(Request $request, UserQuery $userQuery, JobDecisionUseCaseService $useCase)
    {
        $token = $request->session()->get('jobdd_query_token_'.$userQuery->public_id);
        abort_unless(is_string($token) && $token !== '' && is_string($userQuery->session_token)
            && $userQuery->session_token !== '' && hash_equals($userQuery->session_token, $token), 404);

        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.intdiv(PHP_INT_MAX, 20)],
            'tools' => ['sometimes', 'array', 'list', 'max:7'],
            'tools.*' => ['required', 'string', 'distinct', Rule::in(array_keys(JobDecisionUseCaseService::TOOLS))],
        ]);
        abort_if($validator->fails(), 422, 'ページ番号またはツールの指定を確認してください。');
        $input = $validator->validated();
        $requirements = ['desired' => array_map(fn ($key) => ['fact_key' => $key, 'action' => 'use'], $input['tools'] ?? []), 'hard_axes' => []];
        try {
            $data = $useCase->run($userQuery, (int) ($input['page'] ?? 1), $requirements);
        } catch (InvalidArgumentException $e) {
            abort(422, '比較条件を確認してください。職種は機械設計・電気設計、地域は近畿6府県に対応しています。');
        }

        return response()->view('query.jobs', $data)->header('Cache-Control', 'private, no-store');
    }
}
