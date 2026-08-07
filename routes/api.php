<?php

use App\Http\Controllers\Api\EcosystemTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::post('/ecosystem/token', [EcosystemTokenController::class, 'issue'])
        ->name('api.ecosystem.token');
});
