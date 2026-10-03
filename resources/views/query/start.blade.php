<!DOCTYPE html>
<html lang="ja">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>希望条件を入力 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<x-site-header />
<main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-10">
    <x-page-hero id="entry-title" title="まずは4つの条件から求人を見てみる" eyebrow="かんたん入力">
        <p>詳しい条件は、求人を見たあとから追加できます。</p>
        <p class="mt-2 text-sm">現在の対象は、近畿6府県の機械設計・電気設計です。</p>
    </x-page-hero>
    @if ($selectedJob)
        <div class="jobdd-decision-note mb-6" role="status"><p class="font-bold">「{{ $selectedJob->title }}」が気になった方へ</p><p class="mt-2 leading-7">まず希望条件を入力してください。求人一覧から、この求人を含めた仕事の内容を確認・比較できます。職種や公開状況によっては一覧に表示されない場合があります。</p></div>
    @elseif (request()->query('guide') === 'compare')
        <div class="jobdd-decision-note mb-6"><p>比較したい求人を選んでください。まず条件を入力して求人一覧へ進みます。</p></div>
    @elseif (request()->query('guide') === 'preferences')
        <div class="jobdd-decision-note mb-6"><p>詳細条件を追加するには、まず4つの基本条件（希望職種・希望勤務地・希望年収・CAD / Tool）を入力してください。</p></div>
    @elseif (request()->query('guide') === 'unavailable')
        <div class="jobdd-decision-note mb-6"><p>この求人は現在確認できません。条件を入力して公開中の求人をご覧ください。</p></div>
    @endif
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
    @include('query.partials.entry-form')
    <aside aria-labelledby="entry-guide-title" class="min-w-0 space-y-5">
        <section class="jobdd-card">
            <h2 id="entry-guide-title" class="text-xl font-bold text-blue-950">JobDDの使い方</h2>
            <ol class="jobdd-guide-list">
                @foreach (['かんたん入力', '求人を見る', '必要なら詳細条件を追加', '仕事の中身を比較', '応募方法を確認'] as $step)
                    <li class="jobdd-guide-item"><span class="jobdd-guide-icon jobdd-guide-confirmed" aria-hidden="true">{{ $loop->iteration }}</span><span class="pt-1 font-semibold text-blue-950">{{ $step }}</span></li>
                @endforeach
            </ol>
        </section>
        <section class="jobdd-card">
            <h2 class="text-xl font-bold text-blue-950">JobDDで分かること</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">希望条件と求人の記載を照らし合わせ、3つの状態に整理します。</p>
            <ul class="jobdd-guide-list">
                <li class="jobdd-guide-item" data-explanation="confirmed"><span class="jobdd-guide-icon jobdd-guide-confirmed"><x-jobdd-icon name="match" /></span><div><h3 class="font-bold text-blue-950">確認できた</h3><p class="mt-1 text-sm leading-6 text-slate-600">希望条件との一致を、求人の記載から確認できた情報です。</p></div></li>
                <li class="jobdd-guide-item" data-explanation="different"><span class="jobdd-guide-icon jobdd-guide-different"><x-jobdd-icon name="difference" /></span><div><h3 class="font-bold text-blue-950">条件と異なる</h3><p class="mt-1 text-sm leading-6 text-slate-600">希望条件と掲載情報に違いがある項目です。</p></div></li>
                <li class="jobdd-guide-item" data-explanation="unknown"><span class="jobdd-guide-icon jobdd-guide-unknown"><x-jobdd-icon name="unknown" /></span><div><h3 class="font-bold text-blue-950">未確認</h3><p class="mt-1 text-sm leading-6 text-slate-600">求人本文から確認できない情報です。合わないという意味ではありません。</p></div></li>
            </ul>
        </section>
        <section class="jobdd-card" aria-labelledby="entry-evidence-title">
            <h2 id="entry-evidence-title" class="text-lg font-bold text-blue-950">選ぶための、3つの見方</h2>
            <div class="jobdd-feature-row" data-explanation="evidence"><span class="jobdd-icon-tile"><x-jobdd-icon name="evidence" /></span><div><h3 class="font-bold text-blue-950">根拠を確認</h3><p class="mt-1 text-sm leading-6 text-slate-600">求人票や公式情報など、出典とEvidenceを確認できます。</p></div></div>
            <div class="jobdd-feature-row" data-explanation="compare"><span class="jobdd-icon-tile"><x-jobdd-icon name="compare" /></span><div><h3 class="font-bold text-blue-950">同じ軸で比較</h3><p class="mt-1 text-sm leading-6 text-slate-600">2〜3求人を、勤務地・年収・ツールなど同じ項目で比較できます。</p></div></div>
            <div class="jobdd-feature-row" data-explanation="map"><span class="jobdd-icon-tile"><x-jobdd-icon name="map-pin" /></span><div><h3 class="font-bold text-blue-950">地図で見る</h3><p class="mt-1 text-sm leading-6 text-slate-600">近畿の求人を都道府県の代表点で確認。実際の勤務地を示すものではありません。</p></div></div>
        </section>
        <div class="jobdd-decision-note"><h2 class="font-bold text-blue-950">最終判断は、あなた自身で</h2><p class="mt-2 text-sm leading-6 text-slate-600">JobDDは情報の整理と比較を支援します。応募先や応募方法は、あなた自身が選びます。</p></div>
        <p class="px-1 text-sm leading-7 text-slate-600">条件入力 → 求人一覧 → 詳細・根拠 → 比較 → 応募方法</p>
    </aside>
    </div>
</main>
<x-site-footer />
</body>
</html>
