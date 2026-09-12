<?php

namespace App\Console\Commands;

use App\Models\ApplicationRoute;
use App\Services\OccupationNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CoverageReport extends Command
{
  protected $signature = 'jobdd:coverage-report';

  protected $description = 'Report Direct, Agent and Platform coverage for the MVP matrix.';

  private const REGIONS = ['大阪府', '兵庫県', '京都府', '滋賀県', '奈良県', '和歌山県'];

  private const OCCUPATIONS = ['機械設計', '電気設計', '施工管理'];

  public function handle(OccupationNormalizer $normalizer): int
  {
    $routes = ApplicationRoute::query()
      ->with(['jobPosting.company'])
      ->where('availability_status', 'available')
      ->whereNull('unavailable_at')
      ->get()
      ->filter(fn(ApplicationRoute $route) => $route->jobPosting?->company?->name !== 'A製作所');

    $rows = collect(self::REGIONS)->crossJoin(self::OCCUPATIONS)->map(
      fn(array $cell) => $this->cell($cell[0], $cell[1], $routes, $normalizer)
    );

    $this->table(
      ['Region', 'Occupation', 'Direct', 'Agent', 'Platform', 'Direct Status', 'Agent Status', 'Platform Status', 'Direct Evidence', 'Agent Evidence', 'Platform Evidence', 'Latest Seen', 'Overall Status'],
      $rows->map(fn(array $row) => [
        $row['region'],
        $row['occupation'],
        $row['direct_count'],
        $row['agent_count'],
        $row['platform_count'],
        $row['direct_status'],
        $row['agent_status'],
        $row['platform_status'],
        $row['direct_evidence_count'],
        $row['agent_evidence_count'],
        $row['platform_evidence_count'],
        $row['latest_seen_at'] ?? '未確認',
        $row['overall_status'],
      ])->all()
    );

    return self::SUCCESS;
  }

  private function cell(string $region, string $occupation, Collection $routes, OccupationNormalizer $normalizer): array
  {
    $matched = $routes->filter(function (ApplicationRoute $route) use ($region, $occupation, $normalizer) {
      $job = $route->jobPosting;
      $normalized = $normalizer->normalize($job?->title, $job?->description);

      return $normalized === $occupation && str_starts_with((string) $job?->region, $region);
    });

    $counts = [];
    foreach (['direct', 'agent', 'platform'] as $type) {
      $typeRoutes = $matched->where('route_type', $type);
      $counts[$type . '_count'] = $typeRoutes->pluck('job_posting_id')->unique()->count();
      $counts[$type . '_evidence_count'] = $typeRoutes
        ->filter(fn(ApplicationRoute $route) => filled($route->application_url))
        ->pluck('job_posting_id')
        ->unique()
        ->count();
    }

    $latest = $matched->pluck('last_seen_at')->filter()->max();
    $directWasCollected = \App\Models\DirectReverseLookupCandidate::query()
      ->where('region', $region)
      ->where('occupation', $occupation)
      ->exists();

    $statuses = collect(['direct', 'agent', 'platform'])->mapWithKeys(
      fn(string $type) => [$type . '_status' => $this->statusFor(
        $counts[$type . '_count'],
        $type === 'direct' ? $directWasCollected : $counts[$type . '_count'] > 0
      )]
    );

    return [
      'region' => $region,
      'occupation' => $occupation,
      ...$counts,
      ...$statuses,
      'latest_seen_at' => $latest?->format('Y-m-d H:i:s'),
      'overall_status' => $statuses->every(fn(string $status) => $status === 'sufficient')
        ? 'sufficient'
        : 'thin',
    ];
  }

  private function statusFor(int $count, bool $wasCollected): string
  {
    if ($count >= 3) {
      return 'sufficient';
    }

    if ($count > 0) {
      return 'thin';
    }

    return $wasCollected ? 'empty' : 'not_collected';
  }
}
