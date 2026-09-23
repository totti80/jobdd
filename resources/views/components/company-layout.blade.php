@props(['title'])
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | JobDD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:block focus:p-4">本文へ移動</a>
    <header class="border-b border-slate-200 bg-white">
        <nav aria-label="企業メニュー" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <a href="{{ route('company.dashboard') }}" class="text-2xl font-bold text-blue-950">JobDD <span class="text-sm font-normal">企業向け</span></a>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('company.dashboard') }}" class="inline-flex min-h-11 items-center font-semibold text-blue-800 underline">企業マイページ</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 rounded-lg border border-slate-400 px-4" type="submit">ログアウト</button></form>
            </div>
        </nav>
    </header>
    <main id="main-content" class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
        @if(session('status'))<p role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</p>@endif
        {{ $slot }}
    </main>
    <footer class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-600 sm:px-6">JobDD — 仕事の中身を伝え、求職者の判断を支えます。</footer>
</body>
</html>
