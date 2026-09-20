<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
    <title>相談先候補 | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd min-h-screen">
@include('query.partials.selection-header', ['heading' => '人材紹介会社の相談先候補', 'containerClass' => 'max-w-6xl'])
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:space-y-8 lg:py-8">
    <div class="space-y-2 leading-7 text-slate-600">
        <p>公開求人だけでは分からない求人や、キャリア相談を確認したい場合の相談先候補です。相談するかどうかは、ご自身で判断してください。</p>
        <p>保存済み候補を登録ID順に表示しています。未確認は、条件と異なるという意味ではありません。</p>
        <p>専門領域は職種・地域の記載を確認しています。年収・経験・選択ツールを含むすべての希望条件への対応や、個別求人の紹介可否を示すものではありません。</p>
    </div>
    @forelse ($items as $agency)
        <article class="jobdd-card" data-agency-id="{{ $agency['id'] }}" aria-labelledby="agency-{{ $agency['id'] }}">
            <h2 id="agency-{{ $agency['id'] }}" class="break-words text-xl font-bold leading-8 text-blue-950">{{ $agency['name'] }}</h2>
            <p class="mt-3 leading-7">{{ $agency['summary'] }}</p>
            <section class="mt-5 rounded-xl bg-slate-50 p-4" aria-label="公開求人状況">
                <h3 class="font-semibold">公開求人</h3>
                <p class="mt-2">この条件では公開求人を確認できていません</p>
                @if ($agency['public_jobs']['count'] !== null)
                    <p class="mt-2">JobDDで確認できた公開求人：{{ $agency['public_jobs']['count'] }}件</p>
                    <p class="mt-2 text-sm text-slate-600">保存済みSourceの件数です。集計対象の条件は未確認であり、あなたの条件での件数や現在の募集件数を示すものではありません。</p>
                @endif
                <p class="mt-2 text-sm leading-6 text-slate-600">公開求人が確認できないことは、紹介可能な求人がないことを意味しません。</p>
                <details class="jobdd-details mt-3">
                    <summary>公開求人の根拠を見る</summary>
                    <div class="px-4 pb-4 text-sm leading-6">@include('query.partials.agency-evidence', ['facts' => $agency['public_jobs']['facts']])</div>
                </details>
            </section>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ($agency['layers'] as $layer)
                    <section class="min-w-0 rounded-xl border border-slate-200 p-4" data-layer="{{ $layer['key'] }}" aria-labelledby="agency-{{ $agency['id'] }}-{{ $layer['key'] }}">
                        <h3 id="agency-{{ $agency['id'] }}-{{ $layer['key'] }}" class="font-bold text-blue-950">{{ $layer['label'] }}</h3>
                        @if ($layer['key'] === 'outcome')
                            <p class="mt-2 text-sm leading-6 text-slate-600">実績の記載は、あなたと類似する求職者の成果や将来の成果を保証しません。</p>
                        @endif
                        @foreach ($layer['items'] as $item)
                            <div class="mt-4 border-t border-slate-200 pt-4" data-fact-key="{{ $item['key'] }}" data-status="{{ $item['status'] }}">
                                <h4 class="font-semibold">{{ $item['label'] }}{{ isset($item['target']) ? '：'.$item['target'] : '' }}</h4>
                                <div class="mt-2">@include('query.partials.status-badge', ['status' => $item['status']])</div>
                                <p class="mt-2 text-sm leading-6">{{ $item['reason'] }}</p>
                                <details class="jobdd-details mt-3">
                                    <summary>{{ $item['label'] }}の根拠を見る</summary>
                                    <div class="px-4 pb-4 text-sm leading-6">@include('query.partials.agency-evidence', ['facts' => $item['facts']])</div>
                                </details>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </div>
            @if ($agency['website_url'])
                <a href="{{ $agency['website_url'] }}" target="_blank" rel="noopener noreferrer" class="jobdd-button mt-5">公式サイトを見る（新しいタブ）<span class="sr-only">：{{ $agency['name'] }}</span></a>
            @else
                <p class="mt-5 text-sm text-slate-600">公式サイトのリンクは未確認です。</p>
            @endif
        </article>
    @empty
        <p class="jobdd-card">現在、確認できる相談先候補がありません。</p>
    @endforelse
</main>
</body>
</html>
