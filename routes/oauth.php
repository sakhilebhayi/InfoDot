<?php

use App\Http\Controllers\Oidc\DiscoveryController;
use App\Http\Controllers\Oidc\EndSessionController;
use App\Http\Controllers\Oidc\UserinfoController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
 * R-20 §4: the identity provider's OAuth surface, registered by hand (Passport::ignoreRoutes) so
 * that ONLY the flows the ecosystem uses exist — authorization code + PKCE and its token exchange.
 * Device flow, the transient-token refresh and Passport's JSON management API are deliberately not
 * routed. Route names stay Passport's own so the package's controllers and views keep working.
 *
 * `auth` + `verified` in front of /oauth/authorize is the central login/registration experience:
 * a signed-out visitor is sent to InfoDot's login (with register one click away) and returns to the
 * authorize URL afterwards; an unverified account must verify email before any platform sign-in.
 */
Route::group(['as' => 'passport.', 'prefix' => 'oauth'], function () {
    Route::post('/token', [AccessTokenController::class, 'issueToken'])
        ->middleware(['throttle:oauth-token', 'oidc.enforce'])
        ->name('token');

    Route::get('/authorize', [AuthorizationController::class, 'authorize'])
        ->middleware(['web', 'oidc.enforce', 'auth', 'verified'])
        ->name('authorizations.authorize');

    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])
            ->name('authorizations.approve');

        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])
            ->name('authorizations.deny');
    });
});

// OIDC metadata and session endpoints (the package serves /oauth/jwks; these are ours).
Route::get('/.well-known/openid-configuration', DiscoveryController::class)
    ->name('openid.discovery');

Route::get('/oauth/userinfo', UserinfoController::class)
    ->middleware('auth:oidc')
    ->name('openid.userinfo');

Route::match(['get', 'post'], '/oauth/end-session', EndSessionController::class)
    ->middleware('web')
    ->name('openid.end_session_endpoint');
