<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every response. A header the route already set (the platform launch page sets a stricter CSP and
 * X-Frame-Options: DENY) is left alone. The Content-Security-Policy is ENFORCED since R-18: it ran report-only from 2026-10-30 and the
 * production log recorded zero violations, so what it allows is what the pages really use. 'unsafe-inline'/'unsafe-eval' on script-src
 * are a documented requirement of Livewire/Alpine (they evaluate inline expressions); everything else is pinned to the origins in use
 * (cdnjs: Font Awesome on the guest layout; jsdelivr: ApexCharts on analytics; Google Fonts; Unsplash images on two legacy views).
 * SECURITY_CSP_REPORT_ONLY=true in .env is the kill-switch back to report-only without a deploy.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Seminar rooms need the host's camera and microphone and screen sharing from this origin; nothing else is used.
            'Permissions-Policy' => 'camera=(self), microphone=(self), display-capture=(self), geolocation=(), payment=(), usb=()',
        ];

        // Violations keep reporting to /csp-report in both modes, so a regression is seen in the log, not guessed.
        $policy = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
            ."img-src 'self' data: blob: https://images.unsplash.com https://source.unsplash.com; media-src 'self' blob:; "
            ."connect-src 'self' wss: https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https:; report-uri /csp-report";

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
