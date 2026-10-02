@props(['category', 'title', 'image', 'alt', 'lead'])
<x-public-layout :title="$title.' | JobDD'" :description="$lead">
    <article class="site-container py-10 sm:py-16">
        <div class="max-w-3xl">
            <x-page-hero :title="$title" :eyebrow="$category" />
            <img src="{{ asset('images/helpful/'.$image) }}" width="1200" height="675" alt="{{ $alt }}" class="aspect-video w-full rounded-2xl object-cover" decoding="async" fetchpriority="high">
            <div class="jobdd-card mt-6 space-y-8 leading-8" data-helpful-body>
                <p>{{ $lead }}</p>
                {{ $slot }}
            </div>
            <div class="site-actions">
                <a href="{{ route('public.resources') }}" class="site-button-secondary">お役立ち情報へ戻る</a>
                <a href="{{ route('home') }}" class="site-button-secondary">トップへ戻る</a>
            </div>
        </div>
    </article>
</x-public-layout>
