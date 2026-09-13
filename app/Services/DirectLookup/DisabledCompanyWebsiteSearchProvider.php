<?php

namespace App\Services\DirectLookup;

/** No paid API dependency. Enable an external provider only after free usage is validated. */
class DisabledCompanyWebsiteSearchProvider implements CompanyWebsiteSearchProviderInterface
{
    public function search(string $companyName): array
    {
        return [
            'provider' => null,
            'query' => trim(preg_replace('/\s+/u', ' ', $companyName)).' 公式',
            'searched_at' => null,
            'results' => [],
            'api_calls' => 0,
            'status' => 'skipped',
            'reason' => 'free_search_provider_not_configured',
        ];
    }
}
