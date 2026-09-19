<?php

namespace App\Services;

use App\Models\JobFact;
use App\Models\JobPosting;
use Illuminate\Support\Carbon;

class JobFactExtractor
{
  public function extract(JobPosting $jobPosting): array
  {
    $text = $this->plainText((string) $jobPosting->description);
    if ($text === '') {
      return [];
    }

    $facts = [];
    foreach (JobFactDictionary::definitions() as $definition) {
      foreach ($definition['aliases'] as $alias) {
        $match = $this->findMatch($text, $alias);
        if ($match === null) {
          continue;
        }

        $facts[] = [
          'fact_category' => $definition['fact_category'],
          'fact_key' => $definition['fact_key'],
          'fact_value' => $match[0],
          'normalized_value' => $definition['normalized_value'],
          'extraction_method' => 'rule',
          'verification_status' => 'verified',
          'evidence_text' => $this->evidenceSnippet(
            $text,
            mb_strlen(substr($text, 0, $match[1]), 'UTF-8'),
            mb_strlen($match[0], 'UTF-8')
          ),
        ];
        break;
      }
    }

    return $facts;
  }

  public function persist(JobPosting $jobPosting, ?Carbon $observedAt = null): array
  {
    $observedAt ??= now();
    $saved = [];

    foreach ($this->extract($jobPosting) as $fact) {
      $saved[] = JobFact::updateOrCreate(
        [
          'job_posting_id' => $jobPosting->id,
          'fact_category' => $fact['fact_category'],
          'fact_key' => $fact['fact_key'],
          'extraction_method' => 'rule',
        ],
        [
          ...$fact,
          'job_posting_id' => $jobPosting->id,
          'source_id' => null,
          'observed_at' => $observedAt,
        ]
      );
    }

    return $saved;
  }

  private function plainText(string $description): string
  {
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  }

  private function findMatch(string $text, string $alias): ?array
  {
    $pattern = preg_quote($alias, '/');
    $asciiBoundary = preg_match('/^[A-Za-z0-9][A-Za-z0-9 .-]*[A-Za-z0-9]$/', $alias)
      ? '(?<![A-Za-z0-9])'
      : '';
    $asciiEndBoundary = preg_match('/^[A-Za-z0-9][A-Za-z0-9 .-]*[A-Za-z0-9]$/', $alias)
      ? '(?![A-Za-z0-9])'
      : '';

    if (preg_match('/' . $asciiBoundary . '(' . $pattern . ')' . $asciiEndBoundary . '/iu', $text, $matches, PREG_OFFSET_CAPTURE)) {
      return [$matches[1][0], $matches[1][1]];
    }

    return null;
  }

  private function evidenceSnippet(string $text, int $offset, int $length): string
  {
    $radius = 80;
    $start = max(0, $offset - $radius);
    $snippet = mb_substr($text, $start, $length + ($radius * 2));

    if ($start > 0) {
      $snippet = '...' . $snippet;
    }
    if ($start + mb_strlen($snippet, 'UTF-8') < mb_strlen($text, 'UTF-8')) {
      $snippet .= '...';
    }

    return $snippet;
  }
}
