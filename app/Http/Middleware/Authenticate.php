<?php

namespace App\Http\Middleware;

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
            if ($request->is('oauth/authorize') && $request->query('prompt') === 'create') {
                return route('register');
            }

            return route('login');
        }

        return null;
    }
}
