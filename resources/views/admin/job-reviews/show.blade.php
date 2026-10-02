<x-company-layout title="公開申請の確認">
    <a href="{{ route('admin.job-reviews.index') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">審査一覧へ戻る</a>
    <x-company-hero title="公開申請の確認" eyebrow="ADMIN / PREVIEW">{{ $job->review_status === 'pending_review' ? '今回申請された内容を確認し、承認または修正を依頼してください。' : '現在、この求人は審査待ちではありません。最新の企業入力内容を確認できます。' }}</x-company-hero>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-company-card>
                <p class="break-words text-sm text-slate-600">{{ $job->company?->name }}</p><h2 class="mt-2 break-words text-xl font-bold text-blue-950">{{ $job->title }}</h2>
                <div class="mt-4">@include('company.jobs.partials.review-feedback')</div>
                <dl class="mt-4 grid min-w-0 grid-cols-2 gap-4 text-sm"><div><dt class="text-slate-500">公開申請日時</dt><dd class="mt-1">{{ $job->review_requested_at?->format('Y/m/d H:i') ?? '未申請' }}</dd></div><div><dt class="text-slate-500">最終更新</dt><dd class="mt-1">{{ $job->updated_at?->format('Y/m/d H:i') ?? '―' }}</dd></div></dl>
                @if($job->status === 'published')<p class="mt-4 rounded-lg bg-blue-50 p-4 text-sm leading-7 text-blue-950">現在公開中の求人があります。以下は最新Authoring内容です。今回申請された更新内容を承認するまで、現在公開中の内容はそのまま表示されています。</p><a href="{{ route('public.job', $job) }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline">現在の公開ページを見る</a>@elseif($job->status === 'paused')<p class="mt-4 rounded-lg bg-blue-50 p-4 text-sm leading-7 text-blue-950">現在は公開停止中です。再公開申請の承認時のみ、以下の更新内容が求職者向けに再公開されます。</p>@else<p class="mt-4 text-sm text-slate-600">未公開の求人です。承認時のみ求職者向けに公開されます。</p>@endif
                <div class="mt-4 flex flex-wrap gap-4"><a href="#preview-content" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">Preview</a><a href="#review-actions" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline">審査操作へ</a></div>
            </x-company-card>
            <section id="preview-content" class="min-w-0 scroll-mt-6" aria-labelledby="authoring-heading"><h2 id="authoring-heading" class="mb-4 text-xl font-bold text-blue-950">{{ $job->review_status === 'pending_review' ? '今回申請された内容' : '最新の企業入力内容' }}</h2>@include('company.jobs.partials.preview-content')</section>
            <x-company-card id="review-actions">
                <h2 class="text-xl font-bold text-blue-950">審査操作</h2>
                <p class="mt-3 text-sm leading-7 text-slate-600">必須入力・体裁・明らかな矛盾や不適切な記載を確認してください。承認は企業申告内容の真偽を保証するものではありません。</p>
                @include('company.jobs.partials.publish-requirements')
                @if($job->review_status === 'pending_review')
                    <form method="POST" action="{{ route('admin.job-reviews.approve', $job) }}" class="mt-5 rounded-lg border border-blue-100 bg-blue-50 p-4">
                        @csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}">
                        <p id="approve-help" class="text-sm leading-7 text-blue-950">承認すると、ここで確認した内容が求職者向けに公開されます。公開済み求人では、前回の公開内容が今回の更新内容に置き換わります。</p>
                        <label for="approve-confirm" class="mt-4 flex min-h-11 items-start gap-3 text-sm leading-7"><input id="approve-confirm" type="checkbox" required class="mt-1 h-5 w-5 shrink-0 rounded border-slate-400" aria-describedby="approve-help" @disabled(count($publishErrors) > 0)><span>申請内容を確認し、公開することを確認しました。</span></label>
                        <x-company-action type="submit" class="mt-3 w-full sm:w-auto" :disabled="count($publishErrors) > 0">承認して公開</x-company-action>
                    </form>
                    <form method="POST" action="{{ route('admin.job-reviews.changes-requested', $job) }}" class="mt-6 border-t border-slate-200 pt-5">
                        @csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}">
                        <label for="review_note" class="block font-semibold text-blue-950">差戻し理由（必須）</label>
                        <p id="review-note-help" class="mt-2 text-sm leading-7 text-slate-600">企業担当者が修正できるよう、確認したい項目と必要な修正を具体的に記載してください。</p>
                        <textarea id="review_note" name="review_note" required maxlength="10000" rows="5" aria-describedby="review-note-help" @if($errors->has('review_note')) aria-invalid="true" @endif class="mt-3 w-full min-w-0 rounded-lg border border-slate-400 p-3">{{ is_scalar(old('review_note')) ? old('review_note') : '' }}</textarea>
                        <x-company-action type="submit" class="mt-3 w-full border border-amber-700 bg-amber-50! text-amber-950! sm:w-auto">修正を依頼</x-company-action>
                    </form>
                @else<p class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-950">現在、この求人は審査待ちではありません。承認・修正依頼は操作できません。</p>@endif
            </x-company-card>
            @if($job->review_note)<x-company-card><h2 class="font-bold text-blue-950">企業へ伝えた確認内容</h2><p class="mt-3 whitespace-pre-wrap break-words rounded-lg bg-amber-50 p-4 text-sm leading-7 text-amber-950">{{ $job->review_note }}</p></x-company-card>@endif
        </div>
        <aside class="min-w-0 space-y-5"><x-company-card><h2 class="font-bold text-blue-950">公開確認の観点</h2><ul class="mt-4 list-inside list-disc space-y-3 text-sm text-slate-600"><li>公開可能な体裁</li><li>必須入力</li><li>明らかな矛盾</li><li>不適切内容</li></ul><p class="mt-4 text-sm leading-7 text-slate-600">企業申告内容の真偽を保証する審査ではありません。</p><a href="#review-actions" class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">審査操作へ</a></x-company-card><x-company-guide /></aside>
    </div>
</x-company-layout>
