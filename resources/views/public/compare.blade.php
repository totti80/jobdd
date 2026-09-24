<x-public-layout title="求人を比較 | JobDD">
    <div class="site-container py-10 sm:py-16">
        <x-page-hero title="比較したい求人を選んでください" eyebrow="求人を比較">仕事の内容や条件を同じ軸で並べて、違いを確認できます。</x-page-hero>
        <section class="jobdd-card max-w-3xl" data-compare-entry data-query-id="{{ $queryId }}" data-resolve-url="{{ route('public.compare', ['query' => $queryId, ...$context]) }}" data-invalid-selection="{{ $invalidSelection ? 'true' : 'false' }}">
            <h2 class="text-xl font-bold text-blue-950">求人一覧から2〜3件を選択</h2>
            <p class="mt-4 leading-8" data-compare-entry-message>{{ $invalidSelection ? '選択した求人を確認できませんでした。公開状態や検索条件が変わった可能性があります。一覧から選び直してください。' : 'このタブで選択した求人がある場合は、比較へ進めます。まだ選んでいない場合は求人一覧へ進んでください。' }}</p>
            <div class="site-actions"><a href="{{ $listUrl }}" class="jobdd-button">求人一覧で選ぶ</a><a data-compare-entry-link hidden class="site-button-secondary">選択した求人を比較する</a></div>
            <noscript><p class="mt-4 leading-7">JavaScriptが無効です。求人一覧のチェックボックスで選んで比較できます。</p></noscript>
        </section>
    </div>
</x-public-layout>
