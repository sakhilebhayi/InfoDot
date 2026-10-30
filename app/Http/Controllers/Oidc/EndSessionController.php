<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\DotPlatform;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * Without a valid hint the central session still ends, but no redirect parameter is honoured.
 */
class EndSessionController extends Controller
{
    public function __invoke(Request $request, BackchannelLogoutService $backchannel): RedirectResponse
    {
        $platform = $this->platformFromHint((string) $request->input('id_token_hint', ''));

        $user = Auth::user();

        if ($user) {
            $backchannel->propagate($user, $platform?->oauth_client_id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::info('sso.central_logout', ['via' => $platform->slug ?? 'direct']);
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

    /** Identify the initiating platform from the id_token_hint: signature and audience only (it may be expired). */
    private function platformFromHint(string $hint): ?DotPlatform
    {
        if ($hint === '') {
            return null;
        }

        try {
            $token = (new Parser(new JoseEncoder))->parse($hint);

            if (! $token instanceof Plain) {
                return null;
            }

            $key = InMemory::file(Passport::keyPath('oauth-public.key'));

            if (! (new Validator)->validate($token, new SignedWith(new Sha256, $key))) {
                return null;
            }

            $audience = $token->claims()->get('aud', []);

            return DotPlatform::findByClientId(is_array($audience) ? ($audience[0] ?? null) : $audience);
        } catch (\Throwable) {
            return null;
        }
    }
}
