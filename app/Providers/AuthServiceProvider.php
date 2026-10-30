<?php

namespace App\Providers;

use App\Models\Passport\Client;
use App\Models\Team;
use App\Policies\TeamPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;

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
    }

    public function boot()
    {
        $this->registerPolicies();

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
                Limit::perMinute(120)->by('oauth-token-client:'.(string) $request->input('client_id')),
            ];
        });
    }
}
