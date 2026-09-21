@php
    $item = $items[0]; $job = $item['job']; $fit = $item['fit'];
    $roles = \App\Support\JobDecisionPresenter::ROLES;
    $routeLabels = ['direct' => 'Direct（企業への直接応募）', 'agent' => 'Agent（人材紹介会社経由）', 'platform' => 'Platform（求人媒体経由）'];
    $backUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>求人詳細 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
@include('query.partials.selection-header', ['heading' => '求人詳細と根拠', 'containerClass' => 'max-w-6xl'])
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:space-y-8 lg:py-8">
    <p class="leading-7 text-slate-600">他の求人と比較するには、<a href="{{ $backUrl.'#compare-selection' }}" class="jobdd-link">一覧で比較する求人を選ぶ</a>。応募方法はこのページの最後にあります。</p>
    <article data-job-id="{{ $job->id }}" class="jobdd-card">
        <p class="font-semibold text-slate-600"><x-company-name :name="$item['company_name']" /></p>
        <h2 class="mt-2 text-2xl font-bold leading-8 text-blue-950">{{ $job->title }}</h2>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-600">職種</dt><dd>{{ $job->occupation ?? '未確認' }}</dd></div>
            <div><dt class="text-sm text-slate-600">勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
            <div><dt class="text-sm text-slate-600">掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
            @if ($job->employment_type)<div><dt class="text-sm text-slate-600">雇用形態</dt><dd>{{ $job->employment_type }}</dd></div>@endif
        </dl>
        <p class="mt-4 text-sm leading-6 text-slate-600">掲載年収・保存上の掲載状態は、提示年収や現在の募集を保証しません。</p>
    </article>
    <section aria-labelledby="fit-title" class="jobdd-card">
        <h2 id="fit-title" class="text-xl font-bold text-blue-950">希望条件との確認結果</h2>
        <p class="mt-3 leading-7 text-slate-600">未確認：求人本文から確認できない情報です。合わないという意味ではありません。</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">保存された掲載値や記載の文脈が曖昧な場合も、未確認として表示します。</p>
        @include('query.partials.fit', ['fit' => $fit, 'idPrefix' => 'detail-job-'.$job->id, 'summaryOnly' => false])
    </section>
    <section aria-labelledby="presence-title" class="jobdd-card">
        <h2 id="presence-title" class="text-xl font-bold text-blue-950">求人本文で確認できた技術・工程</h2>
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
    </section>
    <section aria-labelledby="source-title" class="jobdd-card">
        <h2 id="source-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><x-jobdd-icon name="evidence" />根拠と求人元の情報</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">各項目の原文は、それぞれの「根拠を見る」から確認できます。ここには、この求人の掲載元を表示しています。</p>
        <details class="jobdd-details mt-4">
            <summary>保存された求人本文を見る</summary>
            <p class="max-w-[760px] whitespace-pre-wrap break-words p-4 pt-2 leading-7">{{ $job->description ?? '求人本文は未確認です。' }}</p>
        </details>
        <div class="mt-5">@include('query.partials.provenance', ['source' => $fit['source']])</div>
    </section>
    <section aria-labelledby="application-title" class="jobdd-card">
        <h2 id="application-title" class="text-xl font-bold text-blue-950">この求人で確認できた応募方法</h2>
        <p class="mt-3 leading-7 text-slate-600">保存済みの応募経路を種類別に表示しています。利用条件と現在の募集状況はリンク先で確認してください。</p>
        @forelse ($application_routes->groupBy('route_type') as $type => $routes)
            <section class="mt-6" aria-labelledby="route-type-{{ $type }}">
                <h3 id="route-type-{{ $type }}" class="font-bold text-blue-950">{{ $routeLabels[$type] }}</h3>
                <div class="mt-3 space-y-4">
                    @foreach ($routes as $route)
                        @php
                            $link = \App\Support\JobDecisionPresenter::safeUrl($route->application_url);
                            $available = $route->availability_status === 'available' && $route->unavailable_at === null;
                            $unavailable = $route->unavailable_at !== null || $route->availability_status === 'unavailable';
                            $notes = $route->notes ?: '記載は未確認です。';
                        @endphp
                        <section data-application-route="{{ $route->id }}" class="min-w-0 rounded-xl border border-slate-200 p-4">
                            <h4 class="font-semibold">提供元：{{ $route->provider_key ?? '未確認' }}</h4>
                            <dl class="mt-3 grid gap-3 text-sm leading-6 sm:grid-cols-2">
                                <div><dt>保存上の状態</dt><dd>{{ $unavailable ? '利用不可として記録' : ($available ? '利用可能として記録' : '利用状況未確認') }}</dd></div>
                                <div><dt>最終取得日時</dt><dd>{{ $route->getRawOriginal('last_seen_at') ?? '未確認' }}</dd></div>
                                <div class="sm:col-span-2"><dt>応募情報のURL</dt><dd class="break-words">{{ $link ?? '保存済みURLでは応募先リンクを表示できません' }}</dd></div>
                            </dl>
                            @if (mb_strlen($notes) > 400)
                                <details class="jobdd-details mt-4"><summary>根拠・補足を見る</summary><p class="whitespace-pre-wrap p-4 pt-2 leading-7">{{ $notes }}</p></details>
                            @else
                                <p class="mt-4 whitespace-pre-wrap leading-7">根拠・補足：{{ $notes }}</p>
                            @endif
                            @if ($available && $link)
                                <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="jobdd-button mt-4">提供元の応募情報を見る（新しいタブ）<span class="sr-only">：{{ $route->provider_key ?? '提供元未確認' }}</span></a>
                            @else
                                <p class="mt-4 text-sm leading-6 text-slate-600">現在利用できる応募先リンクを確認できていません。</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="mt-4">保存済み情報では応募方法を確認できていません</p>
        @endforelse
    </section>
    <section aria-labelledby="agency-option-title" class="jobdd-card">
        <h2 id="agency-option-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><x-jobdd-icon name="agent" />人材紹介会社へ相談する選択肢</h2>
        <p class="mt-3 leading-7 text-slate-600">公開求人だけでは分からない求人や、キャリア相談を確認したい場合の相談先候補です。この求人の紹介可否を示すものではありません。</p>
        <a href="{{ route('query.agencies', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]) }}" class="jobdd-link mt-3 inline-flex min-h-12 items-center">相談先候補を見る</a>
    </section>
    <a href="{{ $backUrl }}" class="jobdd-link inline-flex min-h-12 items-center">求人一覧へ戻る</a>
</main>
</body>
</html>
