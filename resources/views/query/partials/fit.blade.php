<div class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="確認結果の項目数">
    <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-800">確認できた {{ $fit['summary']['confirmed_matches'] }}項目</span>
    <span class="rounded-full bg-amber-50 px-3 py-2 text-amber-900">条件と異なる {{ $fit['summary']['confirmed_mismatches'] }}項目</span>
    <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">未確認 {{ $fit['summary']['unknowns'] }}項目</span>
</div>
@if (!($summaryOnly ?? false))
    <div class="mt-4 divide-y divide-slate-200">
        @foreach ($fit['axes'] as $axis)
            @include('query.partials.axis', ['axis' => $axis, 'fit' => $fit, 'idPrefix' => $idPrefix ?? 'fit'])
        @endforeach
    </div>
@endif
