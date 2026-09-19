<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>選んだ求人を比較 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
@include('query.partials.selection-header', ['heading' => '選んだ求人を比較する'])
<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
    <p class="mb-5 text-sm leading-6 text-slate-600">どれが一番かをJobDDが決めるのではなく、確認できた情報を横に並べています。選択した順に表示します。未確認は、求人本文から確認できない情報です。合わないという意味ではありません。</p>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" tabindex="0" role="region" aria-label="選んだ求人の比較表（横にスクロールできます）">
        <table class="w-full min-w-[720px] table-fixed border-collapse text-left text-sm">
            <caption class="sr-only">{{ count($items) }}求人の掲載情報と希望条件との比較</caption>
            <thead class="bg-blue-50">
                <tr>
                    @foreach ($items as $item)
                        <th scope="col" data-job-id="{{ $item['job']->id }}" class="border-r border-slate-200 p-5 align-top">
                            <p class="break-words text-sm font-semibold text-slate-600">{{ $item['company_name'] }}</p>
                            <h2 class="mt-2 break-words text-lg font-bold text-blue-950">{{ $item['job']->title }}</h2>
                            <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $item['job']->id, 'page' => $page, 'tools' => $selected_tools]) }}" class="mt-3 inline-block font-semibold text-blue-800 underline">詳細・応募方法を見る</a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (['occupation' => '職種', 'region' => '勤務地', 'salary' => '掲載年収', 'provider_key' => '情報提供元'] as $field => $label)
                    <tr class="border-t border-slate-200">
                        @foreach ($items as $item)
                            <td class="border-r border-slate-200 p-5 align-top">
                                <p class="mb-1 font-semibold text-slate-500">{{ $label }}</p>
                                @if ($field === 'salary')
                                    {{ $item['job']->salary_min !== null ? $item['job']->salary_min.'万円' : '下限未確認' }} 〜 {{ $item['job']->salary_max !== null ? $item['job']->salary_max.'万円' : '上限未確認' }}
                                @else
                                    {{ $item['job']->getAttribute($field) ?? '未確認' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="border-t border-slate-200">
                    @foreach ($items as $item)
                        <td class="space-y-2 border-r border-slate-200 p-5 align-top">
                            <p>確認できた条件 {{ $item['fit']['summary']['confirmed_matches'] }}</p>
                            <p>条件と異なる点 {{ $item['fit']['summary']['confirmed_mismatches'] }}</p>
                            <p>未確認 {{ $item['fit']['summary']['unknowns'] }}</p>
                        </td>
                    @endforeach
                </tr>
                @foreach ($items[0]['fit']['axes'] as $axisIndex => $axis)
                    <tr class="border-t border-slate-200">
                        @foreach ($items as $item)
                            <td class="border-r border-slate-200 px-5 align-top">
                                @include('query.partials.axis', ['axis' => $item['fit']['axes'][$axisIndex], 'fit' => $item['fit']])
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
