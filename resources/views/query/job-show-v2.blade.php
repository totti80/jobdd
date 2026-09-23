@php
    $job = $items[0]['job'];
    $fit = $items[0]['fit'];
    $view = $decision_view;
    $backUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]);
    $compareUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools, 'select_job' => $job->id]).'#compare-selection';
    $routesUrl = route('routes.show', ['jobPosting' => $job->id]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>{{ $view['title'] }} | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
@include('query.partials.selection-header', ['heading' => '仕事の中身と根拠', 'containerClass' => 'max-w-6xl'])
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:space-y-8 lg:py-8" data-decision-view="v2">
    <article data-job-id="{{ $job->id }}" class="jobdd-card min-w-0">
        <p class="break-words font-semibold text-slate-600"><x-company-name :name="$view['company']" /></p>
        <h2 class="mt-2 break-words text-2xl font-bold leading-8 text-blue-950">{{ $view['title'] }}</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">企業提供情報 · JobDD公開確認済み</p>
        <div class="mt-5"><x-decision-fields :fields="$view['basic']" /></div>
        <p class="mt-4 text-sm leading-6 text-slate-600">掲載年収・保存上の掲載状態は、提示年収や現在の募集を保証しません。情報を比較し、最終的な判断はご自身で行ってください。</p>
        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
            <a class="jobdd-button" href="{{ $compareUrl }}">比較に追加</a>
            <a class="jobdd-button" href="{{ $routesUrl }}">応募方法を見る</a>
        </div>
        <p class="mt-3 text-sm leading-6 text-slate-600">比較は一覧で2〜3件を選びます。この求人を選択した状態で一覧へ戻ります。</p>
        <noscript><p class="mt-2 text-sm">JavaScriptが無効な場合は、一覧でこの求人を選択してください。</p></noscript>
    </article>
    @foreach ($view['sections'] as $section)
        <x-decision-section :id="$section['id']" :title="$section['title']">
            @switch($section['type'] ?? 'fields')
                @case('points')
                    <p class="mb-3 text-sm leading-6 text-slate-600">企業の公開情報を整理した要点です。推薦や適性の評価ではありません。</p>
                    <ul class="list-disc space-y-3 break-words pl-5 leading-7">
                        @forelse ($section['points'] as $point)<li>{{ $point }}</li>
                        @empty<li>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</li>@endforelse
                    </ul>
                    @break
                @case('fit')
                    <p class="leading-7 text-slate-600">未確認は、条件に合わないという意味ではありません。照合対象は職種・勤務地・年収・希望したCAD / Toolです。</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">企業申告のツール情報だけでは、現在のルールで使用を確認できた扱いにはしません。工程・経験・働き方などは、ご自身で比較するための情報です。</p>
                    @include('query.partials.fit', ['fit' => $fit, 'idPrefix' => 'published-job-'.$job->id, 'summaryOnly' => false])
                    @break
                @case('tools')
                    <p class="mb-4 leading-7 text-slate-600">仕事で使うことと、応募時点で経験が必要なことを分けて表示しています。</p>
                    <div class="space-y-5">
                        @forelse ($section['tools'] as $tool)
                            <section class="min-w-0 rounded-xl border border-slate-200 p-4">
                                <h3 class="mb-4 break-words text-lg font-bold">{{ $tool['name'] }}</h3>
                                <x-decision-fields :fields="$tool['fields']" />
                                <div class="mt-4 flex flex-wrap items-center gap-3"><p class="text-sm font-semibold">あなたとの照合</p>@include('query.partials.status-badge', ['status' => $tool['status']])</div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $tool['fit_note'] }}</p>
                            </section>
                        @empty<p>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</p>@endforelse
                    </div>
                    @break
                @case('day')
                    <p class="mb-5 leading-7 text-slate-600">代表的な1日の例です。毎日同じ業務内容を保証するものではありません。</p>
                    <ol class="ml-2 space-y-5 border-l-2 border-blue-200 pl-5" aria-label="代表的な1日の流れ">
                        @forelse ($section['items'] as $entry)
                            <li class="min-w-0"><h3 class="break-words font-bold text-blue-950">{{ $entry['time'] }}</h3><p class="mt-1 whitespace-pre-wrap break-words leading-7">{{ $entry['activity'] }}</p></li>
                        @empty<li>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</li>@endforelse
                    </ol>
                    @break
                @case('evidence')
                    <p class="font-semibold">企業提供情報 · JobDD公開確認済み</p>
                    <p class="mt-3 leading-7 text-slate-600">JobDDは公開可能な状態を確認しています。企業申告内容の真実性を保証するものではありません。</p>
                    <details class="jobdd-details mt-4">
                        <summary>情報源を見る</summary>
                        <div class="space-y-4 p-4 pt-2">
                            <p class="break-words">情報源：{{ $view['source_title'] }}</p>
                            <p>情報の種類：企業による申告情報</p>
                            <p>公開確認・公開日時：{{ $view['published_at'] }}</p>
                            <p class="text-sm leading-6">対象情報：このページの企業提供の仕事内容・応募条件。以下の根拠は公開時に記録された情報です。</p>
                            @include('query.partials.provenance', ['source' => $view['source']])
                        </div>
                    </details>
                    <details class="jobdd-details mt-4">
                        <summary>項目別の根拠を見る（{{ count($view['evidence']) }}件）</summary>
                        <div class="space-y-6 p-4 pt-2">
                            @forelse ($view['evidence'] as $fact)
                                <section><h3 class="mb-2 break-words font-semibold">{{ $fact['label'] }}</h3><p class="mb-3 text-sm text-slate-600">{{ $fact['kind_label'] }}</p>
                                    @include('query.partials.evidence-block', ['evidence' => $fact['evidence'], 'source' => $fact['source'], 'evidenceLabel' => $fact['label'], 'evidenceTextLabel' => $fact['text_label']])
                                </section>
                            @empty<p>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</p>@endforelse
                        </div>
                    </details>
                    @break
                @case('routes')
                    <p class="leading-7 text-slate-600">{{ $section['count'] }}件の応募経路が保存されています。利用条件と現在の募集状況は応募方法の画面とリンク先で確認してください。</p>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                        <a class="jobdd-button" href="{{ $routesUrl }}">応募方法を見る</a>
                        <a class="jobdd-button" href="{{ $compareUrl }}">比較に追加</a>
                    </div>
                    @break
                @default
                    <x-decision-fields :fields="$section['fields']" />
            @endswitch
        </x-decision-section>
    @endforeach
    <a href="{{ $backUrl }}" class="jobdd-link inline-flex min-h-12 items-center">求人一覧へ戻る</a>
</main>
</body>
</html>
