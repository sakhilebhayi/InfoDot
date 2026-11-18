<?php

namespace App\Http\Responses;

use App\Services\Sso\PlatformContext;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

/**
 * R-54: once the address is confirmed, a sign-in that a platform started continues where it stopped. The pending
 * address is the validated one (our own authorize endpoint, a registered client); with none, the ordinary landing.
 */
class VerifiedEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $pending = app(PlatformContext::class)->pendingUrl();

        return $pending !== null
            ? redirect($pending)
            : redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
