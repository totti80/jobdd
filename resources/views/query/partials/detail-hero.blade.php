@php
    $heroTitle = $decision_view['title'] ?? $job->title;
    $heroCompany = $decision_view['company'] ?? $items[0]['company_name'];
    // Self-service images must not read an editable JobPosting field outside the snapshot.
    $imageUrl = isset($decision_view) ? null : \App\Support\JobEyecatchResolver::importedUrl($job->provider_key ?? '', ['eyecatch_image_url' => $job->eyecatch_image_url]);
    $eyecatch = (new \App\Support\JobEyecatchResolver)->present($heroTitle, $job->occupation, $imageUrl);
    $heroBasic = $decision_view['basic'] ?? [
        '職種' => $job->occupation ?? '未確認', '勤務地' => $job->region ?? '未確認',
        '掲載年収' => ($job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認').' 〜 '.($job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認'),
        ...($job->employment_type ? ['雇用形態' => $job->employment_type] : []),
    ];
@endphp
<article data-job-id="{{ $job->id }}" class="jobdd-detail-hero">
    <div class="jobdd-detail-heading">
        <p class="font-semibold text-slate-600"><x-company-name :name="$heroCompany" /></p>
        <h1>{{ $heroTitle }}</h1>
        @if(isset($decision_view))<p class="mt-3 text-sm text-slate-600">企業提供情報 · JobDD公開確認済み</p>@endif
        <div class="mt-5"><x-decision-fields :fields="$heroBasic" /></div>
        <p class="mt-4 text-xs leading-6 text-slate-600">掲載年収・保存上の掲載状態は、提示年収や現在の募集を保証しません。</p>
    </div>
    <figure class="jobdd-eyecatch" data-eyecatch data-category="{{ $eyecatch['category'] }}">
        <div class="jobdd-eyecatch-frame">
            <div class="jobdd-eyecatch-fallback" data-eyecatch-fallback role="img" aria-label="{{ $eyecatch['label'] }}のJobDDイメージ画像">
                <img src="{{ asset($eyecatch['fallback_asset']) }}" alt="" width="1672" height="941">
                <span class="jobdd-eyecatch-label"><x-jobdd-icon :name="$eyecatch['icon']" />{{ $eyecatch['label'] }}</span>
            </div>
            @if($eyecatch['url'])
                <img data-eyecatch-external data-src="{{ $eyecatch['url'] }}" alt="掲載元求人ページの代表画像" width="800" height="600" referrerpolicy="no-referrer" hidden>
            @endif
        </div>
        <figcaption data-eyecatch-caption>JobDDイメージ画像</figcaption>
    </figure>
    <div class="jobdd-detail-actions">
        <a class="site-button-secondary" href="{{ route('query.jobs', ['userQuery' => $query['public_id'], 'page' => $page, 'tools' => $selected_tools, 'select_job' => $job->id]) }}#compare-selection" data-compare-add="{{ $job->id }}" data-query-id="{{ $query['public_id'] }}" data-job-label="{{ $heroCompany }} {{ $heroTitle }}">比較に追加</a>
        <a class="jobdd-link" href="#application-title">応募方法を見る</a>
        <a class="jobdd-link" href="{{ $backUrl }}">求人一覧へ戻る</a>
    </div>
</article>
<section aria-labelledby="detail-conditions" class="jobdd-results-conditions">
    <h2 id="detail-conditions" class="mb-2 font-bold text-blue-950">あなたの希望</h2>
    @include('query.partials.condition-summary', ['compact' => true])
    <div class="mt-2 flex flex-wrap items-center gap-x-6">
        <a href="{{ route('jobs.start') }}" class="jobdd-link inline-flex min-h-11 items-center">条件を変更</a>
        @include('query.partials.preference-link', ['preferencePage' => $page, 'returnJob' => $job->id, 'preferenceLinkLabel' => '詳細条件'])
    </div>
</section>
