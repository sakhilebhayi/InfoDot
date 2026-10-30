<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Links in email (password reset, email verification, team invitations) are built from the request's Host header unless the root is fixed.
 * Where the web server answers for any Host, an attacker could ask for a reset mail with a Host of their own and have the victim sent a
 * genuine token on a link to the attacker's site. In production every generated URL uses APP_URL. Requests are not refused.
 */
class CanonicalUrl
{
    public static function apply(): void
    {
        $url = (string) config('app.url');

        if ($url === '' || ! str_starts_with($url, 'http')) {
            return;
        }

        URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
