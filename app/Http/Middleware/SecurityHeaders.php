<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every response. A header the route already set (the platform launch page sets a stricter CSP and
 * X-Frame-Options: DENY) is left alone. No Content-Security-Policy is added here: the pages rely on inline Alpine/Livewire scripts, and a
 * policy needs a reporting period before it can be enforced (docs/programme/enterprise/FINDINGS.md).
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

        if (config('security.csp_report_only')) {
            $headers['Content-Security-Policy-Report-Only'] = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
                ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
                ."img-src 'self' data: blob: https://www.gravatar.com https://images.unsplash.com https://source.unsplash.com; media-src 'self' blob:; "
                ."connect-src 'self' wss: https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https:; report-uri /csp-report";
        }

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
