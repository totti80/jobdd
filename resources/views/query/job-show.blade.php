@php
    $item = $items[0]; $job = $item['job']; $fit = $item['fit'];
    $roles = \App\Support\JobDecisionPresenter::ROLES;
    $routeLabels = ['direct' => 'Direct（企業への直接応募）', 'agent' => 'Agent（人材紹介会社経由）', 'platform' => 'Platform（求人媒体経由）'];
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>求人詳細 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
@include('query.partials.selection-header', ['heading' => '求人詳細と根拠'])
<main class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6">
    <p class="text-sm leading-6 text-slate-600">他の求人と比較するには、<a href="{{ route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]).'#compare-selection' }}" class="font-semibold text-blue-800 underline">一覧で比較する求人を選ぶ</a>。応募方法はこのページの最後にあります。</p>
    <article data-job-id="{{ $job->id }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
        <p class="break-words font-semibold text-slate-600">{{ $item['company_name'] }}</p>
        <h2 class="mt-2 break-words text-2xl font-bold text-blue-950">{{ $job->title }}</h2>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">職種</dt><dd>{{ $job->occupation ?? '未確認' }}</dd></div>
            <div><dt class="text-slate-500">勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
            <div><dt class="text-slate-500">掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
            <div><dt class="text-slate-500">情報提供元</dt><dd>{{ $job->provider_key ?? '未確認' }}</dd></div>
            @foreach (['last_seen_at' => '最終取得日時', 'published_at' => '掲載日時', 'provider_updated_at' => '提供元更新日時'] as $field => $label)
                <div><dt class="text-slate-500">{{ $label }}</dt><dd>{{ $fit['source'][$field] ?? '未確認' }}</dd></div>
            @endforeach
        </dl>
        @if (\App\Support\JobDecisionPresenter::safeUrl($job->source_url))
            <a href="{{ $job->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-block text-sm font-semibold text-blue-800 underline">求人元を見る（新しいタブ）</a>
        @endif
        <p class="mt-3 text-xs leading-6 text-slate-500">掲載年収・保存上の掲載状態は、提示年収や現在の募集を保証しません。</p>
        <h3 class="mt-6 text-lg font-bold">希望条件との比較</h3>
        @include('query.partials.fit', ['fit' => $fit])
        <details class="mt-5 rounded-xl bg-slate-50 p-4">
            <summary class="cursor-pointer font-semibold">保存された求人本文を見る</summary>
            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7">{{ $job->description ?? '求人本文は未確認です。' }}</p>
        </details>
    </article>
    <section aria-labelledby="presence-title" class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:p-6">
        <h2 id="presence-title" class="text-xl font-bold">求人本文で確認できた技術・工程</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">保存された記載とその文脈です。技術名の記載だけでは、本人の担当業務や使用を意味しません。</p>
        <div class="mt-4 divide-y divide-slate-200">
            @forelse ($presence_facts as $presence)
                @php
                    $fact = $presence['fact'];
                @endphp
                <section class="py-4">
                    <h3 class="break-words font-semibold">{{ $fact->normalized_value ?: $fact->fact_value }}</h3>
                    <p class="mt-1 text-sm text-slate-600">分類：{{ $fact->fact_category }} / {{ $roles[$presence['context']['role']] ?? '文脈未確認' }}</p>
                    <p class="mt-2 break-words text-sm leading-6">{{ $presence['context']['reason'] }}</p>
                    <details class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <summary class="cursor-pointer font-semibold text-blue-800">根拠を見る</summary>
                        <p class="mt-3 whitespace-pre-wrap break-words leading-6">{{ $fact->evidence_text ?? '根拠本文未確認' }}</p>
                        <p class="mt-3 text-xs text-slate-500">確認日時：{{ $fact->getRawOriginal('observed_at') ?? '未確認' }}</p>
                    </details>
                </section>
            @empty
                <p class="py-4 text-sm">保存済み情報では技術・工程の記載を確認できていません。</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="application-title" class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:p-6">
        <h2 id="application-title" class="text-xl font-bold">この求人への応募方法</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">保存済みの応募経路を種類別に表示しています。利用条件と現在の募集状況はリンク先で確認してください。</p>
        <div class="mt-4 space-y-4">
            @forelse ($application_routes as $route)
                @php
                    $link = \App\Support\JobDecisionPresenter::safeUrl($route->application_url);
                    $available = $route->availability_status === 'available' && $route->unavailable_at === null;
                @endphp
                <section data-application-route="{{ $route->id }}" class="rounded-xl border border-slate-200 p-4">
                    <h3 class="font-semibold">{{ $routeLabels[$route->route_type] }}</h3>
                    <p class="mt-2 text-sm">提供元：{{ $route->provider_key ?? '未確認' }}</p>
                    <p class="mt-1 text-sm">保存上の状態：{{ $available ? '利用可能として記録' : '利用状況を確認する必要があります' }}</p>
                    <p class="mt-1 text-xs text-slate-500">最終取得日時：{{ $route->getRawOriginal('last_seen_at') ?? '未確認' }}</p>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6">根拠・補足：{{ $route->notes ?: '記載は未確認です。' }}</p>
                    @if ($available && $link)
                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm font-semibold text-blue-800 underline">応募先の情報を確認する（新しいタブ）</a>
                    @else
                        <p class="mt-3 text-sm text-slate-600">現在利用できる応募先リンクを確認できていません。</p>
                    @endif
                </section>
            @empty
                <p class="text-sm">保存済み情報では応募方法を確認できていません</p>
            @endforelse
        </div>
    </section>
    <a href="{{ route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]) }}" class="inline-block py-3 font-semibold text-blue-800 underline">求人一覧へ戻る</a>
</main>
</body>
</html>
