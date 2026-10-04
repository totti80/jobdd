<x-company-layout title="企業ダッシュボード">
    <section class="grid overflow-hidden rounded-2xl bg-blue-50 md:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 p-6 sm:p-8 md:px-6 xl:px-8">
            <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">企業ダッシュボード</h1>
            <div class="mt-3 max-w-2xl leading-7 text-slate-600">求人の作成・構造化・公開状況を管理します。<p class="mt-2 text-sm">Level 2 Structured Job Profileを入力すると、求職者に仕事の中身をより具体的に伝えられます。</p></div>
        </div>
        <div class="flex min-w-0 items-center justify-end md:min-w-80">
            <img src="{{ asset('images/jobdd/jobdd-hero-kinki.png') }}" alt="" aria-hidden="true" class="pointer-events-none block h-auto w-full object-contain opacity-100 md:h-full md:object-cover">
        </div>
    </section>
    <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]"><div class="min-w-0">
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
        <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach(['published' => '公開中', 'creating' => '作成中', 'pending_review' => '審査中', 'changes_requested' => '修正依頼'] as $key => $label)
            <div class="rounded-xl border border-blue-100 bg-white p-4"><dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="mt-2 text-3xl font-bold text-blue-950" data-count="{{ $key }}">{{ $counts[$key] ?? 0 }}<span class="ml-1 text-sm font-normal">件</span></dd></div>
            @endforeach
        </dl>
        <section class="mt-8" aria-labelledby="job-list-heading">
            <h2 id="job-list-heading" class="text-xl font-bold text-blue-950">求人一覧</h2>
            <div class="mt-4 space-y-4">
                @forelse ($jobs as $job)
                    @php($state = $states[$job->id])
                    <article class="min-w-0 rounded-xl border border-blue-100 bg-white p-5">
                        <h3 class="break-words font-bold text-blue-950">{{ $job->title }}</h3><p class="mt-1 text-sm text-slate-600">{{ $job->occupation ?: '職種：未入力' }}</p>
                        <dl class="mt-4 grid min-w-0 grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div><dt class="text-slate-500">入力状況</dt><dd class="mt-1">{{ $state['completion'] }}%<span class="block text-xs text-slate-500">基本情報・仕事の中身。公開条件とは別です。</span></dd></div>
                            <div><dt class="text-slate-500">公開状況</dt><dd class="mt-1 font-semibold text-blue-950">{{ $state['publication'] }}</dd></div>
                            <div><dt class="text-slate-500">審査状況</dt><dd class="mt-1">{{ $state['review'] }}</dd>@if($job->status === 'published' && $state['changed'] && $job->review_status === 'approved')<p class="mt-1 text-amber-900">更新作業中</p>@endif</div>
                            <div><dt class="text-slate-500">最終更新</dt><dd class="mt-1">{{ $job->updated_at?->format('Y/m/d H:i') ?? '―' }}</dd></div>
                        </dl>
                        @if($state['invalid'])<p role="status" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">状態不整合があります。JobDD運営による確認が必要です。</p>@endif
                        @if($job->status === 'published' && ($state['changed'] || in_array($job->review_status, ['pending_review', 'changes_requested'])))<p class="mt-4 text-sm text-slate-600">現在公開中の内容はそのまま表示されています。</p>@endif
                        @if($job->review_note)<p class="mt-4 whitespace-pre-wrap break-words rounded-lg bg-amber-50 p-3 text-sm text-amber-900">修正内容：{{ $job->review_note }}</p>@endif
                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center" aria-label="操作">
                            @if($state['primary_method'] === 'POST')
                            <form method="POST" action="{{ $state['url'] }}">@csrf<button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800">{{ $state['primary'] }}<span class="sr-only">：{{ $job->title }}</span></button></form>
                            @else
                            <a href="{{ $state['url'] }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800">{{ $state['primary'] }}<span class="sr-only">：{{ $job->title }}</span></a>
                            @endif
                            @foreach($state['secondary'] as $action)<a href="{{ $action['url'] }}" class="inline-flex min-h-11 items-center justify-center text-sm text-blue-800 underline">{{ $action['label'] }}</a>@endforeach
                            @if($job->status === 'published')@include('company.jobs.partials.pause-action')@endif
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">求人はまだありません。「求人を作成」から基本情報を入力してください。</p>
                @endforelse
            </div>
            <div class="mt-6">{{ $jobs->links() }}</div>
        </section>
    @endif
    </div><aside class="min-w-0 space-y-5"><x-company-guide /><section class="rounded-xl border border-blue-100 bg-white p-5"><h2 class="font-bold text-blue-950">JobDDで伝えられる仕事の情報</h2><ul class="mt-4 list-inside list-disc space-y-3 text-sm leading-6 text-slate-600">@foreach(['何を設計する仕事か', '主な設計工程', 'CAD / Toolと使い方', '誰と仕事をするか', '顧客・製造・現場との関係', '仕事の進め方', '仕事の難しさ', '代表的な1日'] as $item)<li>{{ $item }}</li>@endforeach</ul></section></aside></div>
</x-company-layout>
