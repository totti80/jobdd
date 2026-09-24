<x-public-layout title="JobDD｜仕事の中身を知って、比べて、自分で選ぶ" description="製造・機電系技術職の仕事内容・担当工程・CAD / Toolを根拠とともに理解し、比較して自分で選ぶためのサービス。" :index="true">
<div class="homepage">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="site-container home-hero-grid">
            <div>
                <p class="site-eyebrow">JobDD · 仕事選びのための判断材料</p>
                <h1 id="home-title">仕事の中身を知って、<br>比べて、自分で選ぶ。</h1>
                <p class="home-intro">設計対象、担当工程、使うツール。<br>仕事の違いと情報の根拠を整理して、あなたの判断を支えます。</p>
                <div class="site-actions">
                    <a href="{{ route('jobs.start') }}" class="jobdd-button">かんたん入力から始める <span aria-hidden="true">→</span></a>
                    <a href="{{ route('public.company') }}" class="home-secondary-link">企業の方はこちら <span aria-hidden="true">→</span></a>
                </div>
                <p class="home-scope">現在の対象：近畿6府県・機械設計／電気設計</p>
            </div>
            <div class="home-visual">
                <div class="home-illustration"><img src="{{ asset('images/jobdd/jobdd-hero-kinki.png') }}" width="1672" height="941" alt="近畿の街並みと、機械・電気の仕事について考える技術者" fetchpriority="high"></div>
                <div class="home-sample">
                    <p class="home-sample-label">説明用サンプル</p>
                    <p class="home-sample-title">仕事の中身を、見える形に。</p>
                    <dl>
                        <div><dt>担当工程</dt><dd>詳細設計〜評価</dd></div>
                        <div><dt>CAD / Tool</dt><dd>SolidWorks</dd></div>
                        <div><dt>情報源</dt><dd>企業提供情報〈例〉</dd></div>
                    </dl>
                    <p class="home-sample-note">未確認の項目は未確認として表示します。</p>
                </div>
            </div>
        </div>
    </section>
    <section class="site-container home-section" aria-labelledby="search-entry-title">
        <p class="site-eyebrow">まずは、気になる条件から</p>
        <h2 id="search-entry-title" class="site-section-title">仕事を探す</h2>
        <div class="home-entries">
            @foreach ([['職種', 'compare'], ['勤務地', 'map-pin'], ['CAD / Tool', 'tools'], ['かんたん入力', 'arrow']] as [$label, $icon])
                <a href="{{ route('jobs.start') }}" class="home-entry"><x-jobdd-icon :name="$icon" /><span><strong>{{ $label }}</strong><small>入力画面で選べます</small></span><span aria-hidden="true">→</span></a>
            @endforeach
        </div>
    </section>
    <section class="home-features home-section" aria-labelledby="features-title">
        <div class="site-container">
            <p class="site-eyebrow">職種名の、その先へ</p>
            <h2 id="features-title" class="site-section-title">JobDDならここまで分かる</h2>
            <div class="home-feature-grid">
                @foreach ([['何を設計する？', '製品・設備と、設計する対象を確認。', 'tools'], ['どの工程を担当？', '構想から評価まで、担当範囲を確認。', 'difference'], ['CAD / Toolは？', '使用場面と応募時の経験要件を分けて確認。', 'tools'], ['誰と、どう進める？', '顧客・製造・現場との関わりを確認。', 'agent'], ['どんな1日？', '代表的な仕事の流れを確認。', 'day'], ['根拠は？', '情報源と、まだ確認できない点を確認。', 'evidence']] as [$title, $description, $icon])
                    <article class="home-feature"><span class="home-feature-icon"><x-jobdd-icon :name="$icon" /></span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
                @endforeach
            </div>
        </div>
    </section>
    <section class="site-container home-section" aria-labelledby="preview-title">
        <p class="site-eyebrow">違いが見えると、考えやすい</p>
        <h2 id="preview-title" class="site-section-title"><span class="inline-block">同じ職種でも、</span><span class="inline-block">仕事の中身は違う。</span></h2>
        <div class="home-preview">
            <div class="home-source-example"><x-jobdd-icon name="evidence" /><p class="home-sample-label">説明用サンプル</p><h3>求人票の記載例</h3><p class="home-source-title">機械設計業務</p><p>仕事内容を、同じ項目で整理すると。</p><span aria-hidden="true">↓</span></div>
            <div class="home-comparison">
                <h3>JobDDで整理した表示例</h3>
                <table><caption class="sr-only">説明用サンプル：求人Aと求人Bの仕事の違い</caption>
                    <thead><tr><th scope="col">比較項目</th><th scope="col">求人A</th><th scope="col">求人B</th></tr></thead>
                    <tbody>
                        @foreach ([['設計対象', '生産設備', '産業用機械'], ['担当工程', '詳細設計〜評価', '構想〜基本設計'], ['CAD / Tool', 'SolidWorks', 'AutoCAD'], ['情報源', '企業提供情報〈例〉', '企業提供情報〈例〉'], ['未確認', '現場対応の頻度', '製造部門との関わり']] as [$axis, $a, $b])
                            <tr><th scope="row">{{ $axis }}</th><td>{{ $a }}</td><td>{{ $b }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="home-supporting">仕事内容を理解したら、利用できる応募方法も確認。求人ごとに利用できる経路を確かめられます。</p>
        <a href="{{ route('jobs.start') }}" class="home-secondary-link">条件を入力して、仕事の中身を見る <span aria-hidden="true">→</span></a>
    </section>
    <section id="new-jobs" class="home-new-jobs home-section scroll-mt-6" aria-labelledby="new-jobs-title" data-jobs-carousel>
        <div class="site-container home-rail-container">
        <div class="home-section-heading">
            <div><p class="site-eyebrow">仕事との新しい接点</p><h2 id="new-jobs-title" class="site-section-title">新着求人</h2></div>
            @if ($jobs->isNotEmpty())
                <div class="home-rail-controls" data-rail-controls hidden>
                    <button type="button" data-rail-prev aria-controls="new-jobs-rail" aria-label="新着求人を前へ">← <span>前へ</span></button>
                    <button type="button" data-rail-next aria-controls="new-jobs-rail" aria-label="新着求人を次へ"><span>次へ</span> →</button>
                </div>
            @endif
        </div>
        <p class="home-supporting">公開済みの求人を新しい順に表示しています。掲載情報と現在の募集状況は、応募前にご確認ください。</p>
        @if ($jobs->isNotEmpty())
            <p id="new-jobs-help" class="mt-3 text-sm text-slate-600">横にスクロールして確認できます。初めての方は「詳細を見る」から条件入力へ進みます。</p>
            <div id="new-jobs-rail" class="home-jobs-rail" tabindex="0" role="region" aria-labelledby="new-jobs-title" aria-describedby="new-jobs-help" data-jobs-rail>
                @foreach ($jobs as $job)<x-new-job-card :job="$job" />@endforeach
            </div>
        @else
            <div class="home-empty"><p>公開中の求人はまだありません。掲載の準備が整い次第、こちらに表示します。</p></div>
        @endif
        </div>
    </section>
    <x-pickup-preview />
    <section class="home-section home-decision" aria-labelledby="decision-title">
        <div class="site-container">
            <p class="site-eyebrow">Decision Support</p>
            <h2 id="decision-title" class="home-decision-title">選ぶのは、あなた。</h2>
            <ol class="home-decision-steps">
                @foreach (['知る', '比べる', '根拠を確かめる', '自分で選ぶ'] as $step)
                    <li><span class="home-step-number">0{{ $loop->iteration }}</span><strong>{{ $step }}</strong>@unless ($loop->last)<span class="home-step-arrow" aria-hidden="true">→</span>@endunless</li>
                @endforeach
            </ol>
            <p>JobDDは転職先を決めません。情報を整理し、あなた自身の判断を支援します。</p>
            <p>「未確認」は不適合ではありません。気になる点を確かめるための手がかりです。</p>
            <a href="{{ route('jobs.start') }}" class="jobdd-button mt-7">かんたん入力から始める <span aria-hidden="true">→</span></a>
        </div>
    </section>
    <section class="site-container home-section" aria-labelledby="resources-title">
        <p class="site-eyebrow">仕事選びのヒント</p>
        <h2 id="resources-title" class="site-section-title">お役立ち情報</h2>
        <div class="home-resource-grid">
            <x-resource-teaser title="CAD・設計職" description="経験やツールの使い方を、仕事選びにつなげる。" icon="tools" />
            <x-resource-teaser title="転職ノウハウ" description="希望を整理し、自分のペースで次の一歩へ。" icon="agent" />
            <x-resource-teaser title="求人の読み方" description="仕事内容の違いと、確認したい点を見つける。" icon="evidence" />
        </div>
    </section>
    <section class="home-company home-section" aria-labelledby="company-cta-title">
        <div class="site-container home-company-inner">
            <div><p class="home-company-label">企業の皆さまへ</p><h2 id="company-cta-title" class="site-section-title">仕事の中身を、求職者へ正しく伝える。</h2><p class="mt-5">求人票だけでは伝わりにくい仕事の実態を、構造化して登録できます。</p></div>
            <a href="{{ route('public.company') }}" class="site-button-secondary shrink-0">企業向けJobDDを見る <span aria-hidden="true">→</span></a>
        </div>
    </section>
</div>
</x-public-layout>
