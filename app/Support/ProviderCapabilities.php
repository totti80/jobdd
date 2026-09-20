<?php

namespace App\Support;

class ProviderCapabilities
{
    public static function get(string $provider): array
    {
        return array_replace([
            'mode' => 'read_only', 'supports_persistent_identity' => false,
            'supports_complete_snapshot' => false, 'supports_missing_detection' => false,
            'supports_direct_candidate_generation' => false,
        ], config('discovery.provider_capabilities.'.$provider, []));
    }

    public static function persistent(string $provider): bool
    {
        $capability = self::get($provider);

        return $capability['mode'] === 'persistent' && $capability['supports_persistent_identity'] === true;
    }
}
