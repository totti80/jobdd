@php($toolNames = \App\Services\JobDecisionUseCaseService::TOOLS)
<dl class="grid gap-4 text-base sm:grid-cols-3">
    <div><dt class="text-sm text-slate-600">職種</dt><dd class="mt-1 font-semibold">{{ $query['occupation'] ?? '未指定' }}</dd></div>
    <div><dt class="text-sm text-slate-600">希望地域</dt><dd class="mt-1 font-semibold">{{ $query['region'] ?? '未指定' }}</dd></div>
    <div><dt class="text-sm text-slate-600">希望年収</dt><dd class="mt-1 font-semibold">{{ $query['salary_min'] !== null ? $query['salary_min'].'万円以上' : '下限未指定' }}{{ $query['salary_max'] !== null ? ' / 上限'.$query['salary_max'].'万円' : '' }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-sm text-slate-600">選択ツール</dt><dd class="mt-1 break-words font-semibold">{{ $selected_tools ? implode('・', array_map(fn ($key) => $toolNames[$key] ?? $key, $selected_tools)) : '未指定' }}</dd></div>
</dl>
