<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Decides where an SSO handoff token may be sent.
 *
 * The token is a credential, so the destination must be a live platform on a
 * domain we control. The registry default used to be *.infodot.app, a domain
 * nobody had registered: anyone who registered it would have received the
 * token of every user who clicked a platform in the switcher (unless the
 * production .env happened to override every DOT_*_URL). This checks the host
 * again at the moment of use, so a bad URL fails closed instead.
 */
final class EcosystemHandoff
{
    public const TOKEN_PREFIX = 'ecosystem-handoff';

    /**
     * The token's name carries its audience, so a token minted for one platform
     * cannot be redeemed by another. A token with no audience (the legacy name)
     * can only be redeemed by a platform that shares this database.
     */
    public static function tokenName(?string $platformKey = null): string
    {
        return $platformKey === null || $platformKey === '' ? self::TOKEN_PREFIX : self::TOKEN_PREFIX.':'.$platformKey;
    }

    /**
     * The full handoff URL for this person and platform (the token is minted here),
     * or null when the platform is unknown, not live or not on a trusted host. The
     * destination is checked BEFORE a token exists: a refused destination never gets one.
     * `$redirect` is an in-platform path the destination opens after sign-in; the
     * destination validates it again, so it is a hint, never an instruction.
     */
    public static function launchUrl(User $user, string $key, ?string $redirect = null): ?string
    {
        $platform = config('ecosystem.platforms', [])[$key] ?? null;

        if (! $platform) {
            return null;
        }

        $endpoint = self::endpointFor($platform);

        if ($endpoint === null) {
            Log::warning('Ecosystem handoff refused: platform is inactive or its URL is not on a trusted https domain.', [
                'platform' => $key,
                'url' => $platform['url'] ?? null,
            ]);

            return null;
        }

        $token = $user->createToken(
            self::tokenName($key),
            ['ecosystem:read'],
            now()->addMinutes((int) config('ecosystem.handoff_ttl', 5))
        )->plainTextToken;

        return $endpoint.'?token='.$token.($redirect !== null ? '&redirect='.rawurlencode($redirect) : '');
    }

    /**
     * A server to server API URL on a platform ({scheme}://{host}/path), only for a live platform on a
     * trusted https host, by the same rule as a handoff. Null when the platform must not be called.
     */
    public static function apiUrlFor(string $key, string $path): ?string
    {
        $endpoint = self::endpointFor(config('ecosystem.platforms', [])[$key] ?? null);

        return $endpoint === null ? null : substr($endpoint, 0, -strlen('/auth/ecosystem')).'/'.ltrim($path, '/');
    }

    /**
     * The URL to send the token to (without the token), or null if this
     * platform must not receive one.
     *
     * @param  array<string, mixed>|null  $platform
     */
    public static function endpointFor(?array $platform): ?string
    {
        if ($platform === null || ($platform['active'] ?? true) === false) {
            return null;
        }

        $parts = parse_url((string) ($platform['url'] ?? ''));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ! ($scheme === 'http' && config('ecosystem.allow_insecure_handoff', false))) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        foreach ((array) config('ecosystem.trusted_domains', []) as $domain) {
            $domain = strtolower(trim((string) $domain, " .\t"));

            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                // Rebuilt from scheme, host and port only: no path, query or fragment from the registry.
                return $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '').'/auth/ecosystem';
            }
        }

        return null;
    }
}
