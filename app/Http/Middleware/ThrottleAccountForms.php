<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's sign-up, forgot-password and reset-password POSTs have no limit of their own beyond a per-address one in the password broker.
 * Without this a script can create accounts and send reset mail to any number of different addresses. The limit is per source address and
 * per form; a person mistyping a few times never meets it.
 */
class ThrottleAccountForms
{
    private const FORMS = ['register' => 10, 'forgot-password' => 6, 'reset-password' => 10];

    public function handle(Request $request, Closure $next): Response
    {
        $form = trim($request->path(), '/');

        if ($request->isMethod('POST') && isset(self::FORMS[$form])) {
            $key = 'account-form:'.$form.':'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, self::FORMS[$form])) {
                return response('Too many attempts. Please wait a few minutes and try again.', 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
            }

            RateLimiter::hit($key, 600);
        }

        return $next($request);
    }
}
