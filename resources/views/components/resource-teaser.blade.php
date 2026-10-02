@props(['title', 'description', 'image', 'alt', 'href'])
<article class="jobdd-card flex flex-col gap-4">
    <a href="{{ $href }}" aria-label="{{ $description }}の記事を読む（画像リンク）" class="group block w-full shrink-0 cursor-pointer overflow-hidden rounded-xl shadow-sm transition-shadow duration-200 motion-reduce:transition-none [@media(hover:hover)_and_(pointer:fine)]:hover:shadow-md">
        <img src="{{ asset('images/helpful/'.$image) }}" width="1200" height="675" alt="{{ $alt }}" loading="lazy" decoding="async" class="block aspect-video w-full rounded-xl object-cover transition-transform duration-200 motion-reduce:transition-none motion-reduce:transform-none [@media(hover:hover)_and_(pointer:fine)]:group-hover:scale-[1.03]">
    </a>
    <div class="flex flex-1 flex-col items-start gap-3">
        <p class="text-sm font-semibold text-blue-800">{{ $title }}</p>
        <h3 class="text-lg font-bold leading-8 text-blue-950">{{ $description }}</h3>
        <a href="{{ $href }}" class="jobdd-link mt-auto" aria-label="{{ $description }}の記事を読む">記事を読む <span aria-hidden="true">→</span></a>
    </div>
</article>
