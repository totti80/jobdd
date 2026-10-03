<!DOCTYPE html>
<html lang="ja">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>選んだ求人を比較 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
@include('query.partials.selection-header', ['heading' => '選んだ求人を比較する', 'compactConditions' => true])
<main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:py-8">
    <p class="leading-7 text-slate-600">どれが一番かをJobDDが決めるのではなく、確認できた情報を横に並べています。表示順は選択した順です。</p>
    <p class="leading-7 text-slate-600">未確認：保存済み情報から確認できない情報です。合わないという意味ではありません。</p>
    <p class="text-sm text-slate-600">保存された掲載値や記載の文脈が曖昧な場合も、未確認として表示します。</p>
    @if ($comparison_preferences)
        <section class="jobdd-card" data-comparison-preferences aria-labelledby="preferences-title">
            <h2 id="preferences-title" class="text-xl font-bold">あなたの希望</h2>
            <p>詳細希望は比較材料です。求人ごとの適合判定には使いません。</p>
            <dl>@foreach ($comparison_preferences as $preference)<div class="mt-3"><dt class="font-semibold">{{ $preference['label'] }}</dt><dd class="whitespace-pre-line break-words">{{ $preference['preference'] }}</dd></div>@endforeach</dl>
        </section>
    @endif
    <p class="text-sm font-semibold">比較中 {{ count($items) }}求人</p>
    <ol class="flex flex-wrap gap-3">@foreach ($items as $item)<li class="max-w-full break-words rounded-lg bg-blue-50 px-3 py-2 text-sm">{{ $loop->iteration }}. {{ $item['job']->title }}</li>@endforeach</ol>
    <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools, 'sort' => $sort ?? 'fit']) }}#compare-selection">比較対象を追加・解除する</a>
    <p id="comparison-scroll-help" class="text-sm font-semibold text-blue-950">左右にスクロールできます。表の中を上下にもスクロールできます。</p>
    <div class="jobdd-comparison rounded-2xl border border-slate-200 bg-white shadow-sm" style="--job-count: {{ count($items) }}" tabindex="0" role="region" aria-label="選んだ求人の比較表" aria-describedby="comparison-scroll-help">
        <table>
            <caption class="sr-only">{{ count($items) }}求人の掲載情報と希望条件との比較</caption>
            <colgroup><col class="jobdd-axis-column">@foreach ($items as $item)<col>@endforeach</colgroup>
            <thead class="jobdd-comparison-header">
                <tr>
                    <th scope="col" class="jobdd-corner">比較項目</th>
                    @foreach ($items as $item)
                        <th scope="col" data-job-id="{{ $item['job']->id }}">
                            <p class="line-clamp-2 text-sm font-semibold text-slate-600"><x-company-name :name="$item['company_name']" /></p>
                            <h2 class="mt-2 line-clamp-2 text-xl font-bold leading-7 text-blue-950">{{ $item['job']->title }}</h2>
                            <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools, 'sort' => $sort ?? 'fit']) }}" class="jobdd-link mt-2 inline-flex min-h-11 items-center">詳細を見る</a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr><th colspan="{{ count($items) + 1 }}" class="jobdd-group" scope="rowgroup">基本情報</th></tr>
                @foreach (['occupation' => '職種', 'region' => '勤務地', 'salary' => '掲載年収'] as $field => $label)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        @foreach ($items as $item)
                            <td>
                                @if ($field === 'salary')
                                    <span @class(['jobdd-comparison-unknown' => $item['job']->salary_min === null])>{{ $item['job']->salary_min !== null ? $item['job']->salary_min.'万円' : '下限未確認' }}</span> 〜 <span @class(['jobdd-comparison-unknown' => $item['job']->salary_max === null])>{{ $item['job']->salary_max !== null ? $item['job']->salary_max.'万円' : '上限未確認' }}</span>
                                @else
                                    <span @class(['jobdd-comparison-unknown' => $item['job']->getAttribute($field) === null])>{{ $item['job']->getAttribute($field) ?? '未確認' }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tbody data-comparison-material>
                <tr><th colspan="{{ count($items) + 1 }}" class="jobdd-group" scope="rowgroup">仕事の中身（比較材料）</th></tr>
                @foreach ($items[0]['comparison']['rows'] as $key => $row)
                    <tr><th scope="row">{{ $row['label'] }}</th>
                        @foreach ($items as $item)
                            <td>
                                <div @class(['flex flex-wrap gap-2' => $key === 'design_phases'])>
                                    @forelse ($item['comparison']['rows'][$key]['values'] as $value)<p @class(['jobdd-scope-badge' => $key === 'design_phases', 'mb-2 break-words' => $key !== 'design_phases', 'line-clamp-2' => !in_array($key, ['design_phases', 'tools'])])>{{ $value }}</p>@empty<p class="jobdd-comparison-unknown">未確認</p>@endforelse
                                </div>
                                <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools, 'sort' => $sort ?? 'fit']) }}#{{ $item['comparison']['rows'][$key]['anchor'] }}">詳細を見る</a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr><th scope="row">情報源 / Provenance</th>
                    @foreach ($items as $item)
                        <td>
                            @if ($item['comparison']['snapshot'])
                                <p>企業提供情報</p><p>JobDD公開確認済み</p><p class="text-sm">公開可能な状態の確認です。事実の保証ではありません。</p>
                                <p>{{ $item['comparison']['publisher'] }}</p><p>公開確認・更新：{{ $item['comparison']['published_at'] }}</p>
                                <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('jobs.provenance', $item['job']->id) }}">情報源を見る</a>
                            @else
                                <p>外部情報</p><p>{{ $item['job']->provider_key ?? '情報提供元未確認' }}</p>
                            @endif
                            <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools, 'sort' => $sort ?? 'fit']) }}#{{ $item['comparison']['snapshot'] ? 'evidence-title' : 'source-title' }}">Evidenceを見る</a>
                        </td>
                    @endforeach
                </tr>
                <tr><th scope="row">応募方法</th>
                    @foreach ($items as $item)<td>
                        @forelse ($item['comparison']['routes'] as $label)<p>{{ $label }}</p>@empty<p class="jobdd-comparison-unknown">利用可能な応募方法は未確認</p>@endforelse
                        <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('routes.show', $item['job']->id) }}">応募方法を見る</a>
                    </td>@endforeach
                </tr>
                <tr><th scope="row">ある一日・代表的な案件</th>
                    @foreach ($items as $item)<td><a class="jobdd-link inline-flex min-h-11 items-center" href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools, 'sort' => $sort ?? 'fit']) }}#{{ $item['comparison']['snapshot'] ? 'typical-day-title' : 'presence-title' }}">求人詳細で確認する</a></td>@endforeach
                </tr>
            </tbody>
            <tbody>
                <tr><th colspan="{{ count($items) + 1 }}" class="jobdd-group" scope="rowgroup">確認結果</th></tr>
                <tr><th scope="row">確認結果の項目数</th>
                    @foreach ($items as $item)<td>@include('query.partials.fit', ['fit' => $item['fit'], 'summaryOnly' => true])</td>@endforeach
                </tr>
            </tbody>
            <tbody>
                <tr><th colspan="{{ count($items) + 1 }}" class="jobdd-group" scope="rowgroup">条件別の確認結果</th></tr>
                @foreach ($items[0]['fit']['axes'] as $axisIndex => $axis)
                    @php($axisName = \App\Support\JobDecisionPresenter::LABELS[$axis['key']] ?? (\App\Services\JobDecisionUseCaseService::TOOLS[str_replace('tool_use:', '', $axis['key'])] ?? '比較条件'))
                    <tr>
                        <th scope="row">{{ $axisName }}</th>
                        @foreach ($items as $item)
                            <td>
                                @if (($item['fit']['axes'][$axisIndex]['key'] ?? null) === $axis['key'])
                                    @include('query.partials.axis', ['axis' => $item['fit']['axes'][$axisIndex], 'fit' => $item['fit'], 'showHeading' => false, 'idPrefix' => 'compare-job-'.$item['job']->id, 'jobLabel' => \App\Support\AnonymousCompany::display($item['company_name']).' '.$item['job']->title])
                                @else
                                    <p class="jobdd-comparison-unknown">この項目の確認結果は未確認です。</p>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</main>
<x-site-footer />
</body>
</html>
