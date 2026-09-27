@props(['title', 'description', 'icon' => 'evidence'])
{{-- Non-interactive until articles are available. --}}
<article class="home-resource-teaser">
    <span class="jobdd-icon-tile"><x-jobdd-icon :name="$icon" /></span>
    <div><h3>{{ $title }}</h3><p class="home-coming-label">準備中</p><p>{{ $description }}</p></div>
</article>
