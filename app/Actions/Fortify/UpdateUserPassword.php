<?php

namespace App\Actions\Fortify;

use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($user, $input) {
            if (! isset($input['current_password']) || ! Hash::check($input['current_password'], $user->password)) {
                $validator->errors()->add('current_password', __('The provided password does not match your current password.'));
            }
        })->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // A changed password ends the person's sessions on the Dot platforms and revokes the provider tokens, as a
        // reset already does. The calls to the platforms run after the response is sent so the form stays fast.
        dispatch(function () use ($user) {
            app(BackchannelLogoutService::class)->propagate($user);
        })->afterResponse();
    }
}
