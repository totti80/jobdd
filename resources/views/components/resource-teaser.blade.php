@props(['title', 'description', 'icon' => 'evidence'])
{{-- Presentation-only teaser. Future articles can supply an image, category, title, excerpt and publication date; no invented article metadata here. --}}
<article class="home-resource-teaser">
    <div class="home-resource-visual"><span class="home-resource-icon"><x-jobdd-icon :name="$icon" /></span><span class="home-resource-category">{{ $title }}</span></div>
    <div class="home-resource-copy"><p class="home-coming-label">準備中</p><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
</article>
