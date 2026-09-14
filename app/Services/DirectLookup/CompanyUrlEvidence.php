<?php

namespace App\Services\DirectLookup;

use Illuminate\Support\Facades\DB;

/** Append observed URL evidence inside the importer's existing job transaction. */
class CompanyUrlEvidence
{
    public function save(int $jobId, int $companyId, array $row, string $provider): void
    {
        $incoming = $row['company_url_evidence'] ?? [];
        if (! is_array($incoming) || ! $incoming) {
            return;
        }
        $job = DB::table('job_postings')->where('id', $jobId)->lockForUpdate()->first();
        // Existing provider identities must not attach evidence to a different employer.
        if (! $job || $job->company_id !== $companyId) {
            throw new \InvalidArgumentException('Company identity mismatch for URL evidence.');
        }
        $name = DB::table('companies')->where('id', $companyId)->value('name');
        $saved = json_decode($job->company_url_evidence ?? '[]', true);
        foreach (array_slice($incoming, 0, 40) as $entry) {
            if (! is_array($entry) || ($entry['company_name'] ?? null) !== $name
                || ($entry['source_provider'] ?? null) !== $provider) {
                continue;
            }
            foreach (['raw_value', 'raw_field', 'source_url', 'fetched_at'] as $field) {
                if (! is_string($entry[$field] ?? null) || $entry[$field] === '' || strlen($entry[$field]) > 2048) {
                    continue 2;
                }
            }
            foreach (['raw_value', 'source_url'] as $field) {
                if (! filter_var($entry[$field], FILTER_VALIDATE_URL)
                    || ! in_array(parse_url($entry[$field], PHP_URL_SCHEME), ['http', 'https'], true)) {
                    continue 2;
                }
            }
            try {
                new \DateTimeImmutable($entry['fetched_at']);
            } catch (\Exception) {
                continue;
            }
            $observation = array_intersect_key($entry, array_flip([
                'source_provider', 'source_url', 'company_name', 'raw_field', 'raw_value', 'fetched_at',
            ]));
            if (is_string($entry['link_text'] ?? null)) {
                $observation['link_text'] = mb_substr($entry['link_text'], 0, 200);
            }
            $observation['review_status'] = 'needs_review';
            $identity = fn ($item) => [$item['source_provider'], $item['source_url'], $item['company_name'], $item['raw_field'], $item['raw_value']];
            if (! collect($saved)->contains(fn ($item) => $identity($item) === $identity($observation))) {
                $saved[] = $observation;
            }
        }
        DB::table('job_postings')->where('id', $jobId)->update([
            'company_url_evidence' => json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }
}
