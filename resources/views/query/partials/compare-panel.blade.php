<aside class="jobdd-selection-panel min-w-0 lg:sticky lg:top-6" aria-labelledby="comparison-title">
    <form id="compare-selection" data-query-id="{{ $query['public_id'] }}" method="GET" action="{{ route('query.jobs.compare', ['userQuery' => $query['public_id']]) }}" class="jobdd-card jobdd-compare-panel">
        <input type="hidden" name="sort" value="{{ $sort ?? 'fit' }}">
        <input type="hidden" name="page" value="{{ $pagination['page'] }}">
        @foreach ($selected_tools as $tool)<input type="hidden" name="tools[]" value="{{ $tool }}">@endforeach
        <h2 id="comparison-title" tabindex="-1" class="text-xl font-bold text-blue-950"><x-jobdd-icon name="compare" /> 比較する求人</h2>
        <p class="jobdd-compare-count" data-selection-count hidden>0 / 3件</p>
        <p id="compare-help" class="mt-3 text-sm leading-6 text-slate-600">2〜3件選んでください。</p>
        <ul class="mt-3" data-selected-jobs aria-label="選択中の求人"></ul>
        <button type="submit" data-compare-submit class="jobdd-button mt-4 w-full">選んだ求人を比較する</button>
        <p class="mt-3 text-xs leading-6 text-slate-600">同じタブ・同じ検索条件では、ページを移動しても選択を保持します。</p>
        <p class="sr-only" data-selection-announcement aria-live="polite" aria-atomic="true"></p>
    </form>
</aside>
