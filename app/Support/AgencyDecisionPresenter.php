<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyFact;
use App\Models\UserQuery;
use Illuminate\Support\Collection;

/** Read-only interpretation of saved Facts; never computes an agency rating. */
class AgencyDecisionPresenter
{
    public static function sourceUrl(mixed $url): ?string
    {
        $url = JobDecisionPresenter::safeUrl($url);
        if ($url === null) {
            return null;
        }
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        foreach (['example.com', 'example.org', 'example.net', 'test', 'invalid', 'localhost', 'example'] as $fixture) {
            if ($host === $fixture || str_ends_with($host, '.'.$fixture)) {
                return null;
            }
        }

        return $url;
    }

    public function candidate(Agency $agency): bool
    {
        return self::sourceUrl($agency->website_url) !== null
            || $agency->facts->contains(fn (AgencyFact $fact) => self::sourceUrl($fact->source?->url) !== null);
    }

    public function present(Agency $agency, UserQuery $query): array
    {
        $domain = [
            $this->item($agency, 'supported_occupation', '対応職種', $query->occupation, ['全職種']),
            $this->item($agency, 'supported_region', '対応地域', $query->region, ['全国', '日本国内', '日本国内（全国）']),
        ];
        // These exact keys/values are a display vocabulary, not an extractor or new persistence contract.
        // Unrecognised prose remains unknown and is available as saved Evidence.
        $opportunity = [
            $this->item($agency, 'non_public_jobs', '非公開求人の取扱い'),
            ['key' => 'personal_opportunities', 'label' => 'あなたの条件に合う紹介可能求人', 'status' => 'unknown',
                'reason' => '公開情報だけでは確認できません。現在の紹介可否と条件は相談時に確認してください。', 'facts' => collect()],
        ];
        $advisory = [
            $this->item($agency, 'technical_advisor', '技術職専門担当'),
            $this->item($agency, 'career_consultation', 'キャリア相談'),
            $this->item($agency, 'document_support', '書類支援'),
            $this->item($agency, 'interview_support', '面接対策'),
        ];
        $outcome = [$this->item($agency, 'manufacturing_placement', '製造業の紹介・入社実績')];
        $publicFacts = $agency->facts->where('fact_key', 'public_job_count')->values();
        $counts = $publicFacts->filter(fn ($fact) => $this->usable($fact))
            ->map(fn ($fact) => preg_match('/^(0|[1-9][0-9]*)(?:件)?$/u', trim($fact->fact_value), $m) ? $m[1] : null)
            ->filter(fn ($value) => $value !== null)->unique()->values();

        return [
            'id' => $agency->id, 'name' => $agency->name,
            'website_url' => self::sourceUrl($agency->website_url),
            'summary' => collect($domain)->where('status', 'match')->pluck('label')->isNotEmpty()
                ? '確認できた対応領域：'.collect($domain)->where('status', 'match')->map(fn ($item) => $item['label'].'（'.$item['target'].'）')->implode('・')
                : '希望条件に対応する領域は未確認です。',
            'public_jobs' => ['count' => $counts->count() === 1 ? $counts[0] : null, 'facts' => $publicFacts],
            'layers' => [
                ['key' => 'domain', 'label' => '専門領域との適合', 'items' => $domain],
                ['key' => 'opportunity', 'label' => '求人・機会へのアクセス', 'items' => $opportunity],
                ['key' => 'advisory', 'label' => '相談・支援との適合', 'items' => $advisory],
                ['key' => 'outcome', 'label' => '支援実績の根拠', 'items' => $outcome],
            ],
        ];
    }

    private function usable(AgencyFact $fact): bool
    {
        return $fact->verification_status === 'verified' && trim($fact->fact_value) !== ''
            && self::sourceUrl($fact->source?->url) !== null;
    }

    private function item(Agency $agency, string $key, string $label, ?string $target = null, array $universal = []): array
    {
        $facts = $agency->facts->where('fact_key', $key)->values();
        $states = $facts->filter(fn ($fact) => $this->usable($fact))
            ->map(function ($fact) use ($target, $universal, $key) {
                $value = trim($fact->fact_value);
                if (in_array($key, ['supported_occupation', 'supported_region'], true)) {
                    if ($target === null || $target === '') {
                        return 'unknown';
                    }
                    if (in_array($value, [$target.'：非対応', $target.'：対象外'], true)) {
                        return 'mismatch';
                    }
                    // A list is not exhaustive; absent values never mean unsupported.
                    $tokens = preg_split('/[、,・\/\s]+/u', $value);

                    return in_array($target, $tokens, true) || in_array($value, $universal, true) ? 'match' : 'unknown';
                }

                return match ($value) {
                    'あり', '対応', '実績あり' => 'match',
                    'なし', '非対応', '実績なし' => 'mismatch',
                    default => 'unknown',
                };
            });
        $status = $this->resolve($states);
        $reason = match ($status) {
            'match' => '保存済みの確認済みFactに記載があります。現在の提供状況は相談時に確認してください。',
            'mismatch' => '保存済みの確認済みFactに、非対応・対象外または実績なしの明示があります。',
            default => '根拠を確認できていません。対応内容は公式情報または相談時に確認してください。',
        };
        if ($states->contains('match') && $states->contains('mismatch')) {
            $reason = '保存された根拠に異なる記載があるため、確認が必要です。';
        }

        return compact('key', 'label', 'target', 'status', 'reason', 'facts');
    }

    private function resolve(Collection $states): string
    {
        if ($states->contains('match') && $states->contains('mismatch')) {
            return 'unknown';
        }

        return $states->contains('mismatch') ? 'mismatch' : ($states->contains('match') ? 'match' : 'unknown');
    }
}
