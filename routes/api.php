<?php

use App\Http\Controllers\Api\CompanyPlatformsController;
use App\Http\Controllers\Api\EcosystemRedeemController;
use App\Http\Controllers\Api\FinancePlatformPlansController;
use App\Http\Controllers\Api\KnowledgeCandidatesController;
use App\Http\Controllers\Api\PlatformSubscriptionEventsController;
use App\Http\Controllers\Api\ResourceRegistryController;
use App\Http\Controllers\Api\SeminarRoomWebhookController;
use App\Http\Controllers\Api\SeminarTicketEventsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
});

// Called server to server by a Dot platform with its own database; authenticated by
// that platform's secret, not by a user session. See EcosystemRedeemController.
Route::post('/ecosystem/redeem', EcosystemRedeemController::class)
    ->middleware('throttle:ecosystem-redeem')
    ->name('api.ecosystem.redeem');

// R-50: what a company holds, for the platform that asks (its own secret, like the redeem call), under its own rate limit per calling
// platform so it never shares a bucket with sign-in redemption. See CompanyPlatformsController and the `platform-companies` limiter.
Route::get('/ecosystem/companies/{uuid}/platforms', CompanyPlatformsController::class)
    // The `api` group also applies a 60 a minute limit per address to every route here, which the redeem call shares: left on, it
    // would be a second, shared bucket in front of this one. This route answers to its own limiter only.
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:platform-companies')
    ->name('api.ecosystem.companies.platforms');

// A platform registers (and removes) pointers to the objects it owns. Authenticated by that
// platform's secret. See ResourceRegistryController.
Route::post('/ecosystem/resources', [ResourceRegistryController::class, 'store'])
    ->middleware('throttle:ecosystem-redeem')
    ->name('api.ecosystem.resources.store');
Route::delete('/ecosystem/resources/{objectUuid}', [ResourceRegistryController::class, 'destroy'])
    ->middleware('throttle:ecosystem-redeem')
    ->name('api.ecosystem.resources.destroy');

// Dot.Brain pulls the candidates for the Verified library. Authenticated by an HMAC signature made with the Brain secret
// (fails closed while the secret is unset); the rate limit runs first so guessing is throttled too. See KnowledgeCandidatesController.
Route::get('/knowledge/candidates', KnowledgeCandidatesController::class)
    ->middleware(['throttle:knowledge-candidates', 'knowledge.signed'])
    ->name('api.knowledge.candidates');

// Dot.Finance reports a paid or refunded seminar ticket. Signed with the callback secret; see SeminarTicketEventsController.
Route::post('/finance/ticket-events', SeminarTicketEventsController::class)
    ->middleware('throttle:finance-callbacks')
    ->name('api.finance.ticket-events');

// R-50: Dot.Finance reports that a company's platform subscription was paid, renewed, failed, refunded or cancelled. Signed with
// its own secret and refused entirely while that secret is unset; see PlatformSubscriptionEventsController.
Route::post('/finance/platform-subscription-events', PlatformSubscriptionEventsController::class)
    ->middleware('throttle:finance-callbacks')
    ->name('api.finance.platform-subscription-events');

// R-50: Dot.Finance reads the approved plans and bundles it may sell. Same signature and secret as the confirmations above (a GET signs the
// empty body); refused entirely while the secret is unset. See FinancePlatformPlansController.
Route::get('/finance/platform-plans', FinancePlatformPlansController::class)
    ->middleware('throttle:finance-callbacks')
    ->name('api.finance.platform-plans');

// LiveKit tells InfoDot who connected to a seminar room. Signed with the LiveKit API secret; see SeminarRoomWebhookController.
Route::post('/seminars/livekit-webhook', SeminarRoomWebhookController::class)
    ->middleware('throttle:livekit-webhook')
    ->name('api.seminars.livekit-webhook');
