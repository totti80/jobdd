<x-company-layout title="求人の公開審査">
    <x-company-hero title="求人の公開審査" eyebrow="ADMIN">企業からの公開申請を確認し、承認または修正を依頼します。<p class="mt-2 text-sm">審査待ちの申請を古い順に表示しています。</p></x-company-hero>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0">
            <h2 class="text-xl font-bold text-blue-950">審査待ちの申請</h2>
            <div class="mt-4 space-y-4">
                @forelse($jobs as $job)
                <x-company-card>
                    <p class="break-words text-sm text-slate-600">{{ $job->company?->name ?? '企業情報を確認してください' }}</p>
                    <h3 class="mt-2 break-words text-lg font-bold text-blue-950">{{ $job->title }}</h3>
                    <dl class="mt-4 grid min-w-0 grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-slate-500">職種</dt><dd class="mt-1 break-words">{{ $job->occupation ?: '未入力' }}</dd></div>
                        <div><dt class="text-slate-500">公開状態</dt><dd class="mt-1 font-semibold text-blue-950">{{ $job->status === 'paused' ? '公開停止中' : ($job->status === 'published' ? '公開中' : ($job->status === 'draft' ? '未公開' : '状態を確認してください')) }}</dd></div>
                        <div><dt class="text-slate-500">審査状態</dt><dd class="mt-1">{{ $job->status === 'paused' ? '再公開審査中' : ($job->status === 'published' ? '更新内容を審査中' : '審査中') }}</dd></div>
                        <div><dt class="text-slate-500">公開申請日時</dt><dd class="mt-1">{{ $job->review_requested_at?->format('Y/m/d H:i') ?? '―' }}</dd></div>
                        <div><dt class="text-slate-500">最終更新</dt><dd class="mt-1">{{ $job->updated_at?->format('Y/m/d H:i') ?? '―' }}</dd></div>
                    </dl>
                    @if($job->status === 'published')<p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm leading-7 text-blue-950">再公開申請です。承認までは前回公開版を維持します。</p>@endif
                    @if($job->status === 'paused')<p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm leading-7 text-blue-950">再公開申請です。現在は公開停止中です。承認後に再公開されます。</p>@endif
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-company-action class="bg-blue-700! text-white!" :href="route('admin.job-reviews.show', $job)">審査する<span class="sr-only">：{{ $job->title }}</span></x-company-action><x-company-action :href="route('admin.job-reviews.show', $job).'#preview-content'">Preview<span class="sr-only">：{{ $job->title }}</span></x-company-action></div>
                </x-company-card>
                @empty<x-company-card>審査待ちの申請はありません。</x-company-card>@endforelse
            </div>
            <div class="mt-6">{{ $jobs->links() }}</div>
        </div>
        <aside class="min-w-0 space-y-5">
            <x-company-card><h2 class="font-bold text-blue-950">公開確認の観点</h2><ul class="mt-4 list-inside list-disc space-y-3 text-sm text-slate-600"><li>公開可能な体裁</li><li>必須入力</li><li>明らかな矛盾</li><li>不適切内容</li></ul><p class="mt-4 text-sm leading-7 text-slate-600">企業申告内容の真偽を保証する審査ではありません。</p></x-company-card>
            <x-company-guide />
        </aside>
    </div>
</x-company-layout>
