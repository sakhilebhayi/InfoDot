<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends the verification email after sign-up. The host has no queue worker, so the mail goes out inside the
 * request, after the account is already saved. If the mail server refuses or is unreachable the account still
 * exists, so the request must not answer 500: the person is signed in, lands on "verify your email", is told the
 * email did not go, and can press Resend. The failure is logged for whoever runs the platform.
 */
class SendVerificationEmailSafely
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $e) {
            Log::error('registration.verification_mail_failed', [
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
