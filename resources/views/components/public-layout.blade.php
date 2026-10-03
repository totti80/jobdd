@props(['title', 'description' => null, 'index' => false])
<!DOCTYPE html>
<html lang="ja">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    @unless ($index)<meta name="robots" content="noindex, nofollow">@endunless
    <title>{{ $title }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="jobdd flex min-h-screen flex-col" data-jobdd-root>
    <x-site-header />
    <main class="flex-1">{{ $slot }}</main>
    <x-site-footer />
</body>
</html>
