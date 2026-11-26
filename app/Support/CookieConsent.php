<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * R-71: the visitor's choice about InfoDot's one cookie that is not strictly necessary (dot_src, config/acquisition.php). The choice is
 * kept in a strictly necessary first-party cookie for a year; nothing optional is set until it says "accepted".
 */
final class CookieConsent
{
    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CHOICES = [self::ACCEPTED, self::DECLINED];

    /** The choice this browser made, or null when it has not chosen yet. A changed or unknown value counts as no choice. */
    public static function choice(Request $request): ?string
    {
        $value = $request->cookie(self::name());

        return in_array($value, self::CHOICES, true) ? $value : null;
    }

    public static function accepted(Request $request): bool
    {
        return self::choice($request) === self::ACCEPTED;
    }

    /** Whether to ask: a signed-out visitor who has not chosen. Signed-in people are never given the optional cookie, so are not asked. */
    public static function shouldAsk(Request $request): bool
    {
        return $request->user() === null && self::choice($request) === null;
    }

    public static function cookie(string $choice, bool $secure): Cookie
    {
        return cookie(self::name(), $choice, (int) config('acquisition.consent_days', 365) * 1440, '/', null, $secure, true, false, 'lax');
    }

    public static function name(): string
    {
        return (string) config('acquisition.consent_cookie', 'dot_consent');
    }
}
