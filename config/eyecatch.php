<?php

return [
    // Enable only after permission to store image URLs and hotlink has been verified.
    // No provider is approved merely because its HTML/API can be fetched.
    'approved_providers' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBDD_EYECATCH_APPROVED_PROVIDERS', ''))))),
];
