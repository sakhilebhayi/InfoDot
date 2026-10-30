<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Links in email (password reset, email verification, team invitations) are built from the request's Host header unless the root is fixed.
 * Where the web server answers for any Host, an attacker could ask for a reset mail with a Host of their own and have the victim sent a
 * genuine token on a link to the attacker's site.
 *
 * In production, a request whose Host is not this site's own gets every generated URL fixed to APP_URL. A request for the site itself,
 * including the www form of it (the site is served there too), is left alone: forms post to absolute route() URLs, so forcing the apex
 * for a visitor on www would send their login to a host that does not have their session cookie. Requests are never refused.
 */
class CanonicalUrl
{
    public static function apply(?Request $request = null): void
    {
        $url = (string) config('app.url');
        $own = self::ownHosts($url);

        if ($own === []) {
            return;
        }

        $host = $request ? strtolower($request->getHost()) : null;

        if ($host !== null && in_array($host, $own, true)) {
            return;
        }

        URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }

    /** @return list<string> the site's host and its www form, lower case */
    public static function ownHosts(string $appUrl): array
    {
        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));

        if ($host === '' || ! str_starts_with($appUrl, 'http')) {
            return [];
        }

        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return array_values(array_unique([$bare, 'www.'.$bare]));
    }
}
