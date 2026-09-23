<x-company-layout title="企業ダッシュボード">
    <h1 class="text-2xl font-bold text-blue-950 sm:text-3xl">企業ダッシュボード</h1>
    <p class="mt-2 text-slate-600">求人の基本情報と公開状況を確認できます。</p>
    @if (!$company)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">管理対象の企業が設定されていません</h2>
            <p class="mt-2 text-slate-600">このアカウントに所属企業がないため、企業の求人一覧は表示していません。</p>
            @if(auth()->user()->isPlatformOwner())<a class="mt-4 inline-flex min-h-11 items-center text-blue-800 underline" href="{{ route('admin.agency-facts.index') }}">管理画面へ</a>@endif
        </section>
    @else
        <section aria-label="企業情報" class="mt-6 flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:p-6">
            <h2 class="min-w-0 break-words text-xl font-bold text-blue-950">{{ $company->name }}</h2>
            <a href="{{ route('company.jobs.create') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800">＋ 求人を作成</a>
        </section>
        <dl class="mt-5 grid grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-600">公開中求人</dt><dd class="mt-2 text-3xl font-bold text-blue-950" data-count="published">{{ $counts['published'] ?? 0 }}<span class="ml-2 text-sm font-normal">件</span></dd></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-600">下書き求人</dt><dd class="mt-2 text-3xl font-bold text-blue-950" data-count="draft">{{ $counts['draft'] ?? 0 }}<span class="ml-2 text-sm font-normal">件</span></dd></div>
        </dl>
        <section class="mt-8" aria-labelledby="job-list-heading">
            <h2 id="job-list-heading" class="text-xl font-bold text-blue-950">求人一覧</h2>
            <div class="mt-4 space-y-4">
                @forelse ($jobs as $job)
                    <article class="grid min-w-0 gap-4 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] md:items-center">
                        <h3 class="min-w-0 break-words font-bold text-blue-950">{{ $job->title }}</h3>
                        <dl class="grid min-w-0 grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div class="min-w-0"><dt class="text-slate-500">職種</dt><dd class="mt-1 break-words">{{ $job->occupation ?: '未入力' }}</dd></div>
                            <div><dt class="text-slate-500">公開状態</dt><dd class="mt-1">{{ \App\Models\JobPosting::STATUS_LABELS[$job->status] ?? $job->status }}</dd></div>
                            <div><dt class="text-slate-500">公開審査</dt><dd class="mt-1">{{ \App\Models\JobPosting::REVIEW_STATUS_LABELS[$job->review_status] ?? $job->review_status }}</dd></div>
                            <div><dt class="text-slate-500">更新日時</dt><dd class="mt-1">{{ $job->updated_at?->format('Y/m/d H:i') ?? '―' }}</dd></div>
                        </dl>
                        <div class="flex flex-col gap-2"><a href="{{ route('company.jobs.preview', $job) }}" class="inline-flex min-h-11 items-center justify-center text-sm text-blue-800 underline">Preview・公開申請</a><a href="{{ route('company.jobs.basic.edit', $job) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-800">編集<span class="sr-only">：{{ $job->title }}</span></a></div>
                        @if($job->review_note)<p class="whitespace-pre-wrap break-words text-sm text-amber-900 md:col-span-3">差戻し理由：{{ $job->review_note }}</p>@endif
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">求人はまだありません。「求人を作成」から基本情報を入力してください。</p>
                @endforelse
            </div>
            <div class="mt-6">{{ $jobs->links() }}</div>
        </section>
    @endif
</x-company-layout>
