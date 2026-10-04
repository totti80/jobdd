<x-public-layout title="JobDD｜仕事の中身まで比べて、選ぶ" description="製造・機電系技術職の仕事内容・担当工程・CAD / Toolを根拠とともに理解し、比較して自分で選ぶためのサービス。" :index="true">
<div class="homepage">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="site-container home-hero-grid">
            <div class="md:py-6">
                <p class="site-eyebrow home-badge">近畿6府県 × 機械設計・電気設計 専門</p>
                <h1 id="home-title"><span><span>求人を探すだけでは、</span><span>わからない。</span></span><span><span>仕事の中身まで</span><span>比べて、選ぶ。</span></span></h1>
                <p class="home-intro">JobDDは、求人情報を<br>「確認できたこと・条件と異なること・未確認」に整理し、<br>根拠を見ながら比較できるDecision Supportサービスです。</p>
                <div class="site-actions">
                    <a href="{{ route('jobs.start') }}" class="jobdd-button">希望条件を入力する</a>
                    <a href="{{ route('public.company') }}" class="home-secondary-link">企業の方はこちら</a>
                </div>
            </div>
            <div class="home-visual">
                <div class="home-illustration"><img src="{{ asset('images/jobdd/jobdd-hero-kinki.png') }}" width="1672" height="941" alt="近畿の街並みと、機械・電気の仕事について考える技術者" fetchpriority="high"></div>
            </div>
        </div>
    </section>
    <section class="home-features home-section" aria-labelledby="features-title">
        <div class="site-container">
            <p class="site-eyebrow">職種名の、その先へ</p>
            <h2 id="features-title" class="site-section-title">JobDDで分かること</h2>
            <p class="home-supporting">職種名だけでは見えにくい、毎日の仕事の中身を整理します。</p>
            <div class="home-feature-grid">
                @foreach ([['設計対象', '何を設計する？', 'tools'], ['担当工程', 'どこまで担当する？', 'difference'], ['CAD / Tool', '何を使う？', 'tools'], ['関係者', '誰と、どう進める？', 'agent'], ['Typical Day', 'どんな1日？', 'day'], ['Evidence', 'その情報の根拠は？', 'evidence']] as [$title, $description, $icon])
                    <x-pictogram-feature :title="$title" :description="$description" :icon="$icon" large />
                @endforeach
            </div>
        </div>
    </section>
    <section class="site-container home-section" aria-labelledby="views-title">
        <h2 id="views-title" class="site-section-title">選ぶための、3つの見方</h2>
        <div class="home-views-grid">
            <x-pictogram-feature title="根拠を確認" description="求人票や公式情報など、出典とEvidenceを確認できます。" icon="evidence" large />
            <x-pictogram-feature title="同じ軸で比較" description="2〜3求人を、勤務地・年収・ツールなど同じ項目で比較できます。" icon="compare" large />
            <x-pictogram-feature title="地図で見る" description="近畿の求人を代表地点で確認できます。実際の勤務地を示すものではありません。" icon="map-pin" large />
        </div>
    </section>
    <section class="site-container home-section" aria-labelledby="preview-title">
        <p class="site-eyebrow">違いが見えると、考えやすい</p>
        <h2 id="preview-title" class="site-section-title"><span class="inline-block">同じ「機械設計」でも、</span><span class="inline-block">仕事の中身は違う。</span></h2>
        <p class="home-supporting">求人票に書かれた仕事内容を、JobDDでは比較しやすい項目へ整理します。</p>
        <div class="home-preview">
            <div class="home-source-example"><x-jobdd-icon name="evidence" /><h3>求人票の記載例</h3><p class="home-source-title">機械設計業務</p><p>仕事内容を、同じ項目で整理すると。</p><span aria-hidden="true">↓</span></div>
            <div class="home-comparison">
                <h3>JobDDで整理した表示例</h3>
                <dl class="home-normalized-facts">
                    @foreach ([['設計対象', '生産設備'], ['担当工程', '詳細設計〜評価'], ['CAD / Tool', 'SolidWorks'], ['顧客との関わり', '月に数回'], ['製造との関わり', '週に数回'], ['Typical Day', '確認あり'], ['Evidence', '企業提供情報']] as [$axis, $value])
                        <div><dt>{{ $axis }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </div>
        <div class="home-search">
            @include('query.partials.entry-form', ['compact' => true])
        </div>
    </section>
    <section id="new-jobs" class="home-new-jobs home-section scroll-mt-6" aria-labelledby="new-jobs-title" data-jobs-carousel>
        <div class="site-container home-rail-container">
        <div class="home-section-heading">
            <div><p class="site-eyebrow">仕事との新しい接点</p><h2 id="new-jobs-title" class="site-section-title">新着求人</h2></div>
            @if ($jobs->isNotEmpty())
                <div class="home-rail-controls" data-rail-controls hidden>
                    <button type="button" data-rail-toggle aria-pressed="false" aria-controls="new-jobs-rail">自動送りを停止</button>
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
    <section class="site-container home-section" aria-labelledby="resources-title">
        <p class="site-eyebrow">仕事選びのヒント</p>
        <h2 id="resources-title" class="site-section-title">お役立ち情報</h2>
        <div class="home-resource-grid">
            @include('public.helpful.article-cards')
        </div>
    </section>
    <section class="home-company home-section" aria-labelledby="company-cta-title">
        <div class="site-container home-company-inner">
            <div><p class="home-company-label">企業の皆さまへ</p><h2 id="company-cta-title" class="site-section-title">仕事の中身を、求職者へ正しく伝える。</h2><p class="mt-5">求人票だけでは伝わりにくい仕事の実態を、構造化して登録できます。</p></div>
            <a href="{{ route('public.company') }}" class="jobdd-button shrink-0">企業向けJobDDを見る</a>
        </div>
    </section>
</div>
</x-public-layout>
