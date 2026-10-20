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
     * Where to send the browser to open a platform: an InfoDot URL (no token in it), or null when the platform
     * is unknown, not live or not on a trusted host. The credential is minted on the launch page itself
     * ({@see launchForm()}) and handed over in a POST body, never in a URL: a URL is shown in the address bar,
     * kept in history, written to server logs and sent in Referer headers, and a failed sign-in leaves the
     * person sitting on it. `$redirect` is an in-platform path the destination opens after sign-in; the
     * destination validates it again, so it is a hint, never an instruction.
     */
    public static function launchUrl(User $user, string $key, ?string $redirect = null): ?string
    {
        if (self::endpointFor(config('ecosystem.platforms', [])[$key] ?? null) === null) {
            Log::warning('Ecosystem handoff refused: platform is unknown, inactive or its URL is not on a trusted https domain.', ['platform' => $key]);

            return null;
        }

        $safe = self::safeRedirect($redirect);

        return route('platform.launch', ['platform' => $key] + ($safe !== null ? ['redirect' => $safe] : []));
    }

    /**
     * What the launch page posts to the platform: the destination, a single-use token that only that platform
     * can redeem, and the optional in-platform path. The destination is checked BEFORE a token exists: a
     * refused destination never gets one. Null when the platform must not receive a token.
     *
     * @return array{action: string, token: string, redirect: ?string, name: string, origin: string}|null
     */
    public static function launchForm(User $user, string $key, ?string $redirect = null): ?array
    {
        $platform = config('ecosystem.platforms', [])[$key] ?? null;
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
            now()->addSeconds(max(10, (int) config('ecosystem.launch_ttl_seconds', 60)))
        )->plainTextToken;

        $parts = parse_url($endpoint);

        return [
            'action' => $endpoint,
            'token' => $token,
            'redirect' => self::safeRedirect($redirect),
            'name' => (string) ($platform['name'] ?? $key),
            'origin' => $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
        ];
    }

    /** Only a plain absolute path on the destination is passed on: one "/", no "//", "\\", "..", control character or scheme. */
    public static function safeRedirect(?string $redirect): ?string
    {
        if ($redirect === null || $redirect === '' || strlen($redirect) > 2048) {
            return null;
        }

        foreach ([$redirect, rawurldecode($redirect)] as $candidate) {
            if (
                ! str_starts_with($candidate, '/')
                || str_starts_with($candidate, '//')
                || str_contains($candidate, '\\')
                || str_contains($candidate, '..')
                || str_contains($candidate, '//')
                || preg_match('/[\x00-\x20\x7f]/', $candidate)
            ) {
                return null;
            }
        }

        return $redirect;
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
