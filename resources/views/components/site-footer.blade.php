<footer class="site-footer">
    <div class="site-container">
        <div class="site-footer-top">
            <div><a href="{{ route('home') }}" class="site-brand" aria-label="JobDD トップへ"><span class="jobdd-logo-frame"><img src="{{ asset('images/jobdd/jobdd-logo.png') }}" width="1448" height="1086" alt="JobDD" class="jobdd-logo-image" loading="lazy" decoding="async"></span></a><p class="mt-2 text-sm leading-7 text-slate-600">根拠とともに、仕事を選ぶ。</p></div>
            <nav aria-label="フッターナビゲーション">
                @foreach (['home' => 'トップ', 'jobs.start' => 'かんたん入力', 'public.preferences' => '詳細条件', 'public.resources' => 'お役立ち情報', 'public.company' => '企業向け', 'public.contact' => 'お問い合わせ'] as $name => $label)
                    <a href="{{ $name === 'public.preferences' ? $preferencesUrl : route($name) }}" class="site-nav-link">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <p class="mt-6 border-t border-slate-200 pt-5 text-xs leading-6 text-slate-600">&copy; {{ date('Y') }} JobDD. 情報の整理と比較を支援します。最終判断は、あなた自身で。</p>
    </div>
</footer>
