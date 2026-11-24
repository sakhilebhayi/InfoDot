<?php

namespace App\Http\Middleware;

use App\Services\Sso\PlatformContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-54: the way-in pages (sign-in, registration, password reset) wear a platform's branding only when their own address
 * carries a live flow id for it. Opened any other way (typed, bookmarked, a link from InfoDot, another tab) they are
 * InfoDot's own page, and signing in on them goes to InfoDot, never on to a platform that was started earlier.
 */
class EnterSignInFlow
{
    private const PAGES = ['login', 'register', 'password.request', 'password.email', 'password.reset', 'password.update'];

    public function __construct(private readonly PlatformContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->routeIs(self::PAGES)) {
            $this->context->enter();
        }

        return $next($request);
    }
}
