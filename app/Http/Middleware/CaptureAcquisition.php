<?php

namespace App\Http\Middleware;

use App\Services\Growth\Acquisition;
use App\Support\CookieConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-59 Phase 1A: on a signed-out visitor's page view, keeps the first arriving visit that carries a referral code, a campaign tag or a
 * search or social referrer in one first-party cookie (see config/acquisition.php). A plain visit, a form post, a script request and a
 * signed-in person set nothing, and a visit already kept is never replaced. R-71: the cookie is only set once the visitor has accepted it
 * in the cookie banner; before that the arrival waits in the session, and after Decline nothing is kept.
 */
class CaptureAcquisition
{
    public function __construct(private readonly Acquisition $acquisition) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax() || $request->user() !== null || ! $response->isSuccessful() && ! $response->isRedirection()) {
            return $response;
        }

        if ($this->acquisition->fromCookie($request) !== null || ($arrival = $this->acquisition->fromArrival($request)) === null) {
            return $response;
        }

        // R-71: the cookie is optional, so it waits for the visitor's choice in the cookie banner.
        $choice = CookieConsent::choice($request);

        if ($choice === CookieConsent::DECLINED) {
            return $response;
        }

        if ($choice === null) {
            // Not chosen yet: the first tagged arrival waits in this visit's own session. Accept turns it into the cookie; Decline discards it.
            if ($request->hasSession() && ! $request->session()->has((string) config('acquisition.pending_key'))) {
                $request->session()->put((string) config('acquisition.pending_key'), $arrival);
            }

            return $response;
        }

        $response->headers->setCookie($this->acquisition->cookieFor($arrival, $request->isSecure()));

        return $response;
    }
}
