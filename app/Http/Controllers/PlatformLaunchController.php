<?php

namespace App\Http\Controllers;

use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Opens a platform for the signed-in person.
 *
 * The sign-in credential for the destination is minted here, put in a form and POSTed straight to that
 * platform: it never appears in a URL (address bar, history, access logs, Referer), so a failed sign-in at the
 * other end cannot leave it on screen. The page is not cached, sends only the origin as Referer (the platform
 * checks it), and its content security policy lets the form go to the one destination and nowhere else.
 */
class PlatformLaunchController extends Controller
{
    public function __invoke(Request $request, string $platform): Response|RedirectResponse
    {
        $user = $request->user();

        // Bridge services are never opened by a person, even by a hand-made request.
        abort_if(Platforms::isInternal($platform), 404);

        // A platform with its own sign-in has no receiver for a token: send the person to its front door.
        if (EcosystemHandoff::hasNoReceiver($platform) && ($plain = EcosystemHandoff::plainUrl($platform)) !== null) {
            return redirect()->away($plain);
        }

        $redirect = $request->query('redirect');
        $form = EcosystemHandoff::launchForm($user, $platform, is_string($redirect) ? $redirect : null);

        if ($form === null) {
            return response()->view('resources.unavailable', ['platform' => config('ecosystem.platforms.'.$platform.'.name', $platform)], 503);
        }

        $nonce = Str::random(24);

        return response()
            ->view('launch', $form + ['nonce' => $nonce])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'origin')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'nonce-{$nonce}'; script-src 'nonce-{$nonce}'; form-action {$form['origin']}; base-uri 'none'; frame-ancestors 'none'");
    }
}
