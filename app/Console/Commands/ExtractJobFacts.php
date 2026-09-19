<?php

namespace App\Console\Commands;

use App\Models\JobPosting;
use App\Services\JobFactExtractor;
use Illuminate\Console\Command;

class ExtractJobFacts extends Command
{
  protected $signature = 'jobdd:extract-job-facts
        {--dry-run : Show extracted facts without writing to the database}
        {--limit= : Limit the number of job postings}
        {--job-id= : Extract one job posting by ID}';

  protected $description = 'Extract explainable Tier 2 facts from job descriptions';

  public function handle(JobFactExtractor $extractor): int
  {
    $jobs = JobPosting::query()
      ->with('company:id,name')
      ->whereNull('unavailable_at')
      ->whereIn('occupation', ['機械設計', '電気設計'])
      ->where(function ($query) {
        foreach (['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'] as $region) {
          $query->orWhere('region', 'like', $region . '%');
        }
      })
      ->when($this->option('job-id'), fn($query, $jobId) => $query->whereKey($jobId))
      ->orderBy('id');

    if ($this->option('limit') !== null) {
      $jobs->limit((int) $this->option('limit'));
    }

    $count = 0;
    $factCount = 0;
    foreach ($jobs->get() as $jobPosting) {
      $facts = $extractor->extract($jobPosting);
      $count++;
      $factCount += count($facts);
      $this->render($jobPosting, $facts);

      if (! $this->option('dry-run')) {
        $extractor->persist($jobPosting);
      }
    }

    $this->newLine();
    $this->info("Processed jobs: {$count}");
    $this->info("Detected facts: {$factCount}");
    $this->info($this->option('dry-run') ? 'Mode: dry-run (no database writes)' : 'Mode: persisted');

    return self::SUCCESS;
  }

  private function render(JobPosting $jobPosting, array $facts): void
  {
    $this->line(str_repeat('-', 32));
    $this->line("Job #{$jobPosting->id}");
    $this->line($jobPosting->company?->name ?? 'Unknown company');
    $this->line($jobPosting->title);
    $this->line("{$jobPosting->occupation} / {$jobPosting->region}");
    $this->line('Detected Facts: ' . count($facts));

    foreach ($facts as $fact) {
      $this->line("[{$fact['fact_category']}] {$fact['fact_key']}");
      $this->line("Raw: {$fact['fact_value']} -> {$fact['normalized_value']}");
      $this->line("Evidence: {$fact['evidence_text']}");
    }
  }
}
