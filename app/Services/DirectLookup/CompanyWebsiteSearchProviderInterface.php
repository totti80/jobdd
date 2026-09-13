<?php

namespace App\Services\DirectLookup;

interface CompanyWebsiteSearchProviderInterface
{
    /**
     * Return sanitized metadata only; never include credentials or raw exceptions.
     *
     * @return array{
     *     status: 'searched'|'skipped'|'search_failed',
     *     results: list<array{url: string, title: string, snippet: string, rank: int}>,
     *     provider: ?string, query: string, searched_at: ?string,
     *     reason: string, api_calls: ?int
     * }
     */
    public function search(string $companyName): array;
}
