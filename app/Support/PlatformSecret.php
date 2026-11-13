<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Proves that a server to server call really comes from the platform it names: the bearer token must equal that
 * platform's own secret (ECOSYSTEM_SECRET_<KEY>), compared in constant time. One check for every endpoint a platform
 * calls with its secret (code redemption, the platforms read), so they cannot drift apart.
 */
final class PlatformSecret
{
    public static function proves(Request $request, string $platform): bool
    {
        $secret = (string) config('ecosystem.service_secrets.'.$platform, '');
        $bearer = (string) $request->bearerToken();

        return $secret !== '' && $bearer !== '' && hash_equals($secret, $bearer);
    }

    /** Registered in the registry and switched on. */
    public static function live(string $platform): bool
    {
        $entry = config('ecosystem.platforms.'.$platform);

        return is_array($entry) && ($entry['active'] ?? true) !== false;
    }
}
