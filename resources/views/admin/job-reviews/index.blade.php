<x-company-layout title="求人の公開審査">
    <h1 class="text-2xl font-bold text-blue-950">求人の公開審査</h1><p class="mt-3 text-slate-600">審査待ちの申請を古い順に表示しています。</p>
    <div class="mt-6 space-y-4">
        @forelse($jobs as $job)<x-company-card><p class="break-words text-sm text-slate-600">{{ $job->company?->name }}</p><h2 class="mt-2 break-words text-lg font-bold">{{ $job->title }}</h2><p class="mt-3 text-sm">{{ $job->status === 'published' ? '再公開申請（前回公開版を維持）' : '新規公開申請' }} ／ {{ $job->review_requested_at?->format('Y/m/d H:i') }}</p><x-company-action class="mt-4" :href="route('admin.job-reviews.show', $job)">内容を確認・審査</x-company-action></x-company-card>@empty<x-company-card>審査待ちの申請はありません。</x-company-card>@endforelse
    </div><div class="mt-6">{{ $jobs->links() }}</div>
</x-company-layout>
