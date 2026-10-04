@php
    $editable = $job->authoringEditable() && $job->review_status !== 'pending_review' && ! $state['invalid'];
    $canRequest = $editable && ! (in_array($job->status, ['published', 'paused']) && $job->review_status === 'approved' && ! $state['changed']);
    $editPrimary = $editable && $job->status === 'published' && ($job->review_status === 'changes_requested' || ($job->review_status === 'approved' && ! $state['changed']));
    $nextLabel = $state['can_resume'] ? '公開を再開' : ($editPrimary ? ($job->review_status === 'changes_requested' ? '修正する' : '編集') : ($canRequest ? ($job->status === 'paused' ? '再公開申請へ進む' : ($job->status === 'published' ? '更新内容の公開申請へ進む' : '公開申請へ進む')) : '審査状況を見る'));
    $nextUrl = $editPrimary ? route('company.jobs.basic.edit', $job) : ($canRequest ? '#publish-actions' : '#review-status');
@endphp
<x-company-layout title="求人Preview・公開審査状況">
    <a href="{{ route('company.dashboard') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">企業ダッシュボードへ戻る</a>
    <x-company-hero title="求人Preview" eyebrow="PREVIEW">{{ $job->status === 'published' && ($state['changed'] || in_array($job->review_status, ['pending_review', 'changes_requested'])) ? '更新内容のPreviewです' : ($job->status === 'published' ? '現在公開中の内容を確認できます' : '未公開または編集中の内容です') }}<p class="mt-2 text-sm">公開済み求人も、再申請が承認されるまでは前回の公開内容が維持されます。</p></x-company-hero>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            @include('company.jobs.partials.review-status')
            <section id="preview-content" aria-label="求職者からの見え方" class="min-w-0 scroll-mt-6">
                <p class="mb-4 text-sm leading-7 text-slate-600">求職者からの見え方を確認してください。このPreviewは確認専用です。</p>
                @include('company.jobs.partials.preview-content')
            </section>
            <x-company-card id="publish-actions">
                <h2 class="text-xl font-bold text-blue-950">{{ $canRequest ? '公開申請前の確認' : ($job->review_status === 'pending_review' ? '審査状況を確認する' : '次の操作') }}</h2>
                @if($editable)<div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap"><x-company-action :href="route('company.jobs.basic.edit', $job)">Level 1を編集</x-company-action><x-company-action :href="route('company.jobs.structured.edit', [$job, 1])">Level 2を編集</x-company-action></div>@endif
                <p class="mt-4 text-sm leading-7 text-slate-600">公開申請の必須項目はLevel 1のみです。Level 2は任意です。入力した任意項目に不備がある場合は修正してください。入力充足率が100%である必要はありません。</p>
                @include('company.jobs.partials.publish-requirements')
                @if($canRequest)
                    <p id="publish-explanation" class="mt-5 rounded-lg bg-blue-50 p-4 text-sm leading-7 text-blue-950">公開申請後、JobDD運営が公開内容を確認します。承認後に求職者向けへ公開されます。</p>
                    <form method="POST" action="{{ route('company.jobs.review-request', $job) }}" class="mt-4">@csrf<x-company-action type="submit" class="{{ $editPrimary ? 'border border-blue-700 bg-white! text-blue-800!' : '' }}" aria-describedby="publish-explanation" :disabled="count($publishErrors) > 0">{{ $job->status === 'paused' ? '再公開申請する' : ($job->status === 'published' ? '更新内容を公開申請する' : '公開申請する') }}</x-company-action></form>
                @elseif($state['can_resume'])
                    <form method="POST" action="{{ route('company.jobs.resume', $job) }}" class="mt-4">@csrf<x-company-action type="submit">公開を再開</x-company-action></form>
                @elseif($editPrimary)
                    <x-company-action class="mt-4 bg-blue-700! text-white!" :href="$nextUrl">{{ $nextLabel }}</x-company-action>
                @else
                    <a href="#review-status" class="mt-4 inline-flex min-h-12 items-center justify-center rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white">審査状況を見る</a>
                @endif
                @if($job->review_status === 'changes_requested')<a href="#review-note" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline">修正内容を見る</a>@endif
            </x-company-card>
            @if($job->review_status === 'changes_requested' || $job->review_note)
            <x-company-card id="review-note"><h2 class="text-lg font-bold text-blue-950">JobDDからの確認内容</h2><p class="mt-4 whitespace-pre-wrap break-words rounded-lg bg-amber-50 p-4 text-sm leading-7 text-amber-950">{{ $job->review_note ?: '確認内容が記録されていません。JobDD運営へ確認してください。' }}</p></x-company-card>
            @endif
        </div>
        <aside class="min-w-0 space-y-5">
            <x-company-card><h2 class="font-bold text-blue-950">{{ $job->review_status === 'pending_review' ? '公開審査中' : '確認後の次の操作' }}</h2><p class="mt-3 text-sm leading-7 text-slate-600">{{ $job->review_status === 'pending_review' ? '運営が確認中です。Previewと審査状況を確認できます。' : ($state['can_resume'] ? '前回承認された内容をそのまま再表示できます。再審査は不要です。' : ($editPrimary && $job->review_status === 'approved' ? '現在公開中です。内容を更新する場合は編集へ進んでください。' : '仕事内容と応募条件を確認し、必要な修正を保存してから公開申請してください。')) }}</p>@if($state['can_resume'])<form method="POST" action="{{ route('company.jobs.resume', $job) }}" class="mt-4">@csrf<x-company-action type="submit" class="w-full">公開を再開</x-company-action></form>@else<a href="{{ $nextUrl }}" class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">{{ $nextLabel }}</a>@endif @if($job->status === 'published')<a href="{{ route('public.job', $job) }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline">現在の公開ページを見る</a>@endif</x-company-card>
            <x-company-guide />
        </aside>
    </div>
</x-company-layout>
