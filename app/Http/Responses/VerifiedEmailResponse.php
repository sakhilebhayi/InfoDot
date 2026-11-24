<?php

namespace App\Http\Responses;

use App\Services\Sso\BusinessOnboarding;
use App\Services\Sso\PlatformContext;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

/**
 * R-54: once the address is confirmed, a sign-in that a platform started continues where it stopped. The pending
 * address is the validated one (our own authorize endpoint, a registered client). R-62: with none, a person who has no company yet
 * lands on company setup; anyone else gets the ordinary landing.
 */
class VerifiedEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $pending = app(PlatformContext::class)->pendingUrl();

        if ($pending !== null) {
            return redirect($pending);
        }

        // R-62: the first thing after confirming an address is setting up the company, because that is what a person comes to InfoDot to do.
        // Someone who already belongs to a company goes where they were headed, as before.
        $user = $request->user();
        if ($user !== null && app(BusinessOnboarding::class)->needsBusiness($user)) {
            return redirect()->route('onboarding.business', ['verified' => 1]);
        }

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
