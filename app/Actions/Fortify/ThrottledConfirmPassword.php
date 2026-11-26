<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;

/**
 * R-72 (S-05): confirming the password (before two-factor settings and other protected steps) allows five wrong tries per account in
 * ten minutes, on the page and in the settings dialogs alike, so a stolen session cannot guess the password.
 */
class ThrottledConfirmPassword extends ConfirmPassword
{
    public const ATTEMPTS = 5;

    public function __invoke(StatefulGuard $guard, $user, ?string $password = null)
    {
        $key = 'confirm-password:'.$user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw ValidationException::withMessages([
                'password' => 'Too many wrong passwords. Wait '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minutes and try again.',
            ]);
        }

        $confirmed = parent::__invoke($guard, $user, $password);

        $confirmed ? RateLimiter::clear($key) : RateLimiter::hit($key, 600);

        return $confirmed;
    }
}
