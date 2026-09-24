@props(['title', 'eyebrow' => null])
<section {{ $attributes->class(['site-page-hero']) }}>
    @if ($eyebrow)<p class="site-eyebrow">{{ $eyebrow }}</p>@endif
    <h1 class="jobdd-page-title">{{ $title }}</h1>
    <div class="mt-4 max-w-3xl leading-8 text-slate-600">{{ $slot }}</div>
</section>
