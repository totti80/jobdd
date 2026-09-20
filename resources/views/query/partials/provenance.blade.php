@php($sourceLink = \App\Support\JobDecisionPresenter::safeUrl($source['url'] ?? null))
<div class="jobdd-provenance space-y-2 text-sm leading-6 text-slate-600">
    <p>情報提供元：{{ $source['provider_key'] ?? '未確認' }}</p>
    <p>{{ $scopeLabel ?? 'この求人の掲載元' }}：
        @if ($sourceLink)
            <a href="{{ $sourceLink }}" target="_blank" rel="noopener noreferrer" class="jobdd-link">{{ $sourceLink }}<span class="sr-only">（新しいタブ）</span></a>
        @else
            求人元URL未確認
        @endif
    </p>
    @if ($sourceLink)
        <a href="{{ $sourceLink }}" target="_blank" rel="noopener noreferrer" class="jobdd-link inline-flex min-h-11 items-center">求人元を見る（新しいタブ）</a>
    @endif
    <dl class="grid gap-2 sm:grid-cols-2">
        <div><dt>最終取得日時</dt><dd>{{ $source['last_seen_at'] ?? '未確認' }}</dd></div>
        @foreach (['published_at' => '掲載日時', 'provider_updated_at' => '提供元更新日時'] as $dateKey => $dateLabel)
            @if (!empty($source[$dateKey]))
                <div><dt>{{ $dateLabel }}</dt><dd>{{ $source[$dateKey] }}</dd></div>
            @endif
        @endforeach
    </dl>
</div>
