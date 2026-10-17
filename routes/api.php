<?php

use App\Http\Controllers\Api\EcosystemRedeemController;
use App\Http\Controllers\Api\EcosystemTokenController;
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
