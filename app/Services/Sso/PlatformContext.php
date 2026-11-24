<?php

namespace App\Services\Sso;

use App\Http\Middleware\Oidc\EnforceOidcRules;
use App\Models\DotPlatform;
use App\Support\Branding\PlatformBrandingView;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /** Each sign-in started from a platform gets its own flow id. It is an opaque key into the session, never a platform name. */
    public const SESSION_FLOWS = 'sso.flows';

    public const FLOW_PARAM = 'f';

    private const MAX_FLOWS = 5;

    private const ENTERED = 'sso.entered';

    private const TOKEN = 'sso.flow_token';

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
            $this->request()->attributes->set(self::TOKEN, $this->startFlow($url));
            $this->request()->attributes->remove(self::CACHE);
        }
    }

    /** The flow id for the authorize request being handled right now, for the redirect to the way-in page. */
    public function currentFlowToken(): ?string
    {
        $token = $this->request()->attributes->get(self::TOKEN);

        return is_string($token) ? $token : null;
    }

    /** The flow id the sign-in, registration or password page in front of this person was opened with, if it is a live one. */
    public function enteredFlowToken(): ?string
    {
        $entered = $this->request()->attributes->get(self::ENTERED);

        return is_array($entered) ? $entered['token'] : null;
    }

    /**
     * The way-in pages (sign-in, registration, password reset) take their platform from the flow id in their own address, and from
     * nothing else. A page opened without one is InfoDot's own, whatever else this browser has started: the pending request is
     * dropped (other tabs keep their own flow ids and can still finish theirs). A page opened with a live one restores that request.
     */
    public function enter(): void
    {
        $request = $this->request();
        $raw = $request->input(self::FLOW_PARAM);
        $token = is_string($raw) && preg_match('/^[A-Za-z0-9]{20}$/', $raw) === 1 ? $raw : null;
        $flow = $token !== null ? ($this->liveFlows()[$token] ?? null) : null;

        if ($flow !== null && $this->judge($flow['url']) !== null) {
            $request->session()->put(self::SESSION_KEY, $flow['url']);
            $request->session()->put(self::SESSION_AT_KEY, time());
            $request->session()->put('url.intended', $flow['url']);
            $request->attributes->set(self::ENTERED, ['token' => $token, 'url' => $flow['url']]);
        } else {
            $this->forget();
            $request->attributes->set(self::ENTERED, ['token' => null, 'url' => null]);
        }

        $request->attributes->remove(self::CACHE);
    }

    /** @return array<string, array{url: string, at: int}> */
    private function liveFlows(): array
    {
        $flows = (array) $this->request()->session()->get(self::SESSION_FLOWS, []);

        return array_filter($flows, fn ($f) => is_array($f) && is_string($f['url'] ?? null) && is_int($f['at'] ?? null) && (time() - $f['at']) <= self::FRESH_FOR_SECONDS);
    }

    private function startFlow(string $url): string
    {
        $flows = $this->liveFlows();

        foreach ($flows as $token => $flow) {
            if ($flow['url'] === $url) {
                $flows[$token]['at'] = time();
                $this->request()->session()->put(self::SESSION_FLOWS, $flows);

                return (string) $token;
            }
        }

        $token = Str::random(20);
        $flows[$token] = ['url' => $url, 'at' => time()];
        uasort($flows, fn ($a, $b) => $b['at'] <=> $a['at']);
        $this->request()->session()->put(self::SESSION_FLOWS, array_slice($flows, 0, self::MAX_FLOWS, true));

        return $token;
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

        // On the way-in pages the address decides (enter() ran first): only a live flow id names a platform.
        if (is_array($entered = $request->attributes->get(self::ENTERED))) {
            if ($entered['url'] !== null && ($platform = $this->judge($entered['url'])) !== null) {
                $result = ['platform' => $platform, 'url' => $entered['url']];
            }

            $request->attributes->set(self::CACHE, $result);

            return $result;
        }

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
