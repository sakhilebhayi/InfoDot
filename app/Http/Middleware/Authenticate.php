<?php

namespace App\Http\Middleware;

use App\Services\Sso\PlatformContext;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            // R-54: a platform's "Create account" sends the standard OIDC value prompt=create (OpenID Connect
            // Initiating User Registration). It only chooses which of OUR two pages opens first; it is not an address.
            // The sign-in a platform started gets its own flow id (made just before this, by the authorize endpoint's first step); the
            // pages brand themselves from that id in their address and from nothing else.
            $flow = $request->is('oauth/authorize') ? app(PlatformContext::class)->currentFlowToken() : null;
            $params = $flow !== null ? [PlatformContext::FLOW_PARAM => $flow] : [];

            if ($request->is('oauth/authorize') && $request->query('prompt') === 'create') {
                return route('register', $params);
            }

            return route('login', $params);
        }

        return null;
    }
}
