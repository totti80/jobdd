                <div class="mt-5 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-emerald-50 px-3 py-2 text-emerald-800">確認できた条件 {{ $fit['summary']['confirmed_matches'] }}</span>
                    <span class="rounded-full bg-amber-50 px-3 py-2 text-amber-900">条件と異なる点 {{ $fit['summary']['confirmed_mismatches'] }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">未確認 {{ $fit['summary']['unknowns'] }}</span>
                </div>
<div class="mt-5 divide-y divide-slate-200">
    @foreach ($fit['axes'] as $axis)
        @include('query.partials.axis', ['axis' => $axis])
    @endforeach
</div>
