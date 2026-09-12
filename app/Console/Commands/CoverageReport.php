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
      ['Region', 'Occupation', 'Direct', 'Agent', 'Platform', 'Direct Evidence', 'Agent Evidence', 'Platform Evidence', 'Latest Seen', 'Status'],
      $rows->map(fn(array $row) => [
        $row['region'],
        $row['occupation'],
        $row['direct_count'],
        $row['agent_count'],
        $row['platform_count'],
        $row['direct_evidence_count'],
        $row['agent_evidence_count'],
        $row['platform_evidence_count'],
        $row['latest_seen_at'] ?? '未確認',
        $row['status'],
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
    $hasThree = collect(['direct_count', 'agent_count', 'platform_count'])->every(fn(string $key) => $counts[$key] >= 3);
    $hasAny = collect($counts)->filter(fn(int $count, string $key) => str_ends_with($key, '_count') && !str_ends_with($key, '_evidence_count'))->sum() > 0;

    return [
      'region' => $region,
      'occupation' => $occupation,
      ...$counts,
      'latest_seen_at' => $latest?->format('Y-m-d H:i:s'),
      'status' => $hasThree ? 'sufficient' : ($hasAny ? 'thin' : 'empty'),
    ];
  }
}
