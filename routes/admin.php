<?php

use App\Http\Controllers\Admin\Platforms\BundlesController;
use App\Http\Controllers\Admin\Platforms\CatalogueController;
use App\Http\Controllers\Admin\Platforms\CompaniesController;
use App\Http\Controllers\Admin\Platforms\InfrastructureController;
use App\Http\Controllers\Admin\Platforms\RecordsController;
use Illuminate\Support\Facades\Route;

/*
| The platform admin centre (R-50 section 9). Every route here is behind sign-in, a verified email address and the
| `administer-platforms` gate (the `platform.admin` middleware): a moderator, a company owner and a member all get 403.
| There is deliberately no route that grants or revokes the platform administrator role: that is done only from the
| command line on the host (`platform-admin:grant`).
*/

Route::middleware(['web', 'auth:sanctum', 'verified', 'platform.admin'])->prefix('/admin/platforms')->name('admin.platforms')->group(function () {
    Route::controller(CatalogueController::class)->group(function () {
        Route::get('/', 'index')->middleware('throttle:60,1,admin.platforms');
        Route::get('/catalogue/{entry:key}', 'show')->middleware('throttle:60,1,admin.platforms.view')->name('.platform');
        Route::post('/catalogue/{entry:key}', 'update')->middleware('throttle:30,1,admin.platforms.act')->name('.platform.update');
        Route::post('/catalogue/{entry:key}/plans', 'storePlan')->middleware('throttle:30,1,admin.platforms.act')->name('.plans.store');
        Route::get('/plans/{plan}', 'plan')->whereNumber('plan')->middleware('throttle:60,1,admin.platforms.view')->name('.plan');
        Route::post('/plans/{plan}', 'updatePlan')->whereNumber('plan')->middleware('throttle:30,1,admin.platforms.act')->name('.plan.update');
        Route::post('/plans/{plan}/{decision}', 'decidePlan')->whereNumber('plan')->whereIn('decision', ['approve', 'withdraw'])->middleware('throttle:30,1,admin.platforms.act')->name('.plan.decide');
    });

    Route::controller(BundlesController::class)->group(function () {
        Route::get('/bundles', 'index')->middleware('throttle:60,1,admin.platforms.view')->name('.bundles');
        Route::post('/bundles', 'store')->middleware('throttle:30,1,admin.platforms.act')->name('.bundles.store');
        Route::get('/bundles/{bundle}', 'show')->whereNumber('bundle')->middleware('throttle:60,1,admin.platforms.view')->name('.bundle');
        Route::post('/bundles/{bundle}', 'update')->whereNumber('bundle')->middleware('throttle:30,1,admin.platforms.act')->name('.bundle.update');
        Route::post('/bundles/{bundle}/{decision}', 'decide')->whereNumber('bundle')->whereIn('decision', ['approve', 'withdraw'])->middleware('throttle:30,1,admin.platforms.act')->name('.bundle.decide');
    });

    Route::controller(CompaniesController::class)->group(function () {
        Route::get('/companies', 'index')->middleware('throttle:60,1,admin.platforms.view')->name('.companies');
        Route::get('/companies/{team:uuid}', 'show')->whereUuid('team')->middleware('throttle:60,1,admin.platforms.view')->name('.company');
        Route::post('/companies/{team:uuid}/{action}', 'act')->whereUuid('team')->whereIn('action', ['grant', 'trial', 'suspend', 'restore', 'cancel', 'access'])->middleware('throttle:30,1,admin.platforms.act')->name('.company.act');
    });

    // What InfoDot has on record about each platform: read only, and never shown to a business.
    Route::get('/infrastructure', InfrastructureController::class)->middleware('throttle:60,1,admin.platforms.view')->name('.infrastructure');

    Route::controller(RecordsController::class)->group(function () {
        Route::get('/outbox', 'outbox')->middleware('throttle:60,1,admin.platforms.view')->name('.outbox');
        Route::post('/outbox/{row}/retry', 'retry')->whereNumber('row')->middleware('throttle:30,1,admin.platforms.act')->name('.outbox.retry');
        Route::get('/payments', 'payments')->middleware('throttle:60,1,admin.platforms.view')->name('.payments');
        Route::get('/audit', 'audit')->middleware('throttle:60,1,admin.platforms.view')->name('.audit');
    });
});
