@php
    $tools = \App\Services\JobDecisionUseCaseService::TOOLS;
    $statuses = \App\Support\JobDecisionPresenter::STATUSES;
    $badgeClasses = \App\Support\JobDecisionPresenter::BADGECLASSES;
    $labels = \App\Support\JobDecisionPresenter::LABELS;
    $roles = \App\Support\JobDecisionPresenter::ROLES;
    $reasons = \App\Support\JobDecisionPresenter::REASONS;
@endphp
                        @php($axisName = $labels[$axis['key']] ?? ($tools[str_replace('tool_use:', '', $axis['key'])] ?? '比較条件'))
                        <div class="py-4" data-status="{{ $axis['status'] }}">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-semibold">{{ $axisName }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badgeClasses[$axis['status']] ?? $badgeClasses['unknown'] }}">{{ $statuses[$axis['status']] ?? '未確認' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6">{{ $reasons[$axis['reason_code']] ?? '保存済み情報では、この条件を確認できません。' }}</p>
                            @if ($axis['evidence'])
                                <details class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                                    <summary class="cursor-pointer font-semibold text-blue-800">根拠を見る（{{ count($axis['evidence']) }}件）</summary>
                                    <div class="mt-3 space-y-4">
                                        @foreach ($axis['evidence'] as $evidence)
                                            <div class="min-w-0 border-l-2 border-slate-300 pl-3">
                                                @if ($evidence['kind'] === 'job_fact')
                                                    <p class="whitespace-pre-wrap break-words leading-6">{{ $evidence['evidence_text'] ?? '根拠本文未確認' }}</p>
                                                    <p class="mt-2 text-slate-600">記載の文脈：{{ $roles[$evidence['context']['role'] ?? 'unknown'] ?? '文脈未確認' }}</p>
                                                    <p class="mt-1 break-words text-slate-600">{{ $evidence['context']['reason'] ?? '文脈の理由は未確認です。' }}</p>
                                                @else
                                                    <p class="whitespace-pre-wrap break-words">{{ $labels[$evidence['field']] ?? '掲載情報' }}：{{ $evidence['value'] ?? '未確認' }}</p>
                                                @endif
                                                <p class="mt-2 break-words text-xs text-slate-500">情報提供元：{{ $fit['source']['provider_key'] ?? '未確認' }} / 求人元：{{ $fit['source']['url'] ?? '未確認' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
