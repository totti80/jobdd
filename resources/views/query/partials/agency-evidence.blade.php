@forelse ($facts as $fact)
    @php($source = $fact->source)
    <div class="space-y-2 border-t border-slate-200 py-3 first:border-t-0">
        <p class="whitespace-pre-wrap break-words">保存された記載：{{ $fact->fact_value }}</p>
        <p>記録上の検証状態：{{ ['verified' => '確認済みとして記録', 'pending' => '未確認', 'rejected' => '採用されていない記録'][$fact->verification_status] ?? '未確認' }}</p>
        <p>観測日時：{{ $fact->observed_at ?? '未確認' }}</p>
        @if ($source)
            @php($sourceLink = \App\Support\AgencyDecisionPresenter::sourceUrl($source->url))
            <dl class="space-y-2 jobdd-provenance">
                <div><dt>Source</dt><dd>{{ $source->title ?: '名称未確認' }}</dd></div>
                <div><dt>発行元</dt><dd>{{ $source->publisher ?: '未確認' }}</dd></div>
                <div><dt>保存上のSource種別</dt><dd>{{ $source->source_type ?: '未確認' }}</dd></div>
                <div><dt>取得日時</dt><dd>{{ $source->fetched_at ?? '未確認' }}</dd></div>
            </dl>
            @if ($sourceLink)
                <a href="{{ $sourceLink }}" target="_blank" rel="noopener noreferrer" class="jobdd-link inline-flex min-h-12 items-center">出典を見る（新しいタブ）</a>
            @else
                <p>根拠を確認できていません。保存済みURLでは出典リンクを表示できません。</p>
            @endif
        @else
            <p>根拠を確認できていません。Sourceは未確認です。</p>
        @endif
    </div>
@empty
    <p>根拠を確認できていません</p>
@endforelse
