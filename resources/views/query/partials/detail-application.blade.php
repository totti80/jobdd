@php
    $routeLabels = ['direct' => 'Direct（企業への直接応募）', 'agent' => 'Agent（人材紹介会社経由）', 'platform' => 'Platform（求人媒体経由）'];
    $availableRoutes = $application_routes->filter(fn ($route) => $route->availability_status === 'available' && $route->unavailable_at === null);
    $otherRoutes = $application_routes->diff($availableRoutes);
@endphp
<section aria-labelledby="application-title" class="jobdd-card jobdd-detail-application">
    <h2 id="application-title" class="text-xl font-bold text-blue-950">この求人への応募方法</h2>
    <p class="mt-3 leading-7 text-slate-600">保存済みの応募経路を種類別に表示しています。利用条件と現在の募集状況はリンク先で確認してください。</p>
    @forelse ($availableRoutes->groupBy('route_type') as $type => $routes)
        <section class="mt-6" aria-labelledby="route-type-{{ $type }}">
            <h3 id="route-type-{{ $type }}" class="font-bold text-blue-950">{{ $routeLabels[$type] }}</h3>
            @foreach ($routes as $route)
                @php
                    $link = \App\Support\JobDecisionPresenter::safeUrl($route->application_url);
                    $available = $route->availability_status === 'available' && $route->unavailable_at === null;
                @endphp
                <section data-application-route="{{ $route->id }}" class="mt-3 min-w-0 rounded-xl border border-blue-100 bg-white p-4 sm:p-6">
                    <h4 class="font-semibold">提供元：{{ $route->provider_key ?? '未確認' }}</h4>
                    <p class="mt-2 text-sm text-slate-600">保存上の状態：{{ $available ? '利用可能として記録' : (($route->unavailable_at !== null || $route->availability_status === 'unavailable') ? '利用不可として記録' : '利用状況未確認') }}</p>
                    <p class="mt-2 text-sm text-slate-600">最終取得日時：{{ $route->getRawOriginal('last_seen_at') ?? '未確認' }}</p>
                    <details class="jobdd-details mt-3"><summary>根拠・補足を見る</summary><p class="whitespace-pre-wrap break-words p-4 leading-7">{{ $route->notes ?: '記載は未確認です。' }}</p></details>
                    @if ($available && $link)
                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="jobdd-button jobdd-primary-application" data-detail-application="{{ $route->id }}" data-selected-url="{{ route('interaction.route-selected') }}" data-contact-url="{{ route('interaction.contact-clicked') }}">提供元の応募情報を見る <span aria-hidden="true">↗</span><span class="sr-only">：{{ $route->provider_key ?? '提供元未確認' }}</span></a>
                        <p class="mt-3 text-sm leading-6 text-slate-600">外部サイトを新しいタブで開きます<br>応募前に最新の掲載内容をご確認ください</p>
                    @else
                        <p class="mt-4 text-sm leading-6 text-slate-600">現在利用できる応募先リンクを確認できていません。</p>
                    @endif
                </section>
            @endforeach
        </section>
    @empty
        <p class="mt-4">保存済み情報では応募方法を確認できていません</p>
        @if ($otherRoutes->isNotEmpty())<p class="mt-2 text-sm text-slate-600">現在利用できる応募先リンクを確認できていません。</p>@endif
    @endforelse
    @if ($otherRoutes->isNotEmpty())
        <details class="jobdd-details mt-4">
            <summary>利用可能と確認できていない経路の記録</summary>
            <div class="space-y-3 p-4 text-sm leading-6">
                @foreach ($otherRoutes as $route)
                    <section data-application-route="{{ $route->id }}">
                        <p>提供元：{{ $route->provider_key ?? '未確認' }} · {{ $route->unavailable_at !== null || $route->availability_status === 'unavailable' ? '利用不可として記録' : '利用状況未確認' }}</p>
                        <p>最終取得日時：{{ $route->getRawOriginal('last_seen_at') ?? '未確認' }}</p>
                        <details class="jobdd-details mt-3"><summary>根拠・補足を見る</summary><p class="whitespace-pre-wrap break-words p-4">{{ $route->notes ?: '記載は未確認です。' }}</p></details>
                    </section>
                @endforeach
            </div>
        </details>
    @endif
    <a class="jobdd-link mt-4 inline-flex min-h-11 items-center" href="{{ route('routes.show', ['jobPosting' => $job->id]) }}">応募方法の詳細を見る</a>
</section>
<section aria-labelledby="agency-option-title" class="jobdd-card">
    <h2 id="agency-option-title" class="flex items-center gap-3 text-xl font-bold text-blue-950"><x-jobdd-icon name="agent" />人材紹介会社へ相談する選択肢</h2>
    <p class="mt-3 leading-7 text-slate-600">公開求人だけでは分からない求人や、キャリア相談を確認したい場合の相談先候補です。この求人の紹介可否を示すものではありません。</p>
    <a href="{{ route('query.agencies', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools]) }}" class="jobdd-link mt-3 inline-flex min-h-12 items-center">相談先候補を見る</a>
</section>
