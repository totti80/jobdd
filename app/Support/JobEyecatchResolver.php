<?php

namespace App\Support;

class JobEyecatchResolver
{
    public static function importedUrl(string $provider, array $raw): ?string
    {
        if (! in_array($provider, config('eyecatch.approved_providers', []), true)) {
            return null;
        }

        return JobDecisionPresenter::safeUrl($raw['eyecatch_image_url'] ?? null);
    }

    /** Only public title/occupation enter this deterministic, display-only rule. */
    public function present(string $title, ?string $occupation, ?string $url = null): array
    {
        $text = $occupation.' '.$title;
        [$category, $label, $icon] = match (true) {
            preg_match('/PLC|制御|シーケンサ/iu', $text) === 1 => ['plc', 'PLC / 制御', 'difference'],
            preg_match('/生産技術|設備/iu', $text) === 1 => ['production', '生産技術 / 設備', 'tools'],
            preg_match('/電気|電装|回路/iu', $text) === 1 => ['electrical', '電気設計', 'compare'],
            preg_match('/機械|機構/iu', $text) === 1 => ['machine', '機械設計', 'tools'],
            default => ['generic', '仕事を知る', 'evidence'],
        };

        return compact('category', 'label', 'icon') + [
            'url' => JobDecisionPresenter::safeUrl($url),
            'fallback_asset' => 'images/jobdd/eyecatch/'.$category.'.png',
        ];
    }
}
