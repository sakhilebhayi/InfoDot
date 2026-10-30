<?php

namespace Dot\SsoClient;

use Dot\SsoClient\Http\Middleware\EnforceSsoRevocation;
use Dot\SsoClient\Http\Middleware\InterceptLocalAuth;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Dot ecosystem SSO, relying-party side (R-20 §16). The platform keeps its own domain, session,
 * users table and authorisation; only the question "who is this person?" is answered by InfoDot,
 * through the standard OIDC authorization-code flow with PKCE.
 *
 * Everything is gated on DOTSSO_ENABLED so a platform can merge this package and switch over (or
 * back) purely through its host .env — the controlled transition R-20 §19 requires.
 */
class DotSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dotsso.php', 'dotsso');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/dotsso.php' => config_path('dotsso.php')], 'dotsso-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! config('dotsso.enabled')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Local password forms stop being an entry point the moment central auth is on (§6), and a
        // centrally revoked identity stops being honoured by still-open local sessions (§10).
        $router->prependMiddlewareToGroup('web', InterceptLocalAuth::class);
        $router->pushMiddlewareToGroup('web', EnforceSsoRevocation::class);
    }
}
