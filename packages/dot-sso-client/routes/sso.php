<?php

use Dot\SsoClient\Http\Controllers\BackchannelLogoutController;
use Dot\SsoClient\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/sso/redirect', [SsoController::class, 'redirect'])->name('dotsso.redirect');
    Route::get('/sso/callback', [SsoController::class, 'callback'])->name('dotsso.callback');
    Route::match(['get', 'post'], '/sso/logout', [SsoController::class, 'logout'])->name('dotsso.logout');
});

// Server-to-server from the identity provider (OIDC Back-Channel Logout 1.0): no session, no CSRF
// token — authenticity comes from the signed logout token it carries, which the controller verifies.
Route::post('/sso/backchannel-logout', BackchannelLogoutController::class)
    ->name('dotsso.backchannel');
