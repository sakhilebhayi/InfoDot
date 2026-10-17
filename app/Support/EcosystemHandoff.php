<?php

namespace App\Support;

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
