<x-site-header />
<section class="border-b border-slate-200 bg-white">
    <div class="mx-auto {{ $containerClass ?? 'max-w-7xl' }} space-y-5 px-4 py-6 sm:px-6 lg:py-8">
        <h1 class="jobdd-page-title">{{ $heading }}</h1>
        @include('query.partials.condition-summary', ['query' => $query, 'selected_tools' => $selected_tools])
        <nav aria-label="求人比較のナビゲーション" class="flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('jobs.start') }}" class="jobdd-link inline-flex min-h-11 items-center">新しい条件を入力する</a>
            <a href="{{ route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]) }}" class="jobdd-link inline-flex min-h-11 items-center">求人一覧へ戻る（{{ $page }}ページ目）</a>
        </nav>
    </div>
</section>
