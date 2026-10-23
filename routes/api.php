<?php

use App\Http\Controllers\Api\EcosystemRedeemController;
use App\Http\Controllers\Api\EcosystemTokenController;
use App\Http\Controllers\Api\KnowledgeCandidatesController;
use App\Http\Controllers\Api\ResourceRegistryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::post('/ecosystem/token', [EcosystemTokenController::class, 'issue'])
        ->name('api.ecosystem.token');
});

// Called server to server by a Dot platform with its own database; authenticated by
// that platform's secret, not by a user session. See EcosystemRedeemController.
Route::post('/ecosystem/redeem', EcosystemRedeemController::class)
    ->middleware('throttle:ecosystem-redeem')
    ->name('api.ecosystem.redeem');

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
