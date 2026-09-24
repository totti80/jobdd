@props(['title', 'description'])
<x-public-layout :title="$title.' | JobDD'">
    <div class="site-container py-10 sm:py-16">
        <x-page-hero :title="$title" eyebrow="JobDD">{{ $description }}</x-page-hero>
        <section class="jobdd-card max-w-3xl">
            <h2 class="text-xl font-bold text-blue-950">仕事の中身を知って、比べて、自分で選ぶ。</h2>
            <p class="mt-4 leading-8 text-slate-600">JobDDではまず、求人の情報を整理し、自分で判断するための機能を優先して開発しています。</p>
            <div class="site-actions"><a href="{{ route('jobs.start') }}" class="jobdd-button">かんたん入力から始める</a><a href="{{ route('home') }}" class="site-button-secondary">トップへ戻る</a></div>
        </section>
    </div>
</x-public-layout>
