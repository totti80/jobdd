<div class="min-w-0 max-w-[760px] space-y-3 border-l-2 border-slate-300 pl-4">
    <p class="text-sm text-slate-600">確認内容：{{ $evidenceLabel }}</p>
    @if ($evidence['kind'] === 'job_fact')
        <div><p class="text-sm font-semibold text-slate-600">{{ $evidenceTextLabel ?? '根拠の原文' }}</p><p class="mt-1 whitespace-pre-wrap break-words leading-7">{{ $evidence['evidence_text'] ?? '根拠本文未確認' }}</p></div>
        <p class="text-sm leading-6">記載の文脈：{{ \App\Support\JobDecisionPresenter::ROLES[$evidence['context']['role'] ?? 'unknown'] ?? '文脈未確認' }}</p>
        <p class="text-sm leading-6">文脈の説明：{{ $evidence['context']['reason'] ?? '文脈の理由は未確認です。' }}</p>
        <p class="text-sm text-slate-600">根拠の記録日時：{{ $evidence['observed_at'] ?? '未確認' }}</p>
    @else
        <p class="whitespace-pre-wrap break-words leading-7">掲載情報の値 — {{ \App\Support\JobDecisionPresenter::LABELS[$evidence['field']] ?? '掲載情報' }}：{{ $evidence['value'] ?? '未確認' }}</p>
    @endif
    @include('query.partials.provenance', ['source' => $source, 'scopeLabel' => 'この求人の掲載元'])
</div>
