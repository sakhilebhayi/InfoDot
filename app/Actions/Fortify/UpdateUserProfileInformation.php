<?php

namespace App\Actions\Fortify;

use App\Notifications\EmailAddressChanged;
use App\Rules\DeliverableEmailDomain;
use App\Services\Profile\ProfilePhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  mixed  $user
     * @return void
     */
    public function update($user, array $input)
    {
        // R-72 (E-07): password resets go to the account's address, so taking it over must need the current password. Five wrong tries per
        // account in ten minutes, like changing the password.
        $changingEmail = ($input['email'] ?? null) !== $user->email;
        $key = 'email-change:'.$user->getAuthIdentifier();
        if ($changingEmail && RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'current_password' => 'Too many wrong passwords. Wait '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minutes and try again.',
            ])->errorBag('updateProfileInformation');
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => array_filter(['bail', 'required', ($input['email'] ?? null) !== $user->email ? new DeliverableEmailDomain : null, 'email', 'max:255', Rule::unique('users')->ignore($user->id)]),
            'photo' => ['nullable', 'image', 'mimes:'.ProfilePhoto::MIMES, 'max:'.ProfilePhoto::MAX_KILOBYTES],
            'current_password' => $changingEmail ? ['required', 'string'] : ['nullable'],
        ], [
            'current_password.required' => 'Enter your current password to change your email address.',
            'photo.image' => 'Choose a picture: JPG, PNG or WebP.',
            'photo.mimes' => 'Choose a picture: JPG, PNG or WebP.',
            'photo.max' => 'That picture is over 10 MB. Choose a smaller one.',
            'photo.uploaded' => 'The picture did not upload. Check your connection and try again.',
        ])->after(function ($validator) use ($user, $input, $changingEmail, $key) {
            if ($changingEmail && isset($input['current_password']) && ! Hash::check((string) $input['current_password'], (string) $user->password)) {
                RateLimiter::hit($key, 600);
                $validator->errors()->add('current_password', 'That is not your current password.');
            }
        })->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            app(ProfilePhoto::class)->store($user, $input['photo']);
        }

        $previousEmail = (string) $user->email;

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }

        if ($previousEmail !== '' && $previousEmail !== $input['email']) {
            $this->tellThePreviousAddress($user, $previousEmail);
        }
    }

    /**
     * The old address hears that the account's email changed, so a change the owner did not make is
     * noticed. The change is already saved; a mail server that is down is logged, never an error page.
     *
     * @param  mixed  $user
     */
    protected function tellThePreviousAddress($user, string $previousEmail): void
    {
        try {
            Notification::route('mail', $previousEmail)->notify(new EmailAddressChanged((string) $user->name));
        } catch (TransportExceptionInterface $e) {
            Log::error('profile.email_change_notice_failed', [
                'category' => 'mail',
                'user_id' => $user->getAuthIdentifier(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Update the given verified user's profile information. The new address is saved before the
     * verification email goes out, so a mail server that is down must not turn the saved change
     * into an error page: it is logged, and the "verify your email" page offers Resend.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function updateVerifiedUser($user, array $input)
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $e) {
            Log::error('profile.verification_mail_failed', [
                'category' => 'mail',
                'user_id' => $user->getAuthIdentifier(),
                'exception' => $e::class,
            ]);

            if (app()->bound('session') && request()->hasSession()) {
                session()->flash('verification_mail_failed', true);
            }
        }
    }
}
