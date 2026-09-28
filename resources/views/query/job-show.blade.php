@php
    $item = $items[0]; $job = $item['job']; $fit = $item['fit'];
    $roles = \App\Support\JobDecisionPresenter::ROLES;
    $backUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>求人詳細 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<x-site-header />
<main class="jobdd-detail site-container space-y-6 py-6 lg:space-y-8 lg:py-10">
    @include('query.partials.detail-hero')
    <section aria-labelledby="fit-title" class="jobdd-card">
        <h2 id="fit-title" class="text-xl font-bold text-blue-950">あなたの希望条件との確認</h2>
        <p class="mt-3 leading-7 text-slate-600">未確認：求人本文から確認できない情報です。合わないという意味ではありません。</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">保存された掲載値や記載の文脈が曖昧な場合も、未確認として表示します。</p>
        @include('query.partials.fit', ['fit' => $fit, 'idPrefix' => 'detail-job-'.$job->id, 'summaryOnly' => false])
    </section>
    <section aria-labelledby="job-information" class="jobdd-card">
        <h2 id="job-information" class="text-xl font-bold text-blue-950">この仕事について分かること</h2>
        <p class="mt-3 mb-5 text-sm leading-6 text-slate-600">未確認は、合わないという意味ではありません。記載の文脈を確認してください。</p>
        <x-decision-fields :fields="(new \App\Support\LegacyJobInformation)->present($presence_facts)" />
        <p class="mt-4 text-sm leading-6 text-slate-600">技術名・工程の記載だけでは、本人の担当業務や使用を意味しません。使用文脈・応募時経験条件は、確認できる範囲を表示しています。</p>
    </section>
    <details aria-labelledby="presence-title" class="jobdd-card jobdd-detail-disclosure">
        <summary id="presence-title">求人本文で確認できた技術・工程 <span>詳細を開く</span></summary>
        <p class="mt-3 leading-7 text-slate-600">保存された記載とその文脈です。技術名の記載だけでは、本人の担当業務や使用を意味しません。</p>
        <div class="mt-4 divide-y divide-slate-200">
            @forelse ($presence_facts as $presence)
                @php
                    $fact = $presence['fact'];
                    $factLabel = $fact->normalized_value ?: ($fact->fact_value ?: '記載内容未確認');
                    $factEvidence = ['kind' => 'job_fact', 'evidence_text' => $fact->evidence_text, 'context' => $presence['context'], 'observed_at' => $fact->getRawOriginal('observed_at')];
                @endphp
                <section class="min-w-0 py-5">
                    <h3 class="font-semibold">{{ $factLabel }}</h3>
                    <p class="mt-2 text-sm leading-6">記載の文脈：{{ $roles[$presence['context']['role']] ?? '文脈未確認' }}</p>
                    <p class="mt-2 leading-7">{{ $presence['context']['reason'] }}</p>
                    <p class="mt-2 text-sm text-slate-600">記録上の検証状態：{{ $fact->verification_status === 'verified' ? '確認済みとして記録' : '未確認' }}</p>
                    <details class="jobdd-details mt-3">
                        <summary>{{ $factLabel }}の根拠を見る</summary>
                        <div class="p-4 pt-2">@include('query.partials.evidence-block', ['evidence' => $factEvidence, 'source' => $fit['source'], 'evidenceLabel' => $factLabel])</div>
                    </details>
                </section>
            @empty
                <p class="py-4">保存済み情報では技術・工程の記載を確認できていません。</p>
            @endforelse
        </div>
    </details>
    <section aria-labelledby="source-title" class="jobdd-card">
        <h2 id="source-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><x-jobdd-icon name="evidence" />根拠と掲載元</h2>
        <p class="mt-3 text-sm font-semibold text-slate-600">外部情報から取得</p>
        <p class="mt-3 text-sm leading-6 text-slate-600">各項目の原文は、それぞれの「根拠を見る」から確認できます。ここには、この求人の掲載元を表示しています。</p>
        <details class="jobdd-details mt-4">
            <summary>保存された求人本文を見る</summary>
            <p class="max-w-[760px] whitespace-pre-wrap break-words p-4 pt-2 leading-7">{{ $job->description ?? '求人本文は未確認です。' }}</p>
        </details>
        <div class="mt-5">@include('query.partials.provenance', ['source' => $fit['source'], 'compactSource' => true])</div>
    </section>
    @include('query.partials.detail-application')
    <a href="{{ $backUrl }}" class="jobdd-link inline-flex min-h-12 items-center">求人一覧へ戻る</a>
</main>
<x-site-footer />
</body>
</html>
