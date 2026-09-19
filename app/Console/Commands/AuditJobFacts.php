<?php

namespace App\Console\Commands;

use App\Services\JobFactAuditService;
use App\Services\JobFactExtractor;
use Illuminate\Console\Command;

class AuditJobFacts extends Command
{
  protected $signature = 'jobdd:audit-job-facts
        {--limit-per-cell=10 : Maximum reproducible sample size per cell}';

  protected $description = 'Audit Tier 2 rule facts across the 12 JobDD v0.1 cells';

  public function handle(JobFactAuditService $auditService, JobFactExtractor $extractor): int
  {
    $report = $auditService->audit($extractor, max(1, (int) $this->option('limit-per-cell')));

    $this->line('=== Cell Summary ===');
    foreach ($report['cells'] as $cell) {
      $this->line(sprintf(
        '%s / %s: jobs=%d, fact_jobs=%d, facts=%d, by_fact=%s',
        $cell['region'],
        $cell['occupation'],
        $cell['job_count'],
        $cell['fact_job_count'],
        $cell['fact_count'],
        $this->formatCounts($cell['fact_counts'])
      ));
    }

    $this->newLine();
    $this->line('=== Overall Summary ===');
    $this->line("Jobs: {$report['job_count']}");
    $this->line("Fact jobs: {$report['fact_job_count']}");
    $this->line("Facts: {$report['fact_count']}");
    $this->line('By fact: ' . $this->formatCounts($report['fact_counts']));

    $this->newLine();
    $this->line('=== Suspicious Patterns / Review Candidates ===');
    $this->line('High-frequency facts (review, not automatic false positives): ' . $this->formatCounts($report['review_candidates']['high_frequency_facts']));
    foreach ($report['review_candidates']['high_fact_jobs'] as $job) {
      $this->line("High fact count: Job #{$job['job_id']} ({$job['fact_count']}) {$job['title']}");
    }

    $this->newLine();
    $this->line('=== Priority Fact Detail ===');
    foreach ($report['priority_facts'] as $fact) {
      $this->line(sprintf(
        'Job #%d | %s | %s | provider=%s | [%s] %s -> %s | %s',
        $fact['job_id'],
        $fact['company_name'] ?? 'Unknown company',
        $fact['job_title'],
        $fact['provider_key'] ?? 'null',
        $fact['fact_key'],
        $fact['fact_value'],
        $fact['normalized_value'],
        $fact['evidence_text']
      ));
    }

    $this->newLine();
    $this->line('=== Detection Detail ===');
    foreach ($report['cells'] as $cell) {
      foreach ($cell['jobs'] as $row) {
        $job = $row['job'];
        if (count($row['facts']) === 0) {
          continue;
        }

        $this->line(sprintf(
          'Job #%d | %s | %s | %s / %s | provider=%s',
          $job->id,
          $job->company?->name ?? 'Unknown company',
          $job->title,
          $job->occupation,
          $job->region,
          $job->provider_key ?? 'null'
        ));
        foreach ($row['facts'] as $fact) {
          $this->line(sprintf(
            '  %s/%s: %s -> %s | Evidence: %s',
            $fact['fact_category'],
            $fact['fact_key'],
            $fact['fact_value'],
            $fact['normalized_value'],
            $fact['evidence_text']
          ));
        }
      }
    }

    $this->newLine();
    $this->info('Mode: audit dry-run (no database writes)');

    return self::SUCCESS;
  }

  private function formatCounts(array $counts): string
  {
    if ($counts === []) {
      return 'none';
    }

    return collect($counts)
      ->map(fn(int $count, string $key) => "{$key}:{$count}")
      ->implode(', ');
  }
}
