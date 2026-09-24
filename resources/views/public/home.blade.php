<x-public-layout title="JobDD｜仕事の中身を知って、比べて、自分で選ぶ" description="製造・機電系技術職の求人について、仕事内容・担当工程・CAD / Tool・働き方・応募方法などを整理し、比較・判断を支援するサービス。" :index="true">
    <section class="site-landing-hero">
        <div class="site-container site-hero-grid">
            <div>
                <p class="site-eyebrow">JobDD · 仕事選びのための判断材料</p>
                <h1>仕事の中身を知って、<br>比べて、自分で選ぶ。</h1>
                <p class="mt-6 max-w-2xl leading-8 text-slate-600">求人票だけでは分かりにくい、設計対象・担当工程・CAD / Tool・関係者・仕事の進め方。JobDDは情報を整理し、転職先を自分で判断するための材料を提供します。</p>
                <div class="site-actions"><a href="{{ route('jobs.start') }}" class="jobdd-button">かんたん入力から始める <span aria-hidden="true">→</span></a><a href="{{ route('public.company') }}" class="site-button-secondary">企業の方はこちら</a></div>
                <p class="mt-5 text-sm leading-6 text-slate-600">現在の対象：近畿6府県の機械設計・電気設計</p>
            </div>
            <div class="site-hero-panel" aria-label="仕事を理解するための比較項目">
                <p class="site-eyebrow">仕事を、もう一段深く。</p>
                <h2 class="text-2xl font-bold leading-9 text-blue-950">求人票の先にある、<br>毎日の仕事を知る。</h2>
                <div class="site-hero-topics">
                    @foreach (['何を設計する？' => '設計対象・製品', 'どこまで担当する？' => '担当工程・入社直後', '何を使う？' => 'CAD / Tool・使う場面', '誰と進める？' => 'チーム・顧客・製造'] as $question => $detail)
                        <div><span class="site-topic-dot" aria-hidden="true"></span><div><h3 class="font-bold text-blue-950">{{ $question }}</h3><p class="mt-1 text-sm text-slate-600">{{ $detail }}</p></div></div>
                    @endforeach
                </div>
                <p class="mt-5 border-t border-blue-100 pt-4 text-sm leading-6 text-slate-600">確認できた情報と、まだ確認できない情報を分けて表示します。</p>
            </div>
        </div>
    </section>
    <section class="site-container site-section" aria-labelledby="capabilities-title">
        <p class="site-eyebrow">JobDDでできること</p><h2 id="capabilities-title" class="site-section-title">知ることから、選ぶことへ。</h2>
        <div class="site-capabilities">
            @foreach ([['希望条件を整理する', 'まずは4つの条件から。求人を見たあとで、詳しい希望を追加できます。', 'compare'], ['仕事の中身を知る', '設計するもの、使うツール、関わる人。日々の仕事を具体的に確認します。', 'evidence'], ['求人を並べて比較する', '2〜3件を同じ項目で比較。合う点、異なる点、未確認の点を見渡せます。', 'compare'], ['根拠と応募方法を確認する', '情報源を確認し、利用できる応募方法の違いを理解して選べます。', 'arrow']] as $step)
                <article class="jobdd-card"><div class="flex items-center justify-between"><span class="jobdd-icon-tile"><x-jobdd-icon :name="$step[2]" /></span><span class="text-sm font-bold text-blue-700">0{{ $loop->iteration }}</span></div><h3 class="mt-5 text-lg font-bold text-blue-950">{{ $step[0] }}</h3><p class="mt-3 text-sm leading-7 text-slate-600">{{ $step[1] }}</p></article>
            @endforeach
        </div>
    </section>
    <section class="site-container" aria-labelledby="decision-title"><div class="site-decision"><div><p class="site-eyebrow">Decision Support</p><h2 id="decision-title" class="site-section-title">JobDDは転職先を決めません。</h2></div><div class="max-w-xl leading-8 text-slate-600"><p>情報を整理し、違いを見えるようにするサービスです。応募する会社も、応募方法も、選ぶのはあなた自身です。</p><p class="mt-3">「未確認」は、合わないという意味ではありません。気になる点を確かめるための手がかりとして使ってください。</p></div></div></section>
    <section id="new-jobs" class="site-container site-section scroll-mt-6" aria-labelledby="new-jobs-title">
        <p class="site-eyebrow">仕事との新しい接点</p><h2 id="new-jobs-title" class="site-section-title">新着求人</h2><p class="mt-3 leading-7 text-slate-600">公開済みの求人を新しい順に表示しています。掲載情報と現在の募集状況は、応募前にご確認ください。</p>
        <div class="site-jobs-grid">@forelse ($jobs as $job)<x-new-job-card :job="$job" />@empty<div class="jobdd-card sm:col-span-2 lg:col-span-3"><p class="leading-7">公開中の求人はまだありません。掲載の準備が整い次第、こちらに表示します。</p></div>@endforelse</div>
        <p class="mt-5 text-sm leading-7 text-slate-600">初めての方は「詳細を見る」から条件入力へ進みます。希望条件を整理して、求人の内容を確認できます。</p>
    </section>
    <section class="site-container pb-16" aria-labelledby="company-cta-title"><div class="site-company-cta"><div><p class="site-eyebrow">企業の皆さまへ</p><h2 id="company-cta-title" class="site-section-title">仕事の中身を、求職者へ正しく伝える。</h2><p class="mt-4 leading-8 text-slate-600">求人票だけでは伝わりにくい仕事の実態を構造化して登録できます。</p></div><a href="{{ route('public.company') }}" class="site-button-secondary shrink-0">企業向けJobDDを見る <span aria-hidden="true">→</span></a></div></section>
</x-public-layout>
