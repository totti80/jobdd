<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\UserQuery;

class ScoreService
{
    public function calculate(UserQuery $userQuery, Agency $agency): array
    {
        $occupationScore = $this->occupationScore($userQuery, $agency);
        $regionScore = $this->regionScore($userQuery, $agency);
        $experienceScore = $this->experienceScore($userQuery, $agency);
        $salaryScore = $this->salaryScore($userQuery, $agency);
        $jobScore = $this->jobScore($agency);

        // 根拠が存在する軸だけで総合点を計算する
        $availableScores = array_filter(
            [
                $occupationScore,
                $regionScore,
                $experienceScore,
                $salaryScore,
                $jobScore,
            ],
            fn ($score) => $score !== null
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
            'job_score' => $jobScore,
            'reason' => $this->buildReason(
                $occupationScore,
                $regionScore,
                $experienceScore,
                $salaryScore,
                $jobScore
            ),
            'score_model_version' => 'v0.2',
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

        return $userQuery->occupation === $occupation
            ? 100
            : 25;
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

        return $userQuery->region === $region
            ? 100
            : 50;
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

    private function jobScore(Agency $agency): ?int
    {
        $jobCountText = $this->verifiedFact(
            $agency,
            'public_job_count'
        );

        if ($jobCountText === null) {
            return null;
        }

        if (!preg_match('/(\d+)/', $jobCountText, $matches)) {
            return null;
        }

        $jobCount = (int) $matches[1];

        return match (true) {
            $jobCount >= 500 => 100,
            $jobCount >= 300 => 75,
            $jobCount >= 100 => 50,
            default => 25,
        };
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
        ?int $salaryScore,
        ?int $jobScore
    ): string {
        $reasons = [];

        if ($occupationScore === 100) {
            $reasons[] = '確認済み情報で希望職種との適合が確認できる';
        }

        if ($regionScore === 100) {
            $reasons[] = '確認済み情報で希望地域との一致が確認できる';
        }

        if ($experienceScore === 100) {
            $reasons[] = '確認済み情報で経験年数が対応範囲内';
        }

        if ($salaryScore === 100) {
            $reasons[] = '確認済み情報で希望年収への対応が確認できる';
        }

        if ($jobScore !== null && $jobScore >= 75) {
            $reasons[] = '確認済みの公開求人件数が比較的多い';
        }

        if (empty($reasons)) {
            return '確認済み情報の範囲では、強い適合理由は確認できませんでした。';
        }

        return implode('、', $reasons);
    }
}