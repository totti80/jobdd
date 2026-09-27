@props(['title', 'description', 'icon', 'large' => false])
<article class="home-feature{{ $large ? ' home-feature-large' : '' }}">
    <span class="jobdd-icon-tile"><x-jobdd-icon :name="$icon" /></span>
    <div><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
</article>
