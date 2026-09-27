@props(['item', 'query', 'page', 'selectedTools'])
@php($job = $item['job'])
<article data-job-id="{{ $job->id }}" class="jobdd-card jobdd-result-card" aria-labelledby="result-job-{{ $job->id }}">
    <p class="jobdd-result-company"><x-company-name :name="$item['company_name']" /></p>
    <h2 id="result-job-{{ $job->id }}" class="jobdd-result-title">{{ $job->title }}</h2>
    <dl class="jobdd-result-basics">
        <div><dt>勤務地</dt><dd>{{ $job->region ?? '未確認' }}</dd></div>
        <div><dt>掲載年収</dt><dd>{{ $job->salary_min !== null ? $job->salary_min.'万円' : '下限未確認' }} 〜 {{ $job->salary_max !== null ? $job->salary_max.'万円' : '上限未確認' }}</dd></div>
    </dl>
    @include('query.partials.fit', ['fit' => $item['fit'], 'compact' => true])
    <div class="jobdd-result-actions">
        <a href="{{ route('query.jobs.show', ['userQuery' => $query['public_id'], 'job' => $job->id, 'page' => $page, 'tools' => $selectedTools]) }}" class="jobdd-button">詳細を見る<span class="sr-only">：{{ $job->title }}</span></a>
        <label class="jobdd-choice" for="compare-job-{{ $job->id }}">
            <input id="compare-job-{{ $job->id }}" form="compare-selection" aria-describedby="compare-help" type="checkbox" name="jobs[]" value="{{ $job->id }}" data-job-label="{{ \App\Support\AnonymousCompany::display($item['company_name']).'：'.$job->title }}">
            <span>比較に追加<span class="sr-only">：{{ \App\Support\AnonymousCompany::display($item['company_name']) }} {{ $job->title }}</span></span>
        </label>
    </div>
</article>
