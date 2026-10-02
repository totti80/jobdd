<x-public-layout title="お役立ち情報 | JobDD">
    <div class="site-container py-10 sm:py-16">
        <x-page-hero title="お役立ち情報" eyebrow="仕事選びのヒント">経験や希望条件、求人に書かれた情報を整理するための読みものです。自分の場合を考えながら、仕事を理解・比較する材料にしてください。</x-page-hero>
        <div class="home-resource-grid">
            @include('public.helpful.article-cards')
        </div>
        <div class="site-actions"><a href="{{ route('home') }}" class="site-button-secondary">トップへ戻る</a></div>
    </div>
</x-public-layout>
