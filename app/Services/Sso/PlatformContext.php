<?php

namespace App\Services\Sso;

use App\Http\Middleware\Oidc\EnforceOidcRules;
use App\Models\DotPlatform;
use App\Support\Branding\PlatformBrandingView;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * R-54: which platform started this sign-in, and ONLY from evidence the server holds.
 *
 * The one source is a pending `/oauth/authorize` request of this browser session, kept server-side (`url.intended`
 * from the guest redirect, or the copy the authorize route remembers). It is believed only when it passes the same
 * tests the authorize endpoint applies (a registered, active client; code flow with PKCE S256, state and nonce) AND
 * its redirect URI is exactly one the platform registered. Nothing in the query string of /login or /register is ever
 * read: `?platform=` and `?client=` do not exist as far as this class is concerned, so a link cannot dress the page
 * as another platform. Anything malformed, unknown, inactive or tampered resolves to null, which is the standard
 * InfoDot page, and is logged by reason only.
 */
class PlatformContext
{
    public const SESSION_KEY = 'sso.pending_authorize';

    /** When the pending request was last seen. A sign-in that was started and abandoned must not dress InfoDot's own page as that platform for the rest of the session. */
    public const SESSION_AT_KEY = 'sso.pending_at';

    public const FRESH_FOR_SECONDS = 1800;

    private const CACHE = 'sso.platform_context';

    private function request(): Request
    {
        return request();
    }

    /** The platform that started the pending sign-in, or null for a direct visit. */
    public function platform(): ?DotPlatform
    {
        return $this->resolve()['platform'];
    }

    /** The validated pending authorize URL (our own endpoint on our own host), or null. */
    public function pendingUrl(): ?string
    {
        return $this->resolve()['url'];
    }

    public function branding(): ?PlatformBrandingView
    {
        $platform = $this->platform();

        return $platform ? PlatformBrandingView::forPlatform($platform) : null;
    }

    /** The authorize route calls this so the request survives the login, registration and verification detours. */
    public function remember(string $url): void
    {
        if ($this->judge($url) !== null) {
            $this->request()->session()->put(self::SESSION_KEY, $url);
            $this->request()->session()->put(self::SESSION_AT_KEY, time());
            $this->request()->attributes->remove(self::CACHE);
        }
    }

    public function forget(): void
    {
        $session = $this->request()->session();
        $session->forget([self::SESSION_KEY, self::SESSION_AT_KEY]);

        // The guest redirect parked the same request as the place to go after signing in: it goes too, or signing in to InfoDot would carry on to the platform.
        if (is_string($intended = $session->get('url.intended')) && $this->isAuthorizeAddress($intended)) {
            $session->forget('url.intended');
        }

        $this->request()->attributes->remove(self::CACHE);
    }

    /** @return array{platform: ?DotPlatform, url: ?string} resolved once per request */
    private function resolve(): array
    {
        $request = $this->request();

        if (is_array($cached = $request->attributes->get(self::CACHE))) {
            return $cached;
        }

        $result = ['platform' => null, 'url' => null];

        if ($request->hasSession() && $this->isStale($request)) {
            // Started a while ago and never finished: this is a fresh visit to InfoDot, not a step of that sign-in.
            if ($this->hasPendingAddress($request)) {
                $this->forget();
            }
        } elseif ($request->hasSession()) {
            foreach ([self::SESSION_KEY, 'url.intended'] as $key) {
                $candidate = $request->session()->get($key);

                // A pending address that is not an authorize request at all (an ordinary intended page) is not ours to judge.
                if (! is_string($candidate) || $candidate === '' || ! $this->isAuthorizeAddress($candidate)) {
                    continue;
                }

                if (($platform = $this->judge($candidate)) !== null) {
                    $result = ['platform' => $platform, 'url' => $candidate];

                    break;
                }
            }
        }

        $request->attributes->set(self::CACHE, $result);

        return $result;
    }

    private function isStale(Request $request): bool
    {
        $at = $request->session()->get(self::SESSION_AT_KEY);

        return ! is_int($at) || (time() - $at) > self::FRESH_FOR_SECONDS;
    }

    private function hasPendingAddress(Request $request): bool
    {
        foreach ([self::SESSION_KEY, 'url.intended'] as $key) {
            $candidate = $request->session()->get($key);

            if (is_string($candidate) && $this->isAuthorizeAddress($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isAuthorizeAddress(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) && ($parts['path'] ?? null) === '/oauth/authorize';
    }

    private function judge(string $url): ?DotPlatform
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['path'] ?? null) !== '/oauth/authorize' || strlen($url) > 4096) {
            return $this->reject('malformed');
        }

        // Our own endpoint on the host the person is on: a stored address pointing anywhere else is never followed.
        if (isset($parts['host']) && strcasecmp((string) $parts['host'], $this->request()->getHost()) !== 0) {
            return $this->reject('foreign_host');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->reject('malformed');
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $platform = DotPlatform::findByClientId(Input::text($query['client_id'] ?? null) ?: null);

        if (($reason = EnforceOidcRules::rejectionReason($query, $platform)) !== null) {
            return $this->reject($reason);
        }

        // The redirect URI must be exactly one this platform registered: no prefix, no wildcard, no extra host.
        $redirect = $query['redirect_uri'] ?? null;

        if (! is_string($redirect) || ! in_array($redirect, array_map('strval', (array) $platform->redirect_uris), true)) {
            return $this->reject('redirect_uri_not_registered');
        }

        return $platform;
    }

    private function reject(string $reason): null
    {
        // Reason code only: no URL, client id or state ever goes to the log from here.
        Log::info('sso.pending_ignored', ['reason' => $reason]);

        return null;
    }
}
