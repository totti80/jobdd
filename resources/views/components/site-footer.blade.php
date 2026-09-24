<footer class="site-footer">
    <div class="site-container">
        <div class="site-footer-top">
            <div><a href="{{ route('home') }}" class="text-xl font-bold text-blue-950">JobDD</a><p class="mt-2 text-sm leading-7 text-slate-600">仕事の中身を知って、比べて、自分で選ぶ。</p></div>
            <nav aria-label="フッターナビゲーション">
                @foreach (['home' => 'トップ', 'jobs.start' => 'かんたん入力', 'public.company' => '企業向け', 'public.resources' => 'お役立ち情報', 'public.contact' => 'お問い合わせ'] as $name => $label)
                    <a href="{{ route($name) }}" class="site-nav-link">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <p class="mt-6 border-t border-slate-200 pt-5 text-xs leading-6 text-slate-600">&copy; {{ date('Y') }} JobDD. 情報の整理と比較を支援します。最終判断は、あなた自身で。</p>
    </div>
</footer>
