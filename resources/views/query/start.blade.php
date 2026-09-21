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
    <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
        @include('query.partials.brand')
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-10">
    <section aria-labelledby="entry-title" class="jobdd-entry-hero">
        <div class="jobdd-hero-copy">
        <p class="jobdd-scope-badge"><x-jobdd-icon name="map" /><span>近畿6府県 × 機械設計・電気設計 専門</span></p>
        <h1 id="entry-title" class="jobdd-page-title jobdd-hero-title">求人を探すだけでは、わからない。<br>仕事の中身まで比べて、選ぶ。</h1>
        <p class="mt-4 max-w-3xl leading-7">JobDDは、求人情報を「確認できたこと・条件と異なること・未確認」に整理し、根拠を見ながら比較できるDecision Supportサービスです。</p>
        <p class="mt-3 text-sm leading-6 text-slate-600">現在のJobDDは、近畿地方の機械設計・電気設計職に対象を絞った卒業制作版です。</p>
        <a href="#entry-form" class="jobdd-link mt-4 inline-flex min-h-11 items-center gap-2">希望条件を入力する<x-jobdd-icon name="arrow" /></a>
        </div>
    </section>
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
    <div data-entry-layout class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-8">
    <form id="entry-form" method="POST" action="{{ route('jobs.store') }}" aria-labelledby="entry-form-title" class="jobdd-card jobdd-entry-form space-y-7">
        @csrf
        <div class="border-b border-slate-200 pb-5">
            <h2 id="entry-form-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><span class="jobdd-icon-tile"><x-jobdd-icon name="compare" /></span>希望条件から、求人を比較する</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">対象：近畿6府県 × 機械設計・電気設計</p>
        </div>
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
        <div data-tool-group class="jobdd-tool-group">
        @include('query.partials.tool-selector', ['tools' => $tools, 'selected' => $values['tools'] ?? [], 'idPrefix' => 'start-tools', 'toolErrors' => $fieldErrors['tools'] ?? []])
        <div class="mt-5 border-t border-slate-200 pt-5">
            <label for="custom_tools" class="block font-semibold">その他のCAD・ツール <span class="text-sm font-normal text-slate-600">任意</span></label>
            <textarea id="custom_tools" name="custom_tools" rows="3" maxlength="500" placeholder="iCAD SX、EPLAN、ANSYS など"
                aria-describedby="custom-tools-help{{ isset($fieldErrors['custom_tools']) ? ' custom-tools-error' : '' }}" @if (isset($fieldErrors['custom_tools'])) aria-invalid="true" @endif
                class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-500 px-3 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 aria-invalid:border-red-700">{{ $values['custom_tools'] ?? '' }}</textarea>
            <p id="custom-tools-help" class="mt-2 text-sm leading-6 text-slate-600">500文字以内。自由記述のツールは判定未対応です。希望条件として保持し、一致判定には使用しません。</p>
            @if (isset($fieldErrors['custom_tools']))<p id="custom-tools-error" class="mt-2 text-sm text-red-800">{{ implode(' ', array_unique($fieldErrors['custom_tools'])) }}</p>@endif
        </div>
        </div>
        <div class="border-t border-slate-200 pt-6">
            <button type="submit" class="jobdd-button w-full">求人候補を見る</button>
            <p class="mt-3 text-sm leading-6 text-slate-600">対象は近畿6府県の機械設計・電気設計です。条件が異なる求人や、情報が未確認の求人も表示されます。</p>
        </div>
    </form>
    <aside aria-labelledby="entry-guide-title" class="min-w-0 space-y-5">
        <section class="jobdd-card">
            <h2 id="entry-guide-title" class="text-xl font-bold text-blue-950">JobDDで分かること</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">希望条件と求人の記載を照らし合わせ、3つの状態に整理します。</p>
            <ul class="mt-5 space-y-4">
                <li>@include('query.partials.status-badge', ['status' => 'match'])<p class="mt-2 text-sm leading-6">希望条件との一致を、求人の記載から確認できた情報です。</p></li>
                <li>@include('query.partials.status-badge', ['status' => 'mismatch'])<p class="mt-2 text-sm leading-6">希望条件と掲載情報に違いがある項目です。</p></li>
                <li>@include('query.partials.status-badge', ['status' => 'unknown'])<p class="mt-2 text-sm leading-6">求人本文から確認できない情報です。合わないという意味ではありません。</p></li>
            </ul>
        </section>
        <section class="jobdd-card" aria-labelledby="entry-evidence-title">
            <h2 id="entry-evidence-title" class="text-lg font-bold text-blue-950">選ぶための、3つの見方</h2>
            <div class="jobdd-feature-row"><span class="jobdd-icon-tile"><x-jobdd-icon name="evidence" /></span><div><h3 class="font-bold text-blue-950">根拠を確認</h3><p class="mt-1 text-sm leading-6 text-slate-600">求人票や公式情報など、出典とEvidenceを確認できます。</p></div></div>
            <div class="jobdd-feature-row"><span class="jobdd-icon-tile"><x-jobdd-icon name="compare" /></span><div><h3 class="font-bold text-blue-950">同じ軸で比較</h3><p class="mt-1 text-sm leading-6 text-slate-600">2〜3求人を、勤務地・年収・ツールなど同じ項目で比較できます。</p></div></div>
            <div class="jobdd-feature-row"><span class="jobdd-icon-tile"><x-jobdd-icon name="map" /></span><div><h3 class="font-bold text-blue-950">地図で見る</h3><p class="mt-1 text-sm leading-6 text-slate-600">近畿の求人を都道府県の代表点で確認。実際の勤務地を示すものではありません。</p></div></div>
        </section>
        <div class="jobdd-decision-note"><h2 class="font-bold text-blue-950">最終判断は、あなた自身で</h2><p class="mt-2 text-sm leading-6 text-slate-600">JobDDは情報の整理と比較を支援します。応募先や応募方法は、あなた自身が選びます。</p></div>
        <p class="px-1 text-sm leading-7 text-slate-600">条件入力 → 求人一覧 → 詳細・根拠 → 比較 → 応募方法</p>
    </aside>
    </div>
</main>
</body>
</html>
