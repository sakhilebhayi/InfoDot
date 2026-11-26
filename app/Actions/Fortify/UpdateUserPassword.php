<?php

namespace App\Actions\Fortify;

use App\Services\Notifications\AccountMail;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  mixed  $user
     * @return void
     */
    public function update($user, array $input)
    {
        // R-72 (S-05): five wrong current passwords per account in ten minutes, however the form is sent (page or settings dialog).
        $key = 'update-password:'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'current_password' => 'Too many wrong passwords. Wait '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minutes and try again.',
            ])->errorBag('updatePassword');
        }

        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($user, $input) {
            if (! isset($input['current_password']) || ! Hash::check($input['current_password'], $user->password)) {
                RateLimiter::hit('update-password:'.$user->getAuthIdentifier(), 600);
                $validator->errors()->add('current_password', __('The provided password does not match your current password.'));
            }
        })->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        app(AccountMail::class)->passwordChanged($user);

        // A changed password ends the person's sessions on the Dot platforms and revokes the provider tokens, as a
        // reset already does. The calls to the platforms run after the response is sent so the form stays fast.
        dispatch(function () use ($user) {
            app(BackchannelLogoutService::class)->propagate($user);
        })->afterResponse();
    }
}
