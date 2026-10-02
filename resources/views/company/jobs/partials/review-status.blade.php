<section id="review-status" aria-labelledby="review-status-heading" class="scroll-mt-6 rounded-xl border border-blue-100 bg-white p-5 sm:p-7">
    <h2 id="review-status-heading" class="text-xl font-bold text-blue-950">{{ ['pending_review' => '公開審査中', 'changes_requested' => '修正をお願いします', 'approved' => $job->status === 'published' ? '公開されています' : '状態を確認してください'][$job->review_status] ?? '公開申請前の確認' }}</h2>
    <div class="mt-4">@include('company.jobs.partials.review-feedback')</div>
    <dl class="mt-4 grid min-w-0 grid-cols-2 gap-4 text-sm">
        <div><dt class="text-slate-500">申請日時</dt><dd class="mt-1">{{ $job->review_requested_at?->format('Y/m/d H:i') ?? '未申請' }}</dd></div>
        @if($job->reviewed_at)<div><dt class="text-slate-500">確認日時</dt><dd class="mt-1">{{ $job->reviewed_at->format('Y/m/d H:i') }}</dd></div>@endif
        @if($job->status === 'published')<div><dt class="text-slate-500">公開日時</dt><dd class="mt-1">{{ $job->publishedProfile?->published_at?->format('Y/m/d H:i') ?? $job->published_at?->format('Y/m/d H:i') ?? '―' }}</dd></div>@endif
    </dl>
    @if($job->review_status === 'pending_review')
        <p class="mt-5 rounded-lg bg-blue-50 p-4 text-sm leading-7 text-blue-950">JobDD運営が公開内容を確認しています。審査中は編集できません。差戻し後に編集を再開できます。</p>
        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $job->status === 'published' ? '現在公開中の内容はそのまま表示されています。' : '承認されるまで求職者向けには公開されません。' }}</p>
    @elseif($job->review_status === 'changes_requested')
        <p class="mt-4 text-sm leading-7 text-slate-600">確認内容に沿って修正し、Previewで内容を確認してから再申請してください。</p>
        @if($job->status === 'published')<p class="mt-3 text-sm leading-7 text-slate-600">現在公開中の内容は前回承認された内容です。</p>@endif
    @elseif($job->status === 'published' && $job->review_status === 'approved')
        <p class="mt-4 font-semibold text-blue-950">現在公開中</p>
        @if($state['changed'])<p class="mt-2 rounded-lg bg-blue-50 p-4 text-sm leading-7 text-blue-950">更新作業中です。更新内容は承認後に公開されます。</p>@endif
    @else
        <p class="mt-4 text-sm leading-7 text-slate-600">公開申請後、JobDD運営が公開内容を確認します。承認後に求職者向けへ公開されます。</p>
    @endif
    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        @if(! $state['invalid'] && $job->review_status === 'changes_requested' && $job->authoringEditable())<x-company-action class="bg-blue-700! text-white!" :href="route('company.jobs.basic.edit', $job)">修正する</x-company-action>@endif
        @if(! $state['invalid'] && $job->review_status === 'approved' && $job->authoringEditable())<x-company-action class="{{ ! $state['changed'] ? 'bg-blue-700! text-white!' : '' }}" :href="route('company.jobs.basic.edit', $job)">{{ $state['changed'] ? '編集を続ける' : '編集' }}</x-company-action>@endif
        <x-company-action href="#preview-content">Preview</x-company-action>
        @if($job->status === 'published')<x-company-action :href="route('public.job', $job)">{{ $state['changed'] || $job->review_status !== 'approved' ? '現在の公開ページを見る' : '公開ページを見る' }}</x-company-action>@endif
    </div>
</section>
