<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Support\Collection;

class JobFactAuditService
{
  public const REGIONS = [
    '兵庫県',
    '大阪府',
    '京都府',
    '滋賀県',
    '奈良県',
    '和歌山県',
  ];

  public const OCCUPATIONS = [
    '機械設計',
    '電気設計',
  ];

  public const PRIORITY_FACT_KEYS = [
    'fa',
    'nx',
    'plc',
    'fem',
    'cae',
    'electrical_cad',
  ];

  public function sampleCell(string $region, string $occupation, int $perCell = 10): Collection
  {
    if (
      ! in_array($region, self::REGIONS, true)
      || ! in_array($occupation, self::OCCUPATIONS, true)
      || $perCell < 1
    ) {
      return collect();
    }

    $jobs = JobPosting::query()
      ->with('company:id,name')
      ->whereNull('unavailable_at')
      ->where('occupation', $occupation)
      ->where('region', 'like', $region . '%')
      ->orderBy('id')
      ->get();

    return $this->evenlySample($jobs, $perCell);
  }

  public function audit(JobFactExtractor $extractor, int $perCell = 10): array
  {
    $cells = [];
    $jobs = collect();

    foreach (self::REGIONS as $region) {
      foreach (self::OCCUPATIONS as $occupation) {
        $sample = $this->sampleCell($region, $occupation, $perCell);
        $factsByJob = $sample->mapWithKeys(
          fn(JobPosting $job) => [$job->id => $extractor->extract($job)]
        );

        $cells[] = [
          'region' => $region,
          'occupation' => $occupation,
          'job_count' => $sample->count(),
          'fact_job_count' => $factsByJob->filter(fn(array $facts) => count($facts) > 0)->count(),
          'fact_count' => $factsByJob->sum(fn(array $facts) => count($facts)),
          'fact_counts' => $factsByJob
            ->flatMap(fn(array $facts) => collect($facts)->pluck('fact_key'))
            ->countBy()
            ->sortDesc()
            ->all(),
          'jobs' => $sample->map(fn(JobPosting $job) => [
            'job' => $job,
            'facts' => $factsByJob[$job->id] ?? [],
          ])->values()->all(),
        ];

        $jobs = $jobs->merge($sample);
      }
    }

    $allFacts = collect($cells)
      ->flatMap(fn(array $cell) => collect($cell['jobs'])->flatMap(
        fn(array $row) => collect($row['facts'])->map(fn(array $fact) => [
          ...$fact,
          'job_id' => $row['job']->id,
          'company_name' => $row['job']->company?->name,
          'job_title' => $row['job']->title,
          'occupation' => $row['job']->occupation,
          'region' => $row['job']->region,
          'provider_key' => $row['job']->provider_key,
        ])
      ));

    return [
      'cells' => $cells,
      'jobs' => $jobs->unique('id')->values(),
      'job_count' => $jobs->unique('id')->count(),
      'fact_job_count' => collect($cells)->flatMap(fn(array $cell) => collect($cell['jobs']))
        ->filter(fn(array $row) => count($row['facts']) > 0)
        ->unique(fn(array $row) => $row['job']->id)
        ->count(),
      'fact_count' => $allFacts->count(),
      'fact_counts' => $allFacts->pluck('fact_key')->countBy()->sortDesc()->all(),
      'priority_facts' => $allFacts
        ->filter(fn(array $fact) => in_array($fact['fact_key'], self::PRIORITY_FACT_KEYS, true))
        ->values()
        ->all(),
      'review_candidates' => [
        'high_frequency_facts' => collect($allFacts->pluck('fact_key')->countBy())
          ->filter(fn(int $count) => $count >= 5)
          ->sortDesc()
          ->all(),
        'high_fact_jobs' => collect($cells)
          ->flatMap(fn(array $cell) => collect($cell['jobs']))
          ->filter(fn(array $row) => count($row['facts']) >= 6)
          ->map(fn(array $row) => [
            'job_id' => $row['job']->id,
            'title' => $row['job']->title,
            'fact_count' => count($row['facts']),
          ])
          ->values()
          ->all(),
      ],
    ];
  }

  private function evenlySample(Collection $jobs, int $perCell): Collection
  {
    if ($jobs->count() <= $perCell) {
      return $jobs->values();
    }

    $lastIndex = $jobs->count() - 1;
    $lastSampleIndex = $perCell - 1;
    $indexes = collect(range(0, $lastSampleIndex))
      ->map(fn(int $index) => (int) floor(($index * $lastIndex) / $lastSampleIndex))
      ->unique();

    return $indexes->map(fn(int $index) => $jobs->get($index))->values();
  }
}
