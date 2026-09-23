@props(['id', 'title'])
<section aria-labelledby="{{ $id }}-title" class="jobdd-card min-w-0">
    <h2 id="{{ $id }}-title" class="text-xl font-bold leading-8 text-blue-950">{{ $title }}</h2>
    <div class="mt-4">{{ $slot }}</div>
</section>
