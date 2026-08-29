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

    $score = (int) round(
      (
        $occupationScore +
        $regionScore +
        $experienceScore +
        $salaryScore +
        $jobScore
      ) / 5
    );

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
      'score_model_version' => 'v0.1',
    ];
  }

  private function occupationScore(UserQuery $userQuery, Agency $agency): int
  {
    if (!$userQuery->occupation || !$agency->occupation) {
      return 50;
    }

    return $userQuery->occupation === $agency->occupation ? 100 : 25;
  }

  private function regionScore(UserQuery $userQuery, Agency $agency): int
  {
    if (!$userQuery->region || !$agency->region) {
      return 50;
    }

    return $userQuery->region === $agency->region ? 100 : 50;
  }

  private function experienceScore(UserQuery $userQuery, Agency $agency): int
  {
    if ($userQuery->experience_years === null) {
      return 50;
    }

    if (
      $userQuery->experience_years >= $agency->experience_min &&
      $userQuery->experience_years <= $agency->experience_max
    ) {
      return 100;
    }

    return 50;
  }

  private function salaryScore(UserQuery $userQuery, Agency $agency): int
  {
    if ($userQuery->salary_min === null) {
      return 50;
    }

    if ($agency->salary_max >= $userQuery->salary_min) {
      return 100;
    }

    return 25;
  }

  private function jobScore(Agency $agency): int
  {
    return match (true) {
      $agency->job_count >= 500 => 100,
      $agency->job_count >= 300 => 75,
      $agency->job_count >= 100 => 50,
      default => 25,
    };
  }

  private function buildReason(
    int $occupationScore,
    int $regionScore,
    int $experienceScore,
    int $salaryScore,
    int $jobScore
  ): string {
    $reasons = [];

    if ($occupationScore === 100) {
      $reasons[] = '希望職種との適合度が高い';
    }

    if ($regionScore === 100) {
      $reasons[] = '希望地域と一致している';
    }

    if ($experienceScore === 100) {
      $reasons[] = '経験年数が対応範囲内';
    }

    if ($salaryScore === 100) {
      $reasons[] = '希望年収に対応可能';
    }

    if ($jobScore >= 75) {
      $reasons[] = '公開求人件数が比較的多い';
    }

    return implode('、', $reasons);
  }
}
