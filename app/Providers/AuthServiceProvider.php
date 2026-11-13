<?php

namespace App\Providers;

use App\Models\Passport\Client;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\EcosystemHandoff;
use App\Support\Input;
use App\Support\SuspensionAwareAccessTokenRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        // R-20: the OAuth surface is registered by hand in routes/oauth.php (code + PKCE flow only).
        Passport::ignoreRoutes();

        // The token endpoint issues nothing for a suspended account (code exchange or refresh).
        $this->app->bind(AccessTokenRepository::class, SuspensionAwareAccessTokenRepository::class);
    }

    public function boot()
    {
        $this->registerPolicies();

        // A launch code is stored as a personal access token, but it is not a login. It may only be redeemed, once, by
        // the platform it names (EcosystemRedeemController reads it by value). Presented as "Authorization: Bearer" to
        // InfoDot's own pages or API it is refused, so holding a code never amounts to holding the person's account.
        // An API token of a suspended account is refused too (the owner is the row Sanctum loads next anyway).
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $valid) => $valid
                && ! str_starts_with((string) $token->name, EcosystemHandoff::TOKEN_PREFIX)
                && ! ($token->tokenable instanceof User && $token->tokenable->isSuspended())
        );

        // R-20: InfoDot is the ecosystem's OpenID Provider (see docs/programme/R20_SSO_CENTRAL_IDENTITY.md).
        Passport::useClientModel(Client::class);
        Passport::authorizationView('auth.oauth.authorize');
        Passport::tokensCan(config('openid.passport.tokens_can'));

        // A relying party only needs the token exchange at sign-in; everything after that lives in
        // its own local session. Short access tokens, shorter-than-default refresh tokens.
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDay());

        RateLimiter::for('oauth-token', function (Request $request) {
            return [
                Limit::perMinute(60)->by('oauth-token:'.$request->ip()),
                Limit::perMinute(120)->by('oauth-token-client:'.Input::text($request->input('client_id'))),
            ];
        });
    }
}
