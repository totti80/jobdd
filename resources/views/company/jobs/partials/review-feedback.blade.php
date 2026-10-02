<dl class="grid min-w-0 grid-cols-2 gap-4 text-sm">
    <div><dt class="text-slate-500">公開状態</dt><dd class="mt-1 font-semibold text-blue-950">{{ $state['publication'] ?? (['draft' => '未公開', 'published' => '公開中', 'paused' => '公開停止中'][$job->status] ?? '公開状況を確認してください') }}</dd></div>
    <div><dt class="text-slate-500">公開審査</dt><dd class="mt-1 font-semibold text-blue-950">{{ $state['review'] ?? (['not_submitted' => '未申請', 'pending_review' => '審査中', 'changes_requested' => '修正をお願いします', 'approved' => '承認済み'][$job->review_status] ?? '審査状況を確認してください') }}</dd></div>
</dl>
@if($state['invalid'] ?? false)<p role="alert" class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-950">状態不整合があります。JobDD運営による確認が必要です。</p>@endif
@if($errors->any())<div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900"><h3 class="font-semibold">入力内容を確認してください</h3><ul class="mt-2 list-inside list-disc text-sm leading-7">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
