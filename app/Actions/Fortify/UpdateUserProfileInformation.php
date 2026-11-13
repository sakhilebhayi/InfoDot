<?php

namespace App\Actions\Fortify;

use App\Notifications\EmailAddressChanged;
use App\Services\Profile\ProfilePhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'image', 'mimes:'.ProfilePhoto::MIMES, 'max:'.ProfilePhoto::MAX_KILOBYTES],
        ], [
            'photo.image' => 'Choose a picture: JPG, PNG or WebP.',
            'photo.mimes' => 'Choose a picture: JPG, PNG or WebP.',
            'photo.max' => 'That picture is over 10 MB. Choose a smaller one.',
            'photo.uploaded' => 'The picture did not upload. Check your connection and try again.',
        ])->validateWithBag('updateProfileInformation');

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
                'reason' => mb_substr($e->getMessage(), 0, 300),
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
                'reason' => mb_substr($e->getMessage(), 0, 300),
            ]);

            if (app()->bound('session') && request()->hasSession()) {
                session()->flash('verification_mail_failed', true);
            }
        }
    }
}
