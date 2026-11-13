<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\DotPlatform;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

/**
 * OIDC RP-initiated logout (R-20 §10). A platform sends the user here with its id_token as the
 * hint; the central session ends, every other signed-in platform gets a back-channel logout, and
 * the user is returned ONLY to a post-logout URI that is exactly registered for that platform.
 *
 * Signing out here signs the person out everywhere, so it has to be their own request. The proof is
 * an id_token issued by InfoDot to the person who is signed in. Without it (a bare link, a hint
 * issued to somebody else) nothing ends: a confirmation page is shown and its button posts back
 * through the CSRF check. Without a valid hint no redirect parameter is honoured either way.
 */
class EndSessionController extends Controller
{
    public function __invoke(Request $request, BackchannelLogoutService $backchannel): RedirectResponse|Response
    {
        [$platform, $subject] = $this->readHint((string) $request->input('id_token_hint', ''));

        $user = Auth::user();

        if ($user) {
            $provenByHint = $subject !== null && hash_equals((string) $user->uuid, $subject);

            if (! $provenByHint && ! $request->isMethod('post')) {
                return response()
                    ->view('auth.end-session', [
                        'email' => $user->email,
                        'carry' => array_filter($request->only(['id_token_hint', 'post_logout_redirect_uri', 'state']), 'is_string'),
                    ])
                    ->header('Cache-Control', 'no-store');
            }

            $backchannel->propagate($user, $platform?->oauth_client_id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::info('sso.central_logout', ['via' => $platform->slug ?? 'direct', 'confirmed' => ! $provenByHint]);
        }

        $destination = (string) $request->input('post_logout_redirect_uri', '');

        if ($platform && $destination !== '' && $platform->allowsPostLogoutRedirect($destination)) {
            if ($state = $request->input('state')) {
                $destination .= (str_contains($destination, '?') ? '&' : '?').'state='.urlencode($state);
            }

            return redirect()->away($destination);
        }

        if ($destination !== '') {
            Log::info('sso.post_logout_redirect_rejected', ['platform' => $platform?->slug]);
        }

        return redirect('/');
    }

    /**
     * Read the id_token_hint: signature only (it may be expired). Gives the platform it was issued
     * to (by audience) and the person it was issued for (the subject), or nulls when it is not ours.
     *
     * @return array{0: ?DotPlatform, 1: ?string}
     */
    private function readHint(string $hint): array
    {
        if ($hint === '') {
            return [null, null];
        }

        try {
            $token = (new Parser(new JoseEncoder))->parse($hint);

            if (! $token instanceof Plain) {
                return [null, null];
            }

            $key = InMemory::file(Passport::keyPath('oauth-public.key'));

            if (! (new Validator)->validate($token, new SignedWith(new Sha256, $key))) {
                return [null, null];
            }

            $audience = $token->claims()->get('aud', []);
            $subject = $token->claims()->get('sub');

            return [
                DotPlatform::findByClientId(is_array($audience) ? ($audience[0] ?? null) : $audience),
                is_string($subject) && $subject !== '' ? $subject : null,
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
