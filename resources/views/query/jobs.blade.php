@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
    $statuses = ['match' => '確認できた', 'mismatch' => '条件と異なる', 'unknown' => '未確認'];
    $badgeClasses = ['match' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'mismatch' => 'bg-amber-50 text-amber-900 ring-amber-200', 'unknown' => 'bg-slate-100 text-slate-700 ring-slate-200'];
    $labels = ['occupation' => '職種', 'region' => '勤務地', 'salary' => '年収', 'salary_min' => '掲載年収下限（万円）', 'salary_max' => '掲載年収上限（万円）', 'title' => '求人タイトル', 'description' => '求人本文'];
    $roles = ['responsibility' => '担当業務', 'required_experience' => '応募に必要な経験', 'preferred_experience' => '歓迎する経験', 'collaboration' => '他者との協働', 'other_department' => '他部署の業務', 'company_context' => '会社全体の説明', 'product_context' => '製品の説明', 'project_example' => '案件の例', 'unknown' => '文脈未確認'];
    $reasons = [
        'same_occupation' => '希望職種と掲載職種が一致しています。',
        'different_occupation' => '掲載職種は希望職種と異なります。',
        'same_prefecture' => '掲載勤務地は希望地域と一致しています。',
        'different_prefecture' => '掲載勤務地は希望地域と異なります。',
        'salary_floor_meets' => '掲載年収の下限が希望額以上です。提示・採用時の年収を保証するものではありません。',
        'salary_ceiling_below' => '掲載年収の上限が希望額を下回っています。',
        'salary_overlap' => '掲載年収レンジが希望額をまたいでいるため、条件を満たすか断定できません。',
        'presence_not_found' => '保存済み求人情報では、このツールの使用を確認できません。',
        'context_not_responsibility' => 'この技術の記載はありますが、担当業務での使用は確認できません。',
        'action_unconfirmed' => '記載はありますが、本人が使用することまでは確認できません。',
        'confirmed_tool_use' => '担当業務でこのツールを使用する記載が確認できました。',
        'missing_job_value' => '保存済み求人情報に、比較に必要な情報がありません。',
        'invalid_job_value' => '掲載情報の形式や値を確認する必要があり、条件との一致を判断できません。',
        'unsupported_user_value' => 'この希望条件は現在の比較ルールでは確認できません。',
        'unsupported_salary_request' => '希望年収の範囲指定は現在の比較ルールでは確認できません。',
        'conflicting_job_evidence' => '保存された職種と求人本文に異なる情報があり、一致を断定できません。',
        'fact_unverified' => 'この記載は比較に用いる確認条件を満たしていません。',
        'evidence_missing' => '判断に必要な根拠や確認情報が不足しています。',
        'conflicting_contexts' => '複数の記載で文脈が異なるため、担当業務での使用を断定できません。',
        'context_unknown' => '記載の文脈を確認できず、担当業務での使用を判断できません。',
        'unsupported_tier2_requirement' => 'この条件は現在の比較ルールでは確認できません。',
    ];
    $url = route('query.jobs', ['userQuery' => $query['public_id']]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>求人を比較する | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <p class="text-xl font-bold text-blue-950">JobDD</p>
        <h1 class="mt-2 text-3xl font-bold">求人を比較する</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">確認できたこと・条件と異なること・未確認の情報を、根拠とともに比べられます。最終判断はあなた自身が行います。</p>
    </div>
</header>
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
    <section aria-labelledby="conditions" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 id="conditions" class="text-lg font-bold">今回の希望条件</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">職種</dt><dd class="mt-1 font-semibold">{{ $query['occupation'] ?? '未指定' }}</dd></div>
            <div><dt class="text-slate-500">希望地域</dt><dd class="mt-1 font-semibold">{{ $query['region'] ?? '未指定' }}</dd></div>
            <div><dt class="text-slate-500">希望年収</dt><dd class="mt-1 font-semibold">{{ $query['salary_min'] !== null ? $query['salary_min'].'万円以上' : '下限未指定' }}{{ $query['salary_max'] !== null ? ' / 上限'.$query['salary_max'].'万円' : '' }}</dd></div>
        </dl>
        <p class="mt-4 text-sm">選択中ツール：{{ $selected_tools ? implode('・', array_map(fn ($key) => $tools[$key] ?? $key, $selected_tools)) : '未指定' }}</p>
        <form method="GET" action="{{ $url }}" class="mt-5 border-t border-slate-200 pt-4">
            <fieldset>
                <legend class="font-semibold">仕事で使いたいツール（任意）</legend>
                <p class="mt-1 text-sm text-slate-600">この画面だけの比較条件です。求人の絞り込みや保存は行いません。</p>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-3">
                    @foreach ($tools as $key => $name)
                        <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" name="tools[]" value="{{ $key }}" @checked(in_array($key, $selected_tools, true)) class="size-4">{{ $name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="mt-4 rounded-lg bg-blue-800 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-2">比較条件を更新</button>
        </form>
    </section>
    <div class="space-y-2 text-sm leading-6 text-slate-600">
        <p>{{ $pagination['page'] }}ページ目・{{ count($items) }}件表示。希望地域の求人を先に、各グループ内は保存ID順で表示しています。</p>
        <p>未確認は不一致を意味しません。掲載年収は提示額の保証ではなく、保存上の掲載状態は現在の募集を保証しません。</p>
    </div>
    <section aria-label="求人候補" class="space-y-6">
        @forelse ($items as $item)
            @php($job = $item['job'])
            @php($fit = $item['fit'])
            <article data-job-id="{{ $job->id }}" class="min-w-0 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
                <p class="break-words text-sm font-semibold text-slate-600">{{ $item['company_name'] }}</p>
                <h2 class="mt-2 break-words text-xl font-bold text-blue-950">{{ $job->title }}</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
                    <div><dt class="text-slate-500">掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
                    <div><dt class="text-slate-500">情報提供元</dt><dd class="break-words">{{ $job->provider_key ?? '未確認' }}</dd></div>
                </dl>
                @if ($job->source_url)
                    <a href="{{ $job->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-block text-sm font-semibold text-blue-800 underline underline-offset-4">求人元を見る<span class="sr-only">（新しいタブ）</span></a>
                @endif
                <p class="mt-2 text-xs text-slate-500">情報確認用のリンクです。応募経路の確認とは異なります。</p>
                <div class="mt-5 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-800">確認できた条件 {{ $fit['summary']['confirmed_matches'] }}</span>
                    <span class="rounded-full bg-amber-50 px-3 py-2 text-amber-900">条件と異なる点 {{ $fit['summary']['confirmed_mismatches'] }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">未確認 {{ $fit['summary']['unknowns'] }}</span>
                </div>
                <ul class="mt-5 divide-y divide-slate-200">
                    @foreach ($fit['axes'] as $axis)
                        @php($axisName = $labels[$axis['key']] ?? ($tools[str_replace('tool_use:', '', $axis['key'])] ?? '比較条件'))
                        <li class="py-4" data-status="{{ $axis['status'] }}">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-semibold">{{ $axisName }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badgeClasses[$axis['status']] ?? $badgeClasses['unknown'] }}">{{ $statuses[$axis['status']] ?? '未確認' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6">{{ $reasons[$axis['reason_code']] ?? '保存済み情報では、この条件を確認できません。' }}</p>
                            @if ($axis['evidence'])
                                <details class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                                    <summary class="cursor-pointer font-semibold text-blue-800">根拠を見る（{{ count($axis['evidence']) }}件）</summary>
                                    <div class="mt-3 space-y-4">
                                        @foreach ($axis['evidence'] as $evidence)
                                            <div class="min-w-0 border-l-2 border-slate-300 pl-3">
                                                @if ($evidence['kind'] === 'job_fact')
                                                    <p class="whitespace-pre-wrap break-words leading-6">{{ $evidence['evidence_text'] ?? '根拠本文未確認' }}</p>
                                                    <p class="mt-2 text-slate-600">記載の文脈：{{ $roles[$evidence['context']['role'] ?? 'unknown'] ?? '文脈未確認' }}</p>
                                                    <p class="mt-1 break-words text-slate-600">{{ $evidence['context']['reason'] ?? '文脈の理由は未確認です。' }}</p>
                                                @else
                                                    <p class="whitespace-pre-wrap break-words">{{ $labels[$evidence['field']] ?? '掲載情報' }}：{{ $evidence['value'] ?? '未確認' }}</p>
                                                @endif
                                                <p class="mt-2 break-words text-xs text-slate-500">情報提供元：{{ $fit['source']['provider_key'] ?? '未確認' }} / 求人元：{{ $fit['source']['url'] ?? '未確認' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <p class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">条件に該当する求人候補がありません</p>
        @endforelse
    </section>
    <nav aria-label="ページ送り" class="flex items-center justify-between gap-4 py-4 text-sm">
        @if ($pagination['has_previous'])
            <a rel="prev" href="{{ $url.'?'.http_build_query(['page' => $pagination['page'] - 1, 'tools' => $selected_tools]) }}" class="rounded-lg border border-slate-300 bg-white px-5 py-3 font-semibold text-blue-800">前へ</a>
        @else
            <span></span>
        @endif
        <span>{{ $pagination['page'] }}ページ目</span>
        @if ($pagination['has_next'])
            <a rel="next" href="{{ $url.'?'.http_build_query(['page' => $pagination['page'] + 1, 'tools' => $selected_tools]) }}" class="rounded-lg bg-blue-800 px-5 py-3 font-semibold text-white">次へ</a>
        @else
            <span></span>
        @endif
    </nav>
</main>
</body>
</html>
