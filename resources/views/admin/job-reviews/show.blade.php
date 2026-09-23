<x-company-layout title="公開申請の確認">
    <a href="{{ route('admin.job-reviews.index') }}" class="inline-flex min-h-11 items-center text-blue-800 underline">審査一覧へ戻る</a>
    <h1 class="mt-3 text-2xl font-bold text-blue-950">公開申請の確認</h1>
    <p class="mt-3 text-sm font-bold text-amber-900">PREVIEW — 未公開または編集中の内容です</p>
    <x-company-card class="my-6">
        @include('company.jobs.partials.review-feedback')
        <p class="mt-4 text-sm leading-7">必須入力・体裁・明らかな矛盾や不適切な記載を確認してください。承認は企業申告内容の真偽を保証するものではありません。</p>
        @if($job->review_status === 'pending_review')
            <form method="POST" action="{{ route('admin.job-reviews.approve', $job) }}" class="mt-5">@csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}"><x-company-action type="submit" :disabled="count($publishErrors) > 0">確認した内容を承認して公開</x-company-action></form>
            <form method="POST" action="{{ route('admin.job-reviews.changes-requested', $job) }}" class="mt-6 border-t border-slate-200 pt-5">@csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}"><label for="review_note" class="block font-semibold">差戻し理由（必須）</label><textarea id="review_note" name="review_note" required maxlength="10000" rows="4" class="mt-2 w-full min-w-0 rounded-lg border border-slate-400 p-3">{{ is_scalar(old('review_note')) ? old('review_note') : '' }}</textarea><x-company-action type="submit" class="mt-3">理由を添えて差戻す</x-company-action></form>
        @else<p class="mt-4 text-sm">現在、この求人は審査待ちではありません。</p>@endif
    </x-company-card>
    @include('company.jobs.partials.preview-content')
</x-company-layout>
