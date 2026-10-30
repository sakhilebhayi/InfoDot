<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Signs a person in from a handoff token minted by a platform of the ecosystem.
 *
 * The token travels in the POST body of a form posted from a platform's subdomain, never in a URL. A GET with
 * ?token= is still understood for links minted before this change, but nothing here puts a token in a URL, and
 * a refused sign-in lands on the plain login page: the credential is not shown, echoed or kept anywhere.
 */
class EcosystemAuthController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        // Login CSRF: a form on another site could sign a visitor in as someone else. A browser names the
        // page that posted a cross-site form in Origin, so only this site and its platforms' subdomains pass.
        if ($request->isMethod('post') && ! $this->postedFromOurDomain($request)) {
            return $this->refused();
        }

        // Only a POST signs anyone in. A GET could be a link an attacker sent a victim ("login CSRF": the victim is signed in as the attacker),
        // so it is refused like any bad token and the person lands on the plain sign-in page.
        if (! $request->isMethod('post')) {
            return $this->refused();
        }

        $token = $request->input('token');

        $accessToken = is_string($token) && $token !== '' ? PersonalAccessToken::findToken($token) : null;

        if (
            ! $accessToken
            || ! $accessToken->can('ecosystem:read')
            // A token minted for one satellite is for that satellite's redeem call only. Accepted here it would let a compromised satellite
            // replay a person's launch token against InfoDot and get a full session, without two-factor.
            || $accessToken->name !== EcosystemHandoff::tokenName(null)
            || ($accessToken->expires_at && $accessToken->expires_at->isPast())
        ) {
            return $this->refused();
        }

        /** @var User $user */
        $user = $accessToken->tokenable;
        $accessToken->delete();

        Auth::login($user);

        return redirect()->route('solutions');
    }

    private function postedFromOurDomain(Request $request): bool
    {
        $origin = (string) $request->headers->get('Origin', '');
        $host = $origin !== '' && $origin !== 'null'
            ? parse_url($origin, PHP_URL_HOST)
            : parse_url((string) $request->headers->get('Referer', ''), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return in_array($request->headers->get('Sec-Fetch-Site'), ['same-origin', 'same-site'], true);
        }

        $host = strtolower($host);
        $self = strtolower($request->getHost());

        return $host === $self || str_ends_with($host, '.'.$self);
    }

    private function refused(): RedirectResponse
    {
        return redirect('/login', 303)->with('status', 'We could not sign you in from that link. Please sign in below.');
    }
}
