@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
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
    <form id="compare-selection" method="GET" action="{{ route('query.jobs.compare', ['userQuery' => $query['public_id']]) }}" class="rounded-xl bg-blue-50 p-4">
        <input type="hidden" name="page" value="{{ $pagination['page'] }}">
        @foreach ($selected_tools as $tool)
            <input type="hidden" name="tools[]" value="{{ $tool }}">
        @endforeach
        <p id="compare-help" class="text-sm leading-6">このページから比較する求人を2〜3件選んでください。選択内容は保存されません。</p>
        <button type="submit" class="mt-3 rounded-lg bg-blue-800 px-5 py-3 text-sm font-semibold text-white">選んだ求人を比較する</button>
    </form>
    <section aria-label="求人候補" class="space-y-6">
        @forelse ($items as $item)
            @php($job = $item['job'])
            @php($fit = $item['fit'])
            <article data-job-id="{{ $job->id }}" class="min-w-0 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <label class="flex min-h-10 items-center gap-2"><input form="compare-selection" aria-describedby="compare-help" type="checkbox" name="jobs[]" value="{{ $job->id }}" class="size-4">比較に追加<span class="sr-only">：{{ $job->title }}</span></label>
                    <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $job->id, 'page' => $pagination['page'], 'tools' => $selected_tools]) }}" class="font-semibold text-blue-800 underline underline-offset-4">詳細を見る</a>
                </div>
                <p class="break-words text-sm font-semibold text-slate-600">{{ $item['company_name'] }}</p>
                <h2 class="mt-2 break-words text-xl font-bold text-blue-950">{{ $job->title }}</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
                    <div><dt class="text-slate-500">掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
                    <div><dt class="text-slate-500">情報提供元</dt><dd class="break-words">{{ $job->provider_key ?? '未確認' }}</dd></div>
                </dl>
                @if (\App\Support\JobDecisionPresenter::safeUrl($job->source_url))
                    <a href="{{ $job->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-block text-sm font-semibold text-blue-800 underline underline-offset-4">求人元を見る<span class="sr-only">（新しいタブ）</span></a>
                @endif
                <p class="mt-2 text-xs text-slate-500">情報確認用のリンクです。応募経路の確認とは異なります。</p>
                @include('query.partials.fit', ['fit' => $fit])

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
