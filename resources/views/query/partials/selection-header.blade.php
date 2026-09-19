@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
    $backUrl = route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]);
@endphp
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl space-y-3 px-4 py-6 sm:px-6">
        <p class="text-xl font-bold text-blue-950">JobDD</p>
        <h1 class="text-3xl font-bold">{{ $heading }}</h1>
        <p class="text-sm leading-6 text-slate-600">{{ $query['occupation'] }} / 希望地域：{{ $query['region'] ?? '未指定' }} / 希望年収：{{ $query['salary_min'] !== null ? $query['salary_min'].'万円以上' : '下限未指定' }}{{ $query['salary_max'] !== null ? '・上限'.$query['salary_max'].'万円' : '' }}</p>
        <p class="text-sm">選択中ツール：{{ $selected_tools ? implode('・', array_map(fn ($k) => $tools[$k] ?? $k, $selected_tools)) : '未指定' }}</p>
        <nav aria-label="求人比較のナビゲーション" class="flex flex-wrap gap-x-6 gap-y-2">
        <a href="{{ route('jobs.start') }}" class="inline-block py-2 text-sm font-semibold text-blue-800 underline">新しい条件を入力する</a>
        <a href="{{ $backUrl }}" class="inline-block py-2 text-sm font-semibold text-blue-800 underline">求人一覧へ戻る（{{ $page }}ページ目）</a>
        </nav>
    </div>
</header>
