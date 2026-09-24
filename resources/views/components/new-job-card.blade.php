@props(['job'])
<article class="jobdd-card site-job-card" data-new-job="{{ $job->id }}">
    <p class="text-sm font-semibold text-slate-600"><x-company-name :name="$job->published_company_name ?? $job->company?->name ?? '会社名未確認'" /></p>
    <h3 class="mt-3 text-lg font-bold leading-8 text-blue-950">{{ $job->title }}</h3>
    <dl class="site-job-facts">
        <div><dt>勤務地</dt><dd>{{ $job->region ?: '未確認' }}</dd></div>
        <div><dt>年収</dt><dd>@if ($job->salary_min !== null || $job->salary_max !== null){{ $job->salary_min !== null ? number_format($job->salary_min) : '下限未確認' }}〜{{ $job->salary_max !== null ? number_format($job->salary_max).'万円' : '上限未確認（万円）' }}@else 未確認 @endif</dd></div>
        <div><dt>職種</dt><dd>{{ $job->occupation ?: '未確認' }}</dd></div>
    </dl>
    <a href="{{ route('public.job', ['job' => $job->id]) }}" class="site-job-link" aria-label="{{ $job->title }}の詳細を見る">詳細を見る <span aria-hidden="true">→</span></a>
</article>
