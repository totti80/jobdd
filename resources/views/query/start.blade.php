<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>希望条件を入力 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:py-8">
        <p class="text-xl font-bold text-blue-950">JobDD <span class="ml-2 text-sm font-normal text-slate-600">根拠とともに、求人を比較</span></p>
        <h1 class="jobdd-page-title">希望条件から、求人を比較する</h1>
        <p class="leading-7">希望条件と求人情報を照らし合わせ、確認できたこと・条件と異なること・未確認を整理します。</p>
        <p class="text-sm leading-6 text-slate-600">根拠を確認し、最終判断はあなた自身で行えます。</p>
        <p class="text-sm leading-6 text-slate-600">条件入力 → 求人一覧 → 詳細・根拠 → 比較 → 応募方法</p>
    </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:py-8">
    @if ($inputErrors)
        <section role="alert" aria-labelledby="input-errors" tabindex="-1" data-input-errors class="mb-6 rounded-xl border border-red-700 bg-red-50 p-4 text-red-800">
            <h2 id="input-errors" class="font-bold">入力内容を確認してください</h2>
            <ul class="mt-2 space-y-2">
                @foreach ($fieldErrors as $field => $messages)
                    @php($target = ['occupation' => 'occupation-0', 'tools' => 'start-tools-autocad'][$field] ?? $field)
                    <li><a href="#{{ $target }}" data-error-target="{{ $target }}" class="inline-flex min-h-11 items-center underline">{{ implode(' ', array_unique($messages)) }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
    <form method="POST" action="{{ route('jobs.store') }}" class="jobdd-card space-y-6">
        @csrf
        <fieldset aria-describedby="occupation-help{{ isset($fieldErrors['occupation']) ? ' occupation-error' : '' }}">
            <legend class="font-semibold">職種 <span class="text-sm font-normal text-slate-600">必須</span></legend>
            <p id="occupation-help" class="mt-2 text-sm text-slate-600">希望する職種を1つ選んでください。</p>
            <div class="mt-3 grid gap-3 min-[360px]:grid-cols-2">
                @foreach ($occupations as $index => $occupation)
                    <label class="jobdd-choice" for="occupation-{{ $index }}">
                        <input id="occupation-{{ $index }}" name="occupation" type="radio" value="{{ $occupation }}" required @checked(($values['occupation'] ?? '') === $occupation)
                            @if (isset($fieldErrors['occupation'])) aria-invalid="true" @endif
                            aria-describedby="occupation-help{{ isset($fieldErrors['occupation']) ? ' occupation-error' : '' }}">
                        <span class="font-semibold">{{ $occupation }}</span>
                    </label>
                @endforeach
            </div>
            @if (isset($fieldErrors['occupation']))<p id="occupation-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['occupation'])) }}</p>@endif
        </fieldset>
        <div>
            <label for="region" class="block font-semibold">希望地域 <span class="text-sm font-normal text-slate-600">必須</span></label>
            <select id="region" name="region" required class="mt-2 min-h-12 w-full rounded-lg border border-slate-500 bg-white px-3 py-3 sm:max-w-80"
                aria-describedby="region-help{{ isset($fieldErrors['region']) ? ' region-error' : '' }}" @if (isset($fieldErrors['region'])) aria-invalid="true" @endif>
                <option value="">選んでください</option>
                @foreach ($regions as $region)<option value="{{ $region }}" @selected(($values['region'] ?? '') === $region)>{{ $region }}</option>@endforeach
            </select>
            <p id="region-help" class="mt-2 text-sm text-slate-600">近畿6府県から1つ選んでください。</p>
            @if (isset($fieldErrors['region']))<p id="region-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['region'])) }}</p>@endif
        </div>
        <div>
            <label for="salary_min" class="block font-semibold">希望年収の下限 <span class="text-sm font-normal text-slate-600">任意</span></label>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <input id="salary_min" name="salary_min" type="number" inputmode="numeric" min="1" max="10000" step="1" value="{{ $values['salary_min'] ?? '' }}" placeholder="例：600"
                    aria-describedby="salary-help{{ isset($fieldErrors['salary_min']) ? ' salary-error' : '' }}" @if (isset($fieldErrors['salary_min'])) aria-invalid="true" @endif
                    class="min-h-12 min-w-0 w-48 max-w-full rounded-lg border border-slate-500 px-3 py-3">
                <span>万円以上</span>
            </div>
            <p id="salary-help" class="mt-2 text-sm leading-6 text-slate-600">未指定の場合は空欄にしてください（1〜10,000万円）。年収による絞り込みはせず、掲載情報との違いを表示します。</p>
            @if (isset($fieldErrors['salary_min']))<p id="salary-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['salary_min'])) }}</p>@endif
        </div>
        @include('query.partials.tool-selector', ['tools' => $tools, 'selected' => $values['tools'] ?? [], 'idPrefix' => 'start-tools', 'toolErrors' => $fieldErrors['tools'] ?? []])
        <div class="border-t border-slate-200 pt-6">
            <button type="submit" class="jobdd-button w-full">求人候補を見る</button>
            <p class="mt-3 text-sm leading-6 text-slate-600">対象は近畿6府県の機械設計・電気設計です。条件が異なる求人や、情報が未確認の求人も表示されます。</p>
        </div>
    </form>
</main>
</body>
</html>
