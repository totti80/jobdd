@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
    $url = route('query.jobs', ['userQuery' => $query['public_id']]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>求人候補を確認・比較する | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:py-8">
        @include('query.partials.brand')
        <h1 class="jobdd-page-title">求人候補を確認・比較する</h1>
        <p class="leading-7 text-slate-600">確認できたこと・条件と異なること・未確認の情報を、根拠とともに比べられます。最終判断はあなた自身が行います。</p>
        <a href="{{ route('jobs.start') }}" class="jobdd-link inline-flex min-h-11 items-center">新しい条件を入力する</a>
    </div>
</header>
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:space-y-8 lg:py-8">
    <section aria-labelledby="conditions" class="jobdd-card">
        <h2 id="conditions" class="mb-4 text-xl font-bold text-blue-950">希望条件</h2>
        @include('query.partials.condition-summary', ['query' => $query, 'selected_tools' => $selected_tools])
        <details class="jobdd-details mt-5">
            <summary>比較に使うツールを変更</summary>
            <form method="GET" action="{{ $url }}" class="space-y-4 p-4 pt-2">
                <p class="text-sm leading-6 text-slate-600">この画面だけの比較条件です。求人の絞り込みや保存は行いません。更新すると1ページ目に戻ります。</p>
                @include('query.partials.tool-selector', ['tools' => $tools, 'selected' => $selected_tools, 'idPrefix' => 'list-tools', 'toolErrors' => []])
                <button type="submit" class="jobdd-button">比較条件を更新</button>
            </form>
        </details>
    </section>
    <section aria-labelledby="status-legend" class="rounded-xl bg-slate-100 p-4 sm:p-6">
        <h2 id="status-legend" class="font-bold">確認結果の見方</h2>
        <ul class="mt-3 flex flex-wrap gap-3">
            @foreach (['match', 'mismatch', 'unknown'] as $status)<li>@include('query.partials.status-badge', ['status' => $status])</li>@endforeach
        </ul>
        <p class="mt-3 leading-7">未確認：求人本文から確認できない情報です。合わないという意味ではありません。</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">保存された掲載値や記載の文脈が曖昧な場合も、未確認として表示します。</p>
    </section>
    <div class="space-y-2 text-sm leading-6 text-slate-600">
        <p>{{ $pagination['page'] }}ページ目・{{ count($items) }}件表示。希望地域の求人を先に、同じ地域グループ内は登録順で表示しています。</p>
        <p>掲載年収は提示額の保証ではなく、保存上の掲載状態は現在の募集を保証しません。</p>
        <a href="#compare-selection" class="jobdd-link inline-flex min-h-11 items-center">比較する求人を2〜3件選ぶ</a>
    </div>
    <div data-map-toggle hidden class="flex flex-wrap gap-3" role="group" aria-label="求人候補の表示方法">
        <button type="button" data-jobdd-view="list" aria-pressed="true" aria-controls="jobdd-list-view" class="jobdd-view-button"><x-jobdd-icon name="evidence" />リスト</button>
        <button type="button" data-jobdd-view="map" aria-pressed="false" aria-controls="jobdd-map-view" class="jobdd-view-button"><x-jobdd-icon name="map" />地図（都道府県の目安）</button>
    </div>
    <div data-discovery-layout class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_288px]">
        @include('query.partials.map-view', ['map' => \App\Support\JobMapLocation::viewModel($items, $query, $pagination['page'], $selected_tools)])
        <section id="jobdd-list-view" data-list-view aria-label="求人候補" class="min-w-0 space-y-6">
            @forelse ($items as $item)
                @php($job = $item['job'])
                @php($fit = $item['fit'])
                <article data-job-id="{{ $job->id }}" class="jobdd-card">
                    <p class="text-sm font-semibold text-slate-600"><x-company-name :name="$item['company_name']" /></p>
                    <h2 class="mt-2 text-xl font-bold leading-7 text-blue-950">{{ $job->title }}</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-sm text-slate-600">勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
                        <div><dt class="text-sm text-slate-600">掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
                        @if ($job->employment_type)<div><dt class="text-sm text-slate-600">雇用形態</dt><dd>{{ $job->employment_type }}</dd></div>@endif
                    </dl>
                    @include('query.partials.fit', ['fit' => $fit, 'idPrefix' => 'list-job-'.$job->id, 'summaryOnly' => false])
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $job->id, 'page' => $pagination['page'], 'tools' => $selected_tools]) }}" class="jobdd-button">詳細を見る</a>
                        <label class="jobdd-choice" for="compare-job-{{ $job->id }}">
                            <input id="compare-job-{{ $job->id }}" form="compare-selection" aria-describedby="compare-help" type="checkbox" name="jobs[]" value="{{ $job->id }}" data-job-label="{{ \App\Support\AnonymousCompany::display($item['company_name']).'：'.$job->title }}">
                            <span>比較に追加<span class="sr-only">：{{ \App\Support\AnonymousCompany::display($item['company_name']) }} {{ $job->title }}</span></span>
                        </label>
                    </div>
                    <div class="mt-5 border-t border-slate-200 pt-4">@include('query.partials.provenance', ['source' => $fit['source']])</div>
                </article>
            @empty
                <p class="jobdd-card">このページに表示できる求人候補はありません。</p>
            @endforelse
        </section>
        <aside class="jobdd-selection-panel min-w-0 lg:sticky lg:top-6" aria-labelledby="comparison-title">
            <form id="compare-selection" method="GET" action="{{ route('query.jobs.compare', ['userQuery' => $query['public_id']]) }}" class="jobdd-card">
                <input type="hidden" name="page" value="{{ $pagination['page'] }}">
                @foreach ($selected_tools as $tool)<input type="hidden" name="tools[]" value="{{ $tool }}">@endforeach
                <h2 id="comparison-title" class="text-xl font-bold text-blue-950">比較する求人</h2>
                <p id="compare-help" class="mt-3 text-sm leading-6 text-slate-600">このページから2〜3件選んでください。選択内容は保存されません。ページを移動すると選び直しになります。</p>
                <p class="mt-4 font-semibold" data-selection-count>比較する求人を2〜3件選んでください</p>
                <ul class="mt-3" data-selected-jobs aria-label="選択中の求人"></ul>
                <button type="submit" data-compare-submit class="jobdd-button mt-4 w-full">選んだ求人を比較する</button>
                <p class="sr-only" data-selection-announcement aria-live="polite" aria-atomic="true"></p>
            </form>
        </aside>
    </div>
    <nav aria-label="ページ送り" class="flex flex-wrap items-center justify-between gap-4 py-4">
        @if ($pagination['has_previous'])
            <a rel="prev" href="{{ $url.'?'.http_build_query(['page' => $pagination['page'] - 1, 'tools' => $selected_tools]) }}" class="jobdd-link inline-flex min-h-12 items-center rounded-lg border border-slate-500 bg-white px-4">前へ</a>
        @else<span></span>@endif
        <span>{{ $pagination['page'] }}ページ目</span>
        @if ($pagination['has_next'])
            <a rel="next" href="{{ $url.'?'.http_build_query(['page' => $pagination['page'] + 1, 'tools' => $selected_tools]) }}" class="jobdd-button">次へ</a>
        @else<span></span>@endif
    </nav>
</main>
<div class="jobdd-mobile-compare" data-mobile-compare aria-label="求人比較の操作">
    <div class="min-w-0 flex-1"><p class="text-sm font-semibold" data-selection-count>比較する求人を2〜3件選んでください</p><a href="#compare-selection" class="jobdd-link inline-flex min-h-11 items-center text-sm">選択中の求人を確認</a></div>
    <button form="compare-selection" type="submit" data-compare-submit class="jobdd-button">比較する</button>
</div>
</body>
</html>
