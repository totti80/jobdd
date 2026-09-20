@php
    $axisName = \App\Support\JobDecisionPresenter::LABELS[$axis['key']] ?? (\App\Services\JobDecisionUseCaseService::TOOLS[str_replace('tool_use:', '', $axis['key'])] ?? '比較条件');
    $axisId = ($idPrefix ?? 'axis').'-'.str_replace(':', '-', $axis['key']);
@endphp
<div class="min-w-0 py-4" data-status="{{ $axis['status'] }}" data-axis="{{ $axis['key'] }}">
    <div class="flex flex-wrap items-center gap-3">
        @if ($showHeading ?? true)
            <h3 class="font-semibold" id="{{ $axisId }}">{{ $axisName }}</h3>
        @endif
        @include('query.partials.status-badge', ['status' => $axis['status']])
    </div>
    <p class="mt-2 leading-7">{{ \App\Support\JobDecisionPresenter::REASONS[$axis['reason_code']] ?? '保存済み情報では、この条件を確認できません。' }}</p>
    @if ($axis['evidence'])
        <details class="jobdd-details mt-3">
            <summary>{{ $axisName }}の根拠を見る（{{ count($axis['evidence']) }}件）@if (!empty($jobLabel))<span class="sr-only">：{{ $jobLabel }}</span>@endif</summary>
            <div class="space-y-5 p-4 pt-2">
                @foreach ($axis['evidence'] as $evidence)
                    @include('query.partials.evidence-block', ['evidence' => $evidence, 'source' => $fit['source'], 'evidenceLabel' => $axisName])
                @endforeach
            </div>
        </details>
    @else
        <p class="mt-2 text-sm text-slate-600">根拠を確認できていません</p>
    @endif
</div>
