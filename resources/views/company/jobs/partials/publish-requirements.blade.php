@if($publishErrors)
<div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
    <h3 class="font-bold text-amber-950">公開申請に必要な入力</h3>
    <p class="mt-2 text-sm leading-6 text-slate-700">Level 1の基本情報、Level 2の仕事の中身、応募URLを確認してください。下記の不足項目を修正すると公開申請できます。</p>
    <ul class="mt-3 list-inside list-disc space-y-1 break-words text-sm leading-7 text-amber-950">@foreach($publishErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
    @if($job->review_status !== 'pending_review' && $job->authoringEditable())
    <div class="mt-3 flex flex-wrap gap-4 text-sm"><a href="{{ route('company.jobs.basic.edit', $job) }}" class="inline-flex min-h-11 items-center text-blue-800 underline">Level 1の基本情報を修正</a><a href="{{ route('company.jobs.structured.edit', [$job, 1]) }}" class="inline-flex min-h-11 items-center text-blue-800 underline">Level 2の仕事の中身を修正</a></div>
    @endif
</div>
@endif
