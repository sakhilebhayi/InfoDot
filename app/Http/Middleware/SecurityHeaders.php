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
