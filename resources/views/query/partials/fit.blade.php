<div class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="確認結果の項目数">
    <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-800">確認できた {{ $fit['summary']['confirmed_matches'] }}項目</span>
    <span class="rounded-full bg-amber-50 px-3 py-2 text-amber-900">条件と異なる {{ $fit['summary']['confirmed_mismatches'] }}項目</span>
    <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">未確認 {{ $fit['summary']['unknowns'] }}項目</span>
</div>
@if ($compact ?? false)
    <dl class="jobdd-axis-summary" aria-label="条件ごとの確認結果">
        @foreach ($fit['axes'] as $axis)
            <div data-axis="{{ $axis['key'] }}" data-status="{{ $axis['status'] }}">
                <dt>{{ \App\Support\JobDecisionPresenter::LABELS[$axis['key']] ?? (\App\Services\JobDecisionUseCaseService::TOOLS[str_replace('tool_use:', '', $axis['key'])] ?? '比較条件') }}</dt>
                <dd>@include('query.partials.status-badge', ['status' => $axis['status']])</dd>
            </div>
        @endforeach
    </dl>
@elseif (!($summaryOnly ?? false))
    <div class="mt-4 divide-y divide-slate-200">
        @foreach ($fit['axes'] as $axis)
            @include('query.partials.axis', ['axis' => $axis, 'fit' => $fit, 'idPrefix' => $idPrefix ?? 'fit'])
        @endforeach
    </div>
@endif
