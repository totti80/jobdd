@props(['title', 'eyebrow' => null])
<section class="relative overflow-hidden rounded-2xl bg-blue-50 p-6 sm:p-8">
    <img src="{{ asset('images/jobdd/jobdd-hero-kinki.png') }}" alt="" class="pointer-events-none absolute right-0 top-0 h-full w-1/3 object-contain opacity-20" aria-hidden="true">
    <div class="relative">
        @if($eyebrow)<p class="text-sm font-bold text-blue-700">{{ $eyebrow }}</p>@endif
        <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">{{ $title }}</h1>
        <div class="mt-3 max-w-2xl leading-7 text-slate-600">{{ $slot }}</div>
    </div>
</section>
