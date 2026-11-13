<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * "Resend verification email". The host has no queue worker, so the mail goes out inside the request: when the mail
 * server refuses it the person goes back to the page and is told the email did not go, instead of an error page.
 */
class ResendVerificationEmailController extends EmailVerificationNotificationController
{
    public function store(Request $request)
    {
        try {
            return parent::store($request);
        } catch (TransportExceptionInterface $e) {
            Log::error('account.verification_mail_failed', [
                'category' => 'mail',
                'user_id' => $request->user()?->getAuthIdentifier(),
                'exception' => $e::class,
                'reason' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return $request->wantsJson()
                ? new JsonResponse(['message' => 'The verification email could not be sent just now. Please try again in a minute.'], 503)
                : back()->with('verification_mail_failed', true);
        }
    }
}
