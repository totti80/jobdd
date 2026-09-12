<?php

namespace App\Services;

use App\Models\ApplicationRoute;
use App\Models\UserQuery;
use App\Services\OccupationNormalizer;
use Illuminate\Support\Collection;

class RouteSummaryService
{
  private const ROUTES = [
    'direct' => 'Direct',
    'agent' => 'Agent',
    'platform' => 'Platform',
  ];

  public function __construct(
    private readonly OccupationNormalizer $occupationNormalizer
  ) {}

  public function summarize(UserQuery $userQuery): Collection
  {
    $routes = ApplicationRoute::query()
      ->with(['jobPosting.company', 'agency', 'platform'])
      ->where('availability_status', 'available')
      ->whereNull('unavailable_at')
      ->latest('last_seen_at')
      ->get();

    $routes = $routes
      ->filter(fn(ApplicationRoute $route) => $this->matchesQuery($route, $userQuery))
      ->groupBy(fn(ApplicationRoute $route) => $route->route_type)
      ->map(fn(Collection $group) => $this->uniqueJobRoutes($group))
      ->flatten(1)
      ->values();

    return collect(self::ROUTES)->map(function (string $label, string $routeType) use ($routes, $userQuery) {
      $routeCandidates = $routes->where('route_type', $routeType)->values();
      $candidates = $routeCandidates->map(fn(ApplicationRoute $route) => $this->candidate($route));
      $evidenceCount = $routeCandidates
        ->filter(fn(ApplicationRoute $route) => $this->hasEvidence($route))
        ->pluck('job_posting_id')
        ->unique()
        ->count();

      return [
        'route_type' => $routeType,
        'label' => $label,
        'candidate_count' => $routeCandidates->count(),
        'evidence_count' => $evidenceCount,
        'occupation_match' => $this->matchValue($routeCandidates, 'occupation', $userQuery->occupation),
        'region_match' => $this->matchValue($routeCandidates, 'region', $userQuery->region),
        'salary_evidence' => $this->salaryEvidence($routeCandidates, $userQuery->salary_min),
        'latest_confirmed_at' => $routeCandidates->pluck('last_seen_at')->filter()->max(),
        'missing_items' => $this->missingItems($routeCandidates, $userQuery),
        'summary_reason' => $this->summaryReason($routeCandidates, $evidenceCount),
        'representative_candidates' => $candidates->take(3)->values(),
      ];
    })->values();
  }

  private function matchesQuery(ApplicationRoute $route, UserQuery $userQuery): bool
  {
    $job = $route->jobPosting;

    if (!$job || $job->company?->name === 'A製作所') {
      return false;
    }

    if ($userQuery->occupation !== null) {
      $occupation = $this->occupationNormalizer->normalize(
        $job->title,
        $job->description
      );

      if ($occupation !== $userQuery->occupation) {
        return false;
      }
    }

    if ($userQuery->region !== null && !str_starts_with((string) $job->region, $userQuery->region)) {
      return false;
    }

    if ($userQuery->salary_min !== null && !$this->salaryMatches($job->salary_min, $job->salary_max, $userQuery->salary_min)) {
      return false;
    }

    return true;
  }

  private function salaryMatches(?int $jobSalaryMin, ?int $jobSalaryMax, int $wantedSalaryMin): bool
  {
    if ($jobSalaryMin !== null && $jobSalaryMin >= $wantedSalaryMin) {
      return true;
    }

    return $jobSalaryMax !== null
      && $jobSalaryMax >= $wantedSalaryMin
      && ($jobSalaryMin === null || $jobSalaryMin <= $wantedSalaryMin);
  }

  private function uniqueJobRoutes(Collection $routes): Collection
  {
    return $routes
      ->sortByDesc(fn(ApplicationRoute $route) => $route->last_seen_at?->getTimestamp() ?? $route->updated_at?->getTimestamp() ?? 0)
      ->unique('job_posting_id')
      ->values();
  }

  private function candidate(ApplicationRoute $route): array
  {
    $job = $route->jobPosting;

    return [
      'route_id' => $route->id,
      'route_type' => $route->route_type,
      'platform_name' => $route->platform?->name,
      'agency_name' => $route->agency?->name,
      'company_name' => $job?->company?->name,
      'title' => $job?->title,
      'region' => $job?->region,
      'salary_min' => $job?->salary_min,
      'salary_max' => $job?->salary_max,
      'application_url' => $route->application_url,
      'source_url' => $job?->source_url,
      'confirmed_at' => $route->last_seen_at ?? $route->updated_at,
      'evidence' => $route->notes ?: '応募経路と公開求人URLを確認済みです。',
    ];
  }

  private function hasEvidence(ApplicationRoute $route): bool
  {
    return filled($route->application_url) && $route->availability_status === 'available';
  }

  private function matchValue(Collection $routes, string $field, ?string $wanted): ?bool
  {
    if (blank($wanted) || $routes->isEmpty()) {
      return null;
    }

    return $routes->contains(fn(ApplicationRoute $route) => str_contains((string) $route->jobPosting?->{$field}, $wanted));
  }

  private function salaryEvidence(Collection $routes, ?int $salaryMin): ?bool
  {
    if ($salaryMin === null || $routes->isEmpty()) {
      return null;
    }

    return $routes->contains(fn(ApplicationRoute $route) => $route->jobPosting?->salary_max === null || $route->jobPosting?->salary_max >= $salaryMin);
  }

  private function missingItems(Collection $routes, UserQuery $userQuery): array
  {
    $missing = [];

    if ($routes->isEmpty()) {
      return ['現在確認できるEvidenceがありません', '求人が存在しないことを意味しません'];
    }

    if ($userQuery->occupation !== null && $this->matchValue($routes, 'occupation', $userQuery->occupation) !== true) {
      $missing[] = '職種適合';
    }

    if ($userQuery->region !== null && $this->matchValue($routes, 'region', $userQuery->region) !== true) {
      $missing[] = '地域適合';
    }

    if ($userQuery->salary_min !== null && $this->salaryEvidence($routes, $userQuery->salary_min) !== true) {
      $missing[] = '年収条件';
    }

    if ($routes->every(fn(ApplicationRoute $route) => blank($route->application_url))) {
      $missing[] = '応募可能URL';
    }

    return $missing ?: ['未確認項目なし'];
  }

  private function summaryReason(Collection $routes, int $evidenceCount): string
  {
    if ($routes->isEmpty()) {
      return '現在確認できるEvidenceがありません。求人が存在しないことを意味しません。';
    }

    return $evidenceCount > 0
      ? '公開求人と応募経路URLを確認できる代表候補があります。'
      : '候補はありますが、応募経路のEvidenceは未確認です。';
  }
}
