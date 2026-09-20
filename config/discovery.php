<?php

return [
    // Registration is inert until operations explicitly enables this pipeline.
    'enabled' => env('JOBDD_DISCOVERY_ENABLED', false),
    'time' => env('JOBDD_DISCOVERY_TIME', '04:00'),
    'timezone' => 'Asia/Tokyo',
    'report_directory' => storage_path('app/jobdd/discovery'),
    'fetch_timeout' => 1800,
    'careerjet_api_key' => env('CAREERJET_API_KEY'),
    // Set only after checking access/storage terms and the exact search URL for each cell.
    'approved_providers' => array_filter(explode(',', env('JOBDD_DISCOVERY_APPROVED_PROVIDERS', ''))),
    'search_urls' => json_decode(env('JOBDD_DISCOVERY_SEARCH_URLS', '{}'), true) ?: [],
];
