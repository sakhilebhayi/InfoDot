<?php

namespace App\Services\Sso;

use App\Models\DotPlatform;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * R-54: a sign-in that detours through "switch this platform on" must come back to the sign-in, not to the legacy /go
 * handoff (which loses the platform's state, and with it the deep link the person was going to). When the OAuth door
 * refuses a platform the exact authorize request is parked in the visitor's SERVER-side session; once the platform is
 * on, it is resumed.
 *
 * The parked request is believed only under the same rules the sign-in pages use for a pending request: the path is
 * exactly /oauth/authorize on this host, the client is a registered ACTIVE platform and the one being switched on,
 * response_type is code, and redirect_uri is exactly one of that platform's registered URIs. Anything else is dropped
 * with a reason-only log line and the old behaviour applies. Nothing the browser sends at resume time is read: the
 * resumed address is rebuilt from the parked, validated values.
 */
class ResumeAuthorize
{
    private const KEY = 'sso.resume_authorize';

    public static function remember(Request $request): void
    {
        if ($request->is('oauth/authorize') && $request->isMethod('GET') && $request->hasSession()) {
            $request->session()->put(self::KEY, $request->getQueryString());
        }
    }

    /** The relative address to resume for this platform key, or null. A parked request is spent either way. */
    public static function take(Request $request, string $platformKey): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $raw = $request->session()->pull(self::KEY);

        if (! is_string($raw) || $raw === '' || strlen($raw) > 4096) {
            return null;
        }

        parse_str($raw, $q);

        $platform = DotPlatform::findByClientId(Input::text($q['client_id'] ?? '') ?: null);
        $redirect = $q['redirect_uri'] ?? null;

        $why = match (true) {
            $platform === null || ! $platform->isActive() => 'client_not_active',
            (string) $platform->slug !== $platformKey => 'other_platform',
            ($q['response_type'] ?? null) !== 'code' => 'response_type',
            ! is_string($redirect) || ! in_array($redirect, $platform->redirect_uris ?? [], true) => 'redirect_uri_not_registered',
            default => null,
        };

        if ($why !== null) {
            Log::info('sso.resume_ignored', ['reason' => $why]);

            return null;
        }

        return '/oauth/authorize?'.http_build_query(array_filter($q, 'is_scalar'), '', '&', PHP_QUERY_RFC3986);
    }
}
