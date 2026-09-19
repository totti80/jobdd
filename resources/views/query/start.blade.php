<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>希望条件を入力 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
        <p class="text-xl font-bold text-blue-950">JobDD</p>
        <h1 class="mt-3 text-2xl font-bold sm:text-3xl">希望条件から、求人を比較する</h1>
        <p class="mt-4 text-sm leading-7 text-slate-600">希望条件と求人情報を照らし合わせて、確認できたこと・異なること・未確認のことを整理します。根拠を確認し、最終判断はあなた自身で行えます。</p>
        <p class="mt-3 text-sm text-slate-500">条件入力 → 求人一覧 → 詳細・根拠 → 比較 → 応募方法</p>
    </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    @if ($inputErrors)
        <section role="alert" aria-labelledby="input-errors" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">
            <h2 id="input-errors" class="font-bold">入力内容を確認してください</h2>
            <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                @foreach (array_unique($inputErrors) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif
    <form method="POST" action="{{ route('jobs.store') }}" class="space-y-7 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-8">
        @csrf
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach (['occupation' => ['職種', $occupations], 'region' => ['希望地域', $regions]] as $field => [$label, $options])
                <div class="min-w-0">
                    <label for="{{ $field }}" class="block font-semibold">{{ $label }} <span class="text-sm text-slate-500">必須</span></label>
                    <select id="{{ $field }}" name="{{ $field }}" required class="mt-2 min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3 py-3">
                        <option value="">選んでください</option>
                        @foreach ($options as $option)
                            <option value="{{ $option }}" @selected(($values[$field] ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <div>
            <label for="salary_min" class="block font-semibold">希望年収の下限 <span class="text-sm text-slate-500">任意</span></label>
            <div class="mt-2 flex items-center gap-3">
                <input id="salary_min" name="salary_min" type="number" inputmode="numeric" min="1" max="10000" step="1" value="{{ $values['salary_min'] ?? '' }}" placeholder="例：600" aria-describedby="salary-help" class="min-h-12 min-w-0 w-48 rounded-lg border border-slate-300 px-3 py-3">
                <span class="shrink-0 text-sm">万円以上</span>
            </div>
            <p id="salary-help" class="mt-2 text-sm leading-6 text-slate-500">未指定の場合は空欄にしてください（1〜10,000万円）。年収による絞り込みはせず、掲載情報との違いを表示します。</p>
        </div>
        <fieldset>
            <legend class="font-semibold">仕事で使いたいCAD・ツール <span class="text-sm text-slate-500">任意</span></legend>
            <p class="mt-2 text-sm leading-6 text-slate-500">複数選択できます。求人本文に担当業務として記載があるかを確認します。</p>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($tools as $key => $name)
                    <label class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-200 px-3 py-3 text-sm"><input type="checkbox" name="tools[]" value="{{ $key }}" @checked(in_array($key, $values['tools'] ?? [], true)) class="size-4 shrink-0">{{ $name }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="border-t border-slate-200 pt-6">
            <button type="submit" class="min-h-12 w-full rounded-lg bg-blue-800 px-6 py-3 font-semibold text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-2">求人を比較する</button>
            <p class="mt-3 text-sm leading-6 text-slate-500">対象は近畿6府県の機械設計・電気設計です。条件が異なる求人や、情報が未確認の求人も表示されます。</p>
        </div>
    </form>
</main>
</body>
</html>
