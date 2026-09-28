@php
    $job = $items[0]['job'];
    $fit = $items[0]['fit'];
    $view = $decision_view;
    $backUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>{{ $view['title'] }} | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen" data-jobdd-root>
<x-site-header />
<main class="jobdd-detail site-container space-y-6 py-6 lg:space-y-8 lg:py-10" data-decision-view="v2">
    @include('query.partials.detail-hero')
    @php($sections = collect($view['sections'])->keyBy('id'))
    <x-decision-section id="fit" title="あなたの希望条件との確認">
        @include('query.partials.published-section-content', ['section' => $sections['fit']])
    </x-decision-section>
    <section aria-labelledby="job-information" class="jobdd-card">
        <h2 id="job-information" class="text-xl font-bold text-blue-950">この仕事について分かること</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">企業が公開した情報です。未確認は、合わないという意味ではありません。</p>
        <div class="jobdd-information-grid">
            @foreach (['design' => '設計対象', 'phases' => '担当工程', 'tools' => 'CAD / Tool', 'collaboration' => '関係者', 'typical-day' => 'Typical Day'] as $key => $label)
                <section id="{{ $key }}" class="min-w-0">
                    <h3 class="mb-4 text-lg font-bold text-blue-950">{{ $label }}</h3>
                    @include('query.partials.published-section-content', ['section' => $sections[$key]])
                </section>
            @endforeach
        </div>
    </section>
    <details class="jobdd-card jobdd-detail-disclosure">
        <summary>仕事の詳細情報 <span>詳細を開く</span></summary>
        <div class="mt-5 space-y-6">
            @if ($view['preference_rows'])
                <x-decision-section id="detailed-preferences" title="あなたの詳細希望と、この求人の仕事">
                    <p class="mb-5 leading-7 text-slate-600">希望と企業提供の公開情報を並べています。一致・不一致の自動判定はしません。違いを確かめ、判断するための比較材料です。</p>
                    <div class="divide-y divide-slate-200" data-preference-comparison>
                        @foreach ($view['preference_rows'] as $row)
                            <section class="min-w-0 py-5">
                                <h3 class="mb-4 font-bold text-blue-950">{{ $row['label'] }}</h3>
                                <x-decision-fields :fields="['あなたの希望' => $row['preference'], 'この求人の公開情報' => $row['job']]" />
                            </section>
                        @endforeach
                    </div>
                    @include('query.partials.preference-link', ['preferencePage' => $page, 'returnJob' => $job->id, 'preferenceLinkLabel' => '詳細希望を変更する'])
                </x-decision-section>
            @endif
        @foreach ($view['sections'] as $section)
            @if (!in_array($section['id'], ['fit', 'design', 'phases', 'tools', 'collaboration', 'typical-day', 'evidence', 'routes']))
                <x-decision-section :id="$section['id']" :title="$section['title']">
                    @include('query.partials.published-section-content')
                </x-decision-section>
            @endif
        @endforeach
        </div>
    </details>
    <x-decision-section id="evidence" title="根拠と掲載元">
        @include('query.partials.published-section-content', ['section' => $sections['evidence']])
    </x-decision-section>
    @include('query.partials.detail-application')
    <a href="{{ $backUrl }}" class="jobdd-link inline-flex min-h-12 items-center">求人一覧へ戻る</a>
</main>
<x-site-footer />
</body>
</html>
