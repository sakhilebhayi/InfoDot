<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every response. A header the route already set (the platform launch page sets a
 * stricter CSP and X-Frame-Options: DENY) is left alone.
 *
 * The Content-Security-Policy is ENFORCED and, since R-19, nonce-based: a per-request nonce is registered with Vite
 * (so @vite tags and Livewire's assets carry it automatically — Livewire's FrontendAssets falls back to
 * Vite::cspNonce()) and the handful of hand-written inline scripts declare it. 'unsafe-inline' is gone from
 * script-src, script-src-attr is 'none' (native on*= handlers were converted to Alpine directives), object-src is
 * 'none'. 'unsafe-eval' REMAINS on script-src as a documented stack requirement: Livewire 3's bundled Alpine
 * evaluates its expressions with the Function constructor, and no supported CSP build exists for that pairing —
 * removing it means replacing the frontend stack (R-19 report). style-src keeps 'unsafe-inline' because the Yard
 * views use style attributes throughout; script injection, not style, is the attack surface that matters here.
 *
 * Violations report to /csp-report through both the legacy report-uri and the modern Reporting-Endpoints/report-to
 * pair. SECURITY_CSP_REPORT_ONLY=true in .env is the kill-switch back to report-only without a deploy.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Registered before the views render so every @vite tag, Livewire asset and nonce-marked inline script
        // carries the same per-request value the header announces.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Seminar rooms need the host's camera and microphone and screen sharing from this origin; nothing else is used.
            'Permissions-Policy' => 'camera=(self), microphone=(self), display-capture=(self), geolocation=(), payment=(), usb=()',
            // The modern reporting channel; report-to below names it, report-uri stays for older browsers.
            'Reporting-Endpoints' => 'csp="'.url('/csp-report').'"',
        ];

        $policy = "default-src 'self'; "
            ."script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
            ."script-src-attr 'none'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
            ."font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
            ."img-src 'self' data: blob: https://images.unsplash.com https://source.unsplash.com; media-src 'self' blob:; "
            // Local dev talks to Reverb over plaintext ws:; production websockets are wss: only.
            ."connect-src 'self' wss: https:".(app()->isLocal() ? ' ws:' : '').'; '
            ."frame-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; "
            ."form-action 'self' https:; report-uri /csp-report; report-to csp";

        $headers[config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy'] = $policy;

        if ($request->isSecure() && config('security.hsts_seconds', 0) > 0) {
            $headers['Strict-Transport-Security'] = 'max-age='.(int) config('security.hsts_seconds');
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
