<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Fortify's sign-up, forgot-password and reset-password POSTs have no limit of their own beyond a per-address one in the password broker.
 * Without this a script can create accounts and send reset mail to any number of different addresses. The limit is per source address and
 * per form; a person mistyping a few times never meets it.
 *
 * Sign-up allows thirty in ten minutes: a room of people joining on one Wi-Fi (a seminar, a demonstration) shares one address, and ten
 * was within their reach. Past the limit the answer is the ordinary 429 page (or JSON for a JSON caller) with Retry-After, not bare text.
 */
class ThrottleAccountForms
{
    private const FORMS = ['register' => 30, 'forgot-password' => 6, 'reset-password' => 10];

    /**
     * R-54: a second, per-address limit for the two forms that send mail or change a password. The per-source limit
     * above stops one machine; this one stops many machines (or one rotating its address) aimed at a single mailbox
     * or account. Counted in ten minutes, on the lower-cased address so `A@x.com` and `a@x.com` are one.
     */
    private const PER_EMAIL = ['forgot-password' => 5, 'reset-password' => 5];

    public function handle(Request $request, Closure $next): Response
    {
        $form = trim($request->path(), '/');

        if ($request->isMethod('POST') && isset(self::FORMS[$form])) {
            $key = 'account-form:'.$form.':'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, self::FORMS[$form])) {
                // The exception handler draws errors/429 for a browser and answers JSON to a JSON caller; both carry Retry-After.
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), 'Too many attempts. Please wait a few minutes and try again.');
            }

            RateLimiter::hit($key, 600);

            if (isset(self::PER_EMAIL[$form]) && is_string($email = $request->input('email')) && $email !== '') {
                $emailKey = 'account-form-email:'.$form.':'.sha1(mb_strtolower(trim($email)));

                if (RateLimiter::tooManyAttempts($emailKey, self::PER_EMAIL[$form])) {
                    throw new TooManyRequestsHttpException(RateLimiter::availableIn($emailKey), 'Too many attempts. Please wait a few minutes and try again.');
                }

                RateLimiter::hit($emailKey, 600);
            }
        }

        return $next($request);
    }
}
