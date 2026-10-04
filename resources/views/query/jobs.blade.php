@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
    $url = route('query.jobs', ['userQuery' => $query['public_id']]);
    $nextUrl = $pagination['has_next'] ? $url.'?'.http_build_query(['page' => $pagination['page'] + 1, 'tools' => $selected_tools, 'sort' => $sort]) : '';
    $offset = ($pagination['page'] - 1) * 20;
    $previousLabel = $pagination['has_previous'] ? '前の求人を見る（'.max(1, $offset - 19).'〜'.min($offset, $total).'件）' : '前の20件';
    $nextLabel = $pagination['has_next'] ? '次の求人を見る（'.($offset + 21).'〜'.min($offset + 40, $total).'件）' : '';
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>求人候補を確認・比較する | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<x-site-header />
<main class="jobdd-results site-container">
    <section class="jobdd-results-header" aria-labelledby="results-title">
        <div class="jobdd-results-heading">
            <h1 id="results-title" class="jobdd-page-title">求人候補を確認・比較する</h1>
            <p class="jobdd-result-total">該当求人 <strong data-result-total>{{ number_format($total) }}</strong>件</p>
        </div>
        <section aria-labelledby="conditions" class="jobdd-results-conditions">
            <h2 id="conditions" class="sr-only">希望条件</h2>
            @include('query.partials.condition-summary', ['query' => $query, 'selected_tools' => $selected_tools, 'compact' => true])
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mt-2">
                <a href="{{ route('jobs.start') }}" class="jobdd-link inline-flex min-h-11 items-center">条件を変更</a>
                @include('query.partials.preference-link', ['preferencePage' => $pagination['page'], 'preferenceLinkLabel' => '詳細条件を追加'])
            </div>
            <details class="jobdd-details mt-2">
                <summary>比較に使うツールを変更</summary>
                <form method="GET" action="{{ $url }}" class="space-y-4 p-4 pt-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <p class="text-sm leading-6 text-slate-600">この画面だけの比較条件です。求人の絞り込みや保存は行いません。更新すると1ページ目に戻ります。</p>
                    @include('query.partials.tool-selector', ['tools' => $tools, 'selected' => $selected_tools, 'idPrefix' => 'list-tools', 'toolErrors' => []])
                    <button type="submit" class="jobdd-button">比較条件を更新</button>
                </form>
            </details>
        </section>
        <section aria-labelledby="status-legend" class="jobdd-results-legend">
            <h2 id="status-legend" class="sr-only">確認結果の見方</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach (['match', 'mismatch', 'unknown'] as $status)<li>@include('query.partials.status-badge', ['status' => $status])</li>@endforeach
            </ul>
            <p class="mt-2 text-sm leading-6 text-slate-600">未確認は、求人情報から判断できない項目です。合わないという意味ではありません。</p>
        </section>
    </section>
    <div data-map-toggle hidden class="flex flex-wrap gap-3" role="group" aria-label="求人候補の表示方法">
        <button type="button" data-jobdd-view="list" aria-pressed="true" aria-controls="jobdd-list-view" class="jobdd-view-button"><x-jobdd-icon name="evidence" />リスト</button>
        <button type="button" data-jobdd-view="map" aria-pressed="false" aria-controls="jobdd-map-view" class="jobdd-view-button"><x-jobdd-icon name="map" />地図（都道府県の目安）</button>
    </div>
    <div data-discovery-layout class="jobdd-results-layout">
        @include('query.partials.map-view', ['map' => \App\Support\JobMapLocation::viewModel($items, $query, $pagination['page'], $selected_tools, $sort)])
        <section id="jobdd-list-view" data-list-view aria-label="求人候補" class="min-w-0">
            <div class="jobdd-sort-row">
            <p id="result-range" data-result-range tabindex="-1" role="status" aria-live="polite" aria-atomic="true" class="jobdd-result-range">{{ count($items) ? ($offset + 1).'〜'.($offset + count($items)).'件を表示' : '表示できる求人はありません' }}</p>
            <form method="GET" action="{{ $url }}" class="jobdd-sort-form">
                @foreach ($selected_tools as $tool)<input type="hidden" name="tools[]" value="{{ $tool }}">@endforeach
                <label for="job-sort" class="font-semibold text-sm">表示順</label>
                <select id="job-sort" name="sort" aria-describedby="sort-help" class="rounded-lg border-slate-300 text-sm">
                    @foreach (\App\Services\JobListSort::OPTIONS as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach
                </select>
                <button type="submit" class="site-button-secondary">適用</button>
            </form>
            </div>
            <p id="sort-help" class="mb-4 text-xs leading-6 text-slate-600">「希望条件に近い順」は職種・勤務地・年収・CAD / Toolの確認結果を使用します。新着順は掲載日時、不明なら初回確認日時を使用します。</p>
            <div class="jobdd-result-cards">
                @forelse ($items as $item)
                    <x-job-result-card :sort="$sort" :item="$item" :query="$query" :page="$pagination['page']" :selected-tools="$selected_tools" />
                @empty
                    <p class="jobdd-card">このページに表示できる求人候補はありません。</p>
                @endforelse
            </div>
        </section>
        @include('query.partials.compare-panel')
    </div>
    <nav data-result-pagination aria-label="ページ送り" class="jobdd-result-pagination">
        @if ($pagination['has_previous'])
            <a rel="prev" href="{{ $url.'?'.http_build_query(['page' => $pagination['page'] - 1, 'tools' => $selected_tools, 'sort' => $sort]) }}" class="jobdd-pagination-button order-2 sm:order-1">← {{ $previousLabel }}</a>
        @else
            <span role="link" aria-disabled="true" class="jobdd-pagination-button order-2 sm:order-1">← {{ $previousLabel }}</span>
        @endif
        <span aria-current="page" class="order-1 col-span-2 text-center font-semibold sm:order-2 sm:col-span-1">{{ $pagination['page'] }}ページ目</span>
        @if ($pagination['has_next'])
            <a rel="next" href="{{ $nextUrl }}" class="jobdd-pagination-button order-3">{{ $nextLabel }} →</a>
        @else
            <span role="link" aria-disabled="true" class="jobdd-pagination-button order-3">次の20件 →</span>
        @endif
    </nav>
    <p class="text-xs leading-6 text-slate-600">掲載年収・募集状況は保証されません。根拠・掲載元は求人詳細で確認できます。最終判断はあなた自身で。</p>
</main>
<button type="button" data-back-to-top class="jobdd-back-to-top" aria-label="ページ上部へ戻る" hidden><span aria-hidden="true">↑</span> 上へ</button>
<x-site-footer />
</body>
</html>
