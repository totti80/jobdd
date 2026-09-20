<section id="jobdd-map-view" data-map-view hidden aria-labelledby="map-title" class="min-w-0 space-y-4">
    <div class="jobdd-card space-y-3">
        <h2 id="map-title" class="text-xl font-bold text-blue-950">近畿6府県の簡易位置図</h2>
        <p id="map-accuracy" class="font-semibold leading-7">都道府県の代表点です。実際の勤務地を示すものではありません。</p>
        <p class="text-sm leading-6 text-slate-600">府県庁の代表点の相対位置を示す図です。境界・距離・通勤時間は表していません。求人詳細の勤務地表示をご確認ください。</p>
        <p>地図表示対象 {{ $map['mapped_count'] }}求人 / 位置表示未設定 {{ $map['missing_count'] }}求人<span class="text-sm text-slate-600">（このページ内）</span></p>
    </div>
    <div data-map-error hidden class="jobdd-card" role="alert"><p>地図を表示できませんでした</p><button type="button" data-map-return class="jobdd-button mt-3">リストに戻る</button></div>
    @if (count($map['jobs']) === 0)
        <p class="jobdd-card">このページに表示できる求人候補はありません。</p>
    @else
        @if ($map['mapped_count'] === 0)<p class="jobdd-card">このページの求人の地図上の位置を表示できていません</p>@endif
        <div data-map-content class="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-3">
                <div class="jobdd-map-plot" aria-label="府県庁代表点の簡易位置図" aria-describedby="map-accuracy">
                    <span class="absolute right-3 top-2 text-sm text-slate-600" aria-hidden="true">北 ↑</span>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full" aria-hidden="true" focusable="false">
                        @foreach ($map['markers'] as $marker)
                            <line x1="{{ $marker['x'] }}" y1="{{ $marker['y'] }}" x2="{{ $marker['label_x'] }}" y2="{{ $marker['label_y'] }}" stroke="#64748b" stroke-width="1" vector-effect="non-scaling-stroke" />
                        @endforeach
                    </svg>
                    @foreach ($map['markers'] as $marker)
                        <span class="jobdd-map-point" style="left: {{ $marker['x'] }}%; top: {{ $marker['y'] }}%" aria-hidden="true"></span>
                        <button type="button" class="jobdd-map-marker" data-map-marker="{{ $marker['key'] }}" data-region="{{ $marker['region'] }}" data-count="{{ $marker['count'] }}" style="left: {{ $marker['label_x'] }}%; top: {{ $marker['label_y'] }}%" aria-pressed="false" aria-controls="map-results" aria-label="{{ $marker['region'] }}。{{ $marker['count'] }}件の求人候補">
                            <span>{{ $marker['region'] }}</span><span class="block text-sm">{{ $marker['count'] }}件</span>
                        </button>
                    @endforeach
                </div>
                <p class="text-sm leading-6 text-slate-600">点は代表座標、線は府県名への引出線です。件数はこのページ内の求人数です。</p>
                @if ($map['missing_count'] > 0)<button type="button" data-map-missing aria-pressed="false" aria-controls="map-results" class="jobdd-choice text-blue-800">位置表示未設定の{{ $map['missing_count'] }}求人を見る</button>@endif
            </div>
            <section id="map-results" class="min-w-0 space-y-4" aria-labelledby="map-results-title">
                <h3 id="map-results-title" class="text-lg font-bold text-blue-950" tabindex="-1" data-map-heading>府県を選ぶと求人候補を表示します</h3>
                <p data-map-announcement role="status" class="text-sm leading-6 text-slate-600">表示する府県を選んでください。比較の選択とは別の操作です。</p>
                @foreach ($map['jobs'] as $mapJob)
                    <div data-map-job="{{ $mapJob['job_id'] }}" data-point-key="{{ $mapJob['point_key'] ?? 'unknown' }}" hidden class="jobdd-card space-y-3">
                        <p class="text-sm font-semibold text-slate-600">{{ $mapJob['company_name'] }}</p>
                        <h4 class="text-lg font-bold leading-7 text-blue-950">{{ $mapJob['job_title'] }}</h4>
                        <p>保存勤務地：{{ $mapJob['region'] ?? '未確認' }}</p>
                        <p class="text-sm text-slate-600">{{ $mapJob['location_label'] }}。実勤務地の座標ではありません。</p>
                        <button type="button" data-map-select-job aria-pressed="false" class="jobdd-choice text-blue-800">この求人を位置図で選択<span class="sr-only">：{{ $mapJob['job_title'] }}</span></button>
                        <a href="{{ $mapJob['detail_url'] }}" class="jobdd-button">詳細を見る</a>
                    </div>
                @endforeach
                <button type="button" data-map-clear hidden class="jobdd-link inline-flex min-h-11 items-center">府県の選択を解除</button>
            </section>
        </div>
    @endif
    <div class="text-sm leading-6 text-slate-600">
        <p>出典：国土地理院の公開サンプルの府県庁座標を抽出し、JobDDが簡易位置図を作成（2020年保存データ、2026年9月20日確認）。</p>
        <a class="jobdd-link inline-flex min-h-11 items-center" href="{{ \App\Support\JobMapLocation::SOURCE['url'] }}" target="_blank" rel="noopener noreferrer">座標の出典（新しいタブ）</a>
        <a class="jobdd-link ml-3 inline-flex min-h-11 items-center" href="{{ \App\Support\JobMapLocation::SOURCE['terms_url'] }}" target="_blank" rel="noopener noreferrer">利用条件 PDL 1.0（新しいタブ）</a>
    </div>
</section>
