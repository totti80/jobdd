<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>比較材料となる詳細希望 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
@include('query.partials.selection-header', ['heading' => '仕事の中身を、もう少し詳しく比較する', 'containerClass' => 'max-w-6xl'])
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:py-8">
    <p id="preferences-help" class="leading-7 text-slate-600">すべて任意です。気になる項目だけ追加できます。求人詳細で仕事の中身と並べて表示し、一致・不一致の判定や求人の並べ替えには使いません。</p>
    @if ($inputErrors)
        <section id="preference-errors" role="alert" tabindex="-1" data-input-errors class="rounded-xl border border-red-700 bg-red-50 p-4 text-red-800">
            <h2 class="font-bold">入力内容を確認してください</h2>
            <ul class="mt-2 list-disc space-y-2 pl-5">@foreach (array_unique($inputErrors) as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif
    <form method="POST" action="{{ $saveUrl }}" class="space-y-6" aria-describedby="preferences-help{{ $inputErrors ? ' preference-errors' : '' }}">
        @csrf
        @method('PATCH')
        <x-decision-section id="preference-phases" title="関わりたい担当工程">
            <fieldset>
                <legend class="mb-3 text-sm leading-6 text-slate-600">複数選択できます。まだ決めていなければ選ばずに進めます。</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($phases as $key => $label)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-300 p-3">
                            <input type="checkbox" name="design_phases[]" value="{{ $key }}" @checked(in_array($key, $values['design_phases'], true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </x-decision-section>
        <x-decision-section id="preference-relations" title="誰と、どのくらい関わりたいか">
            <div class="grid gap-6 sm:grid-cols-3">
                @foreach ($relations as $key => $label)
                    <div class="min-w-0">
                        <label for="{{ $key }}" class="block font-semibold">{{ $label }}との関わり</label>
                        <select id="{{ $key }}" name="{{ $key }}" class="mt-2 min-h-12 w-full min-w-0 rounded-lg border border-slate-500 bg-white px-3 py-3">
                            <option value="">まだ入力しない</option>
                            @foreach ($tendencies as $value => $text)<option value="{{ $value }}" @selected(($values[$key] ?? null) === $value)>{{ $text }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </x-decision-section>
        <x-decision-section id="preference-style" title="希望する仕事の進め方">
            <label for="work_style" class="block font-semibold">大切にしたい働き方 <span class="text-sm font-normal text-slate-600">任意</span></label>
            <textarea id="work_style" name="work_style" rows="5" maxlength="2000" aria-describedby="work-style-help" placeholder="例：チームで相談しながら進めたい。一人で集中する時間も欲しい。" class="mt-2 w-full min-w-0 rounded-lg border border-slate-500 p-3">{{ $values['work_style'] ?? '' }}</textarea>
            <p id="work-style-help" class="mt-2 text-sm leading-6 text-slate-600">2,000文字以内。文章の意味を自動判定せず、そのまま比較材料として表示します。</p>
        </x-decision-section>
        <div class="jobdd-card space-y-4">
            <p class="text-sm leading-6 text-slate-600">職種・勤務地・年収・CAD / Toolの条件は維持します。詳細希望は後から編集でき、選択や文章を空にして保存すると取り消せます。</p>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="submit" class="jobdd-button">詳細希望を保存して求人へ戻る</button>
                <a href="{{ $returnUrl }}" class="jobdd-link inline-flex min-h-12 items-center">保存せず求人へ戻る</a>
            </div>
        </div>
    </form>
</main>
<x-site-footer />
</body>
</html>
