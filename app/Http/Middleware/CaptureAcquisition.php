<?php

namespace App\Http\Middleware;

use App\Services\Growth\Acquisition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-59 Phase 1A: on a signed-out visitor's page view, keeps the first arriving visit that carries a referral code, a campaign tag or a
 * search or social referrer in one first-party cookie (see config/acquisition.php). A plain visit, a form post, a script request and a
 * signed-in person set nothing, and a visit already kept is never replaced.
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

        $response->headers->setCookie(cookie(
            (string) config('acquisition.cookie'),
            json_encode($arrival, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int) config('acquisition.days') * 1440,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        return $response;
    }
}
