<?php

return [
    // Registration is inert until operations explicitly enables this pipeline.
    'enabled' => env('JOBDD_DISCOVERY_ENABLED', false),
    'time' => env('JOBDD_DISCOVERY_TIME', '04:00'),
    'timezone' => 'Asia/Tokyo',
    'report_directory' => storage_path('app/jobdd/discovery'),
    'fetch_timeout' => 1800,
    // Absence in a bounded Careerjet search window is not a missing-job observation.
    'provider_capabilities' => [
        'careerjet' => ['mode' => 'read_only', 'supports_persistent_identity' => false,
            'supports_complete_snapshot' => false, 'supports_missing_detection' => false,
            'supports_direct_candidate_generation' => false],
        // Onboarding has not established permission for persistent storage.
        'recruit_agent' => ['mode' => 'read_only', 'supports_persistent_identity' => true,
            'supports_complete_snapshot' => false, 'supports_missing_detection' => false,
            'supports_direct_candidate_generation' => false],
        // Existing import capability retained; no new access approval or complete-snapshot claim.
        'meitec_next' => ['mode' => 'persistent', 'supports_persistent_identity' => true,
            'supports_complete_snapshot' => false, 'supports_missing_detection' => false,
            'supports_direct_candidate_generation' => true],
    ],
    'careerjet_api_key' => env('CAREERJET_API_KEY'),
    // Set only after checking access/storage terms and the exact search URL for each cell.
    'approved_providers' => array_filter(explode(',', env('JOBDD_DISCOVERY_APPROVED_PROVIDERS', ''))),
    'search_urls' => json_decode(env('JOBDD_DISCOVERY_SEARCH_URLS', '{}'), true) ?: [],
];
