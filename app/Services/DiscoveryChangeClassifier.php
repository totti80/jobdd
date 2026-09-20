<?php

namespace App\Services;

class DiscoveryChangeClassifier
{
    public const FIELDS = ['company_name', 'title', 'occupation', 'region', 'salary_min', 'salary_max',
        'description', 'employment_type', 'source_url', 'published_at', 'provider_updated_at', 'unavailable_at'];

    public function classify(?array $before, array $after): string
    {
        if ($before === null) {
            return 'new';
        }
        foreach (self::FIELDS as $field) {
            if (($before[$field] ?? null) != ($after[$field] ?? null)) {
                return 'updated';
            }
        }

        return 'unchanged';
    }
}
