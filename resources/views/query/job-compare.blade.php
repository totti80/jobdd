<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>選んだ求人を比較 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
@include('query.partials.selection-header', ['heading' => '選んだ求人を比較する'])
<main class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:py-8">
    <p class="leading-7 text-slate-600">どれが一番かをJobDDが決めるのではなく、確認できた情報を横に並べています。表示順は比較フォームで送信された順です。</p>
    <p class="leading-7 text-slate-600">未確認：求人本文から確認できない情報です。合わないという意味ではありません。</p>
    <p class="text-sm text-slate-600">保存された掲載値や記載の文脈が曖昧な場合も、未確認として表示します。</p>
    <p id="comparison-scroll-help" class="text-sm font-semibold text-blue-950">左右にスクロールできます。PCでは表の中を上下にもスクロールできます。</p>
    <div class="jobdd-comparison rounded-2xl border border-slate-200 bg-white shadow-sm" style="--job-count: {{ count($items) }}" tabindex="0" role="region" aria-label="選んだ求人の比較表" aria-describedby="comparison-scroll-help">
        <table>
            <caption class="sr-only">{{ count($items) }}求人の掲載情報と希望条件との比較</caption>
            <colgroup><col class="jobdd-axis-column">@foreach ($items as $item)<col>@endforeach</colgroup>
            <thead>
                <tr>
                    <th scope="col" class="jobdd-corner">比較項目</th>
                    @foreach ($items as $item)
                        <th scope="col" data-job-id="{{ $item['job']->id }}">
                            <p class="text-sm font-semibold text-slate-600"><x-company-name :name="$item['company_name']" /></p>
                            <h2 class="mt-2 text-xl font-bold leading-7 text-blue-950">{{ $item['job']->title }}</h2>
                            <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools]) }}" class="jobdd-link mt-2 inline-flex min-h-11 items-center">詳細を見る</a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr><th colspan="{{ count($items) + 1 }}" class="jobdd-group" scope="rowgroup">基本情報</th></tr>
                @foreach (['occupation' => '職種', 'region' => '勤務地', 'salary' => '掲載年収', 'provider_key' => '情報提供元'] as $field => $label)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        @foreach ($items as $item)
                            <td>
                                @if ($field === 'salary')
                                    {{ $item['job']->salary_min !== null ? $item['job']->salary_min.'万円' : '下限未確認' }} 〜 {{ $item['job']->salary_max !== null ? $item['job']->salary_max.'万円' : '上限未確認' }}
                                @else
                                    {{ $item['job']->getAttribute($field) ?? '未確認' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
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
                                    <p>この項目の確認結果は未確認です。</p>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
