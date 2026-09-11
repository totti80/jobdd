<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\UserQuery;

class ScoreService
{
    public const REQUIRED_FACT_KEYS = [
        'supported_occupation',
        'supported_region',
        'experience_range',
        'salary_range',

        // Scoreには使用しない。
        // Evidenceとして保持・表示するため、調査対象には残す。
        'public_job_count',
    ];

    public function calculate(UserQuery $userQuery, Agency $agency): array
    {
        $occupationScore = $this->occupationScore($userQuery, $agency);
        $regionScore = $this->regionScore($userQuery, $agency);
        $experienceScore = $this->experienceScore($userQuery, $agency);
        $salaryScore = $this->salaryScore($userQuery, $agency);

        // v0.3:
        // 公開求人件数はAgentの総求人保有力を表さないため、
        // JobDD Scoreには含めない。
        $availableScores = array_filter(
            [
                $occupationScore,
                $regionScore,
                $experienceScore,
                $salaryScore,
            ],
            fn($score) => $score !== null
        );

        $score = count($availableScores) > 0
            ? (int) round(array_sum($availableScores) / count($availableScores))
            : 0;

        return [
            'score' => $score,
            'occupation_score' => $occupationScore,
            'region_score' => $regionScore,
            'experience_score' => $experienceScore,
            'salary_score' => $salaryScore,

            // 既存DB・Viewとの互換性のためキーは残す。
            // v0.3ではScore計算には使用しない。
            'job_score' => null,

            'reason' => $this->buildReason(
                $occupationScore,
                $regionScore,
                $experienceScore,
                $salaryScore
            ),
            'score_model_version' => 'v0.3',
        ];
    }

    private function occupationScore(
        UserQuery $userQuery,
        Agency $agency
    ): ?int {
        if (!$userQuery->occupation) {
            return null;
        }

        $occupation = $this->verifiedFact(
            $agency,
            'supported_occupation'
        );

        if ($occupation === null) {
            return null;
        }

        if (
            $occupation === '全職種' ||
            str_contains($occupation, $userQuery->occupation)
        ) {
            return 100;
        }

        return 25;
    }

    private function regionScore(
        UserQuery $userQuery,
        Agency $agency
    ): ?int {
        if (!$userQuery->region) {
            return null;
        }

        $region = $this->verifiedFact(
            $agency,
            'supported_region'
        );

        if ($region === null) {
            return null;
        }

        if (
            str_contains($region, '全国') ||
            str_contains($region, '日本国内') ||
            str_contains($region, $userQuery->region)
        ) {
            return 100;
        }

        return 50;
    }

    private function experienceScore(
        UserQuery $userQuery,
        Agency $agency
    ): ?int {
        if ($userQuery->experience_years === null) {
            return null;
        }

        $range = $this->verifiedFact(
            $agency,
            'experience_range'
        );

        if ($range === null) {
            return null;
        }

        [$min, $max] = $this->parseRange($range);

        if ($min === null || $max === null) {
            return null;
        }

        if (
            $userQuery->experience_years >= $min &&
            $userQuery->experience_years <= $max
        ) {
            return 100;
        }

        return 50;
    }

    private function salaryScore(
        UserQuery $userQuery,
        Agency $agency
    ): ?int {
        if ($userQuery->salary_min === null) {
            return null;
        }

        $range = $this->verifiedFact(
            $agency,
            'salary_range'
        );

        if ($range === null) {
            return null;
        }

        [$min, $max] = $this->parseRange($range);

        if ($min === null || $max === null) {
            return null;
        }

        return $max >= $userQuery->salary_min
            ? 100
            : 25;
    }

    private function verifiedFact(
        Agency $agency,
        string $factKey
    ): ?string {
        return $agency->facts()
            ->where('fact_key', $factKey)
            ->where('verification_status', 'verified')
            ->latest('observed_at')
            ->value('fact_value');
    }

    private function parseRange(string $value): array
    {
        if (
            preg_match(
                '/(\d+)\s*[〜～\-]\s*(\d+)/u',
                $value,
                $matches
            )
        ) {
            return [
                (int) $matches[1],
                (int) $matches[2],
            ];
        }

        return [null, null];
    }

    private function buildReason(
        ?int $occupationScore,
        ?int $regionScore,
        ?int $experienceScore,
        ?int $salaryScore
    ): string {
        $reasons = [];

        if ($occupationScore === 100) {
            $reasons[] = '確認済み情報で希望職種との適合が確認できる';
        }

        if ($regionScore === 100) {
            $reasons[] = '確認済み情報で希望地域への対応が確認できる';
        }

        if ($experienceScore === 100) {
            $reasons[] = '確認済み情報で経験年数が対応範囲内';
        }

        if ($salaryScore === 100) {
            $reasons[] = '確認済み情報で希望年収への対応が確認できる';
        }

        if (empty($reasons)) {
            return '確認済み情報の範囲では、強い適合理由は確認できませんでした。';
        }

        return implode('、', $reasons);
    }
}
