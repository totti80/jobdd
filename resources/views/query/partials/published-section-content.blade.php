@switch($section['type'] ?? 'fields')
    @case('points')
        <p class="mb-3 text-sm leading-6 text-slate-600">企業の公開情報を整理した要点です。推薦や適性の評価ではありません。</p>
        <ul class="list-disc space-y-3 break-words pl-5 leading-7">
            @forelse ($section['points'] as $point)<li>{{ $point }}</li>
            @empty<li>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</li>@endforelse
        </ul>
        @break
    @case('fit')
        <p class="leading-7 text-slate-600">未確認は、条件に合わないという意味ではありません。照合対象は職種・勤務地・年収・希望したCAD / Toolです。</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">企業申告のツール情報だけでは、現在のルールで使用を確認できた扱いにはしません。工程・経験・働き方などは、ご自身で比較するための情報です。</p>
        @include('query.partials.fit', ['fit' => $fit, 'idPrefix' => 'published-job-'.$job->id, 'summaryOnly' => false])
        @break
    @case('tools')
        <p class="mb-4 leading-7 text-slate-600">仕事で使うことと、応募時点で経験が必要なことを分けて表示しています。</p>
        <div class="space-y-5">
            @forelse ($section['tools'] as $tool)
                <section class="min-w-0 rounded-xl border border-slate-200 p-4">
                    <h3 class="mb-4 break-words text-lg font-bold">{{ $tool['name'] }}</h3>
                    <x-decision-fields :fields="$tool['fields']" />
                    <div class="mt-4 flex flex-wrap items-center gap-3"><p class="text-sm font-semibold">あなたとの照合</p>@include('query.partials.status-badge', ['status' => $tool['status']])</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $tool['fit_note'] }}</p>
                </section>
            @empty<p>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</p>@endforelse
        </div>
        @break
    @case('day')
        <p class="mb-5 leading-7 text-slate-600">代表的な1日の例です。毎日同じ業務内容を保証するものではありません。</p>
        <ol class="ml-2 space-y-5 border-l-2 border-blue-200 pl-5" aria-label="代表的な1日の流れ">
            @forelse ($section['items'] as $entry)
                <li class="min-w-0"><h3 class="break-words font-bold text-blue-950">{{ $entry['time'] }}</h3><p class="mt-1 whitespace-pre-wrap break-words leading-7">{{ $entry['activity'] }}</p></li>
            @empty<li>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</li>@endforelse
        </ol>
        @break
    @case('evidence')
        <p class="font-semibold">企業提供情報 · JobDD公開確認済み</p>
        <p class="mt-3 leading-7 text-slate-600">JobDDは公開可能な状態を確認しています。企業申告内容の真実性を保証するものではありません。</p>
        <p class="mt-3 text-sm text-slate-600">情報提供元：{{ $view['source_title'] }} · 公開確認・公開日時：{{ $view['published_at'] }}</p>
        <details class="jobdd-details mt-4">
            <summary>保存された求人本文を見る</summary>
            <p class="whitespace-pre-wrap break-words p-4 leading-7">{{ $job->description ?: '未確認' }}</p>
        </details>
        <details class="jobdd-details mt-4">
            <summary>情報源を見る</summary>
            <div class="space-y-4 p-4 pt-2">
                <p class="break-words">情報源：{{ $view['source_title'] }}</p>
                <p>情報の種類：企業による申告情報</p>
                <p>公開確認・公開日時：{{ $view['published_at'] }}</p>
                <p class="text-sm leading-6">対象情報：このページの企業提供の仕事内容・応募条件。以下の根拠は公開時に記録された情報です。</p>
                @include('query.partials.provenance', ['source' => $view['source'], 'compactSource' => true])
            </div>
        </details>
        <details class="jobdd-details mt-4">
            <summary>項目別の根拠を見る（{{ count($view['evidence']) }}件）</summary>
            <div class="space-y-6 p-4 pt-2">
                @forelse ($view['evidence'] as $fact)
                    <section><h3 class="mb-2 break-words font-semibold">{{ $fact['label'] }}</h3><p class="mb-3 text-sm text-slate-600">{{ $fact['kind_label'] }}</p>
                        @include('query.partials.evidence-block', ['evidence' => $fact['evidence'], 'source' => $fact['source'], 'evidenceLabel' => $fact['label'], 'evidenceTextLabel' => $fact['text_label']])
                    </section>
                @empty<p>{{ \App\Support\PublishedJobDecisionPresenter::MISSING }}</p>@endforelse
            </div>
        </details>
        @break
    @default
        <x-decision-fields :fields="$section['fields']" />
@endswitch
