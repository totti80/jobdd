@props(['title', 'description', 'icon' => 'evidence'])
{{-- Presentation-only teaser. Future published articles can replace this without a CMS dependency. --}}
<article class="home-resource-teaser">
    <span class="home-resource-icon"><x-jobdd-icon :name="$icon" /></span>
    <div><p class="home-coming-label">準備中</p><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
</article>
