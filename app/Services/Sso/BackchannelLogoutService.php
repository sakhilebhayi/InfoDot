<?php

namespace App\Services\Sso;

use App\Models\DotPlatform;
use App\Models\SsoPlatformSession;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

/**
 * OIDC Back-Channel Logout 1.0 (R-20 §10). When the central session ends — logout, password
 * reset/change, account deletion or suspension — every platform this user has signed into is told
 * server-to-server with a signed logout token, the user's provider-side tokens are revoked, and the
 * session registry rows are cleared. Logout tokens identify the subject (`sub`), so a platform ends
 * every local session for that identity; per-session `sid` logout is deliberately not offered.
 */
class BackchannelLogoutService
{
    /**
     * @param  string|null  $exceptClientId  the platform that initiated the logout (it is already
     *                                       logging itself out; it gets no token)
     */
    public function propagate(User $user, ?string $exceptClientId = null): void
    {
        $sessions = SsoPlatformSession::with('platform')->where('user_id', $user->id)->get();

        $targets = $sessions->pluck('platform')->filter(
            fn (?DotPlatform $p) => $p && $p->isActive() && $p->backchannel_logout_uri
                && $p->oauth_client_id !== $exceptClientId
        );

        foreach ($targets as $platform) {
            $this->send($user, $platform);
        }

        $this->revokeProviderTokens($user);

        SsoPlatformSession::where('user_id', $user->id)->delete();
    }

    private function send(User $user, DotPlatform $platform): void
    {
        try {
            $response = Http::timeout(config('sso.backchannel_timeout'))
                ->asForm()
                ->post($platform->backchannel_logout_uri, [
                    'logout_token' => $this->logoutToken($user, $platform),
                ]);

            Log::info('sso.backchannel_logout', [
                'platform' => $platform->slug,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('sso.backchannel_logout_failed', [
                'platform' => $platform->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** A logout token is a short-lived signed JWT; it is NOT an id_token and carries no nonce. */
    public function logoutToken(User $user, DotPlatform $platform): string
    {
        $key = InMemory::file(Passport::keyPath('oauth-private.key'));
        $config = Configuration::forSymmetricSigner(new Sha256, $key);

        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->issuedBy(rtrim(config('app.url'), '/'))
            ->permittedFor($platform->oauth_client_id)
            ->issuedAt($now)
            ->expiresAt($now->modify('+'.config('sso.logout_token_ttl').' seconds'))
            ->identifiedBy((string) Str::uuid())
            ->relatedTo((string) $user->uuid)
            ->withClaim('events', ['http://schemas.openid.net/event/backchannel-logout' => (object) []]);

        if ($kid = config('openid.token_headers.kid')) {
            $builder = $builder->withHeader('kid', $kid);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /** Revoke every provider-side credential the user holds: access tokens, their refresh tokens, pending codes. */
    private function revokeProviderTokens(User $user): void
    {
        $tokenIds = Token::where('user_id', $user->id)->pluck('id');

        if ($tokenIds->isNotEmpty()) {
            RefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            Token::whereIn('id', $tokenIds)->update(['revoked' => true]);
        }

        AuthCode::where('user_id', $user->id)->update(['revoked' => true]);
    }
}
