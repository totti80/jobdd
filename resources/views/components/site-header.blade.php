@php
    $routeQuery = request()->route('userQuery');
    $queryId = $routeQuery instanceof \App\Models\UserQuery ? $routeQuery->public_id : null;
    $context = $queryId ? ['query' => $queryId, ...request()->only(['page', 'tools'])] : [];
    $links = [
        ['トップ', route('home'), request()->routeIs('home')],
        ['かんたん入力', route('jobs.start'), request()->routeIs('jobs.start')],
        ['詳細条件', route('public.preferences', $context), request()->routeIs('query.preferences.*')],
        ['お役立ち情報', route('public.resources'), request()->routeIs('public.resources')],
        ['企業向け', route('public.company'), request()->routeIs('public.company')],
        ['お問い合わせ', route('public.contact'), request()->routeIs('public.contact')],
    ];
@endphp
<header class="site-header">
    <div class="site-header-inner">
        <a href="{{ route('home') }}" class="site-brand" data-jobdd-brand aria-label="JobDD トップへ">
            <span class="jobdd-logo-frame"><img src="{{ asset('images/jobdd/jobdd-logo.png') }}" width="1448" height="1086" alt="JobDD" class="jobdd-logo-image" decoding="async"></span>
            <span class="site-tagline">根拠とともに、仕事を選ぶ。</span>
        </a>
        <details class="site-menu" data-site-menu open>
            <summary aria-label="ナビゲーションメニュー"><span>メニュー</span><span aria-hidden="true">☰</span></summary>
            <nav aria-label="メインナビゲーション">
                @foreach ($links as [$label, $href, $active])
                    <a href="{{ $href }}" class="site-nav-link" @if ($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </details>
    </div>
</header>
