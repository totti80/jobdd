<details class="w-full min-w-0 rounded-lg border border-slate-200 p-3 sm:w-auto">
    <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm text-blue-800 underline">公開を停止</summary>
    <div class="mt-3 max-w-lg">
        <p class="font-semibold text-blue-950">この求人の公開を停止しますか？</p>
        <p class="mt-2 text-sm leading-7 text-slate-600">求職者向けの求人一覧・比較・応募導線から非表示になります。求人データと過去に承認された公開内容は保持されます。</p>
        @if($job->review_status === 'pending_review')<p class="mt-2 text-sm leading-7 text-amber-900">審査中の更新申請は取り下げられます。再公開する場合はPreviewから改めて申請してください。</p>@endif
        <form method="POST" action="{{ route('company.jobs.pause', $job) }}" class="mt-3 flex flex-col gap-3 sm:flex-row">
            @csrf
            <button type="submit" class="min-h-11 rounded-lg border border-slate-400 px-4 py-2 font-semibold text-slate-800">公開を停止する</button>
            <button type="button" onclick="this.closest('details').open = false" class="min-h-11 px-4 py-2 text-sm text-blue-800 underline">戻る</button>
        </form>
    </div>
</details>
