<?php

namespace App\Http\Middleware;

use App\Services\Sso\PlatformContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-54: sits in front of /oauth/authorize (after the house rules). While the person still has to sign in, register,
 * verify or set up a business, the request is kept in their session so the pages know which platform they are
 * heading for and every step can continue it. It is forgotten as soon as the endpoint answers with a redirect away
 * from InfoDot (the code or an error going back to the platform).
 */
class RememberPendingAuthorize
{
    public function __construct(private readonly PlatformContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $this->context->remember($request->fullUrl());
        }

        $response = $next($request);

        if ($response->isRedirection()) {
            $host = parse_url((string) $response->headers->get('Location'), PHP_URL_HOST);

            if (is_string($host) && strcasecmp($host, $request->getHost()) !== 0) {
                $this->context->forget();
            }
        }

        return $response;
    }
}
