<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One address per page (R-17 §13). Jetstream auto-registers /terms-of-service and /privacy-policy with the same
 * content as our canonical /terms and /policy; its routes are registered before the application's, so a plain route
 * redirect never matches. This runs before routing and sends the duplicates to the canonical pages instead.
 */
class CanonicalRedirects
{
    private const REDIRECTS = [
        'terms-of-service' => '/terms',
        'privacy-policy' => '/policy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && isset(self::REDIRECTS[$request->path()])) {
            return redirect(self::REDIRECTS[$request->path()], 301);
        }

        return $next($request);
    }
}
