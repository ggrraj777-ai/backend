<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DriverAccessController;

/*
|--------------------------------------------------------------------------
| Driver Access Rules API Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
    
    // Public routes - Fee configurations
    Route::get('driver/access/fee-configurations', [DriverAccessController::class, 'getFeeConfigurations']);

    // Protected routes - Require authentication
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'driver/access'], function () {
        
        // Driver status and checks
        Route::get('status', [DriverAccessController::class, 'getTodayStatus']);
        Route::get('can-accept-trips', [DriverAccessController::class, 'canAcceptTrips']);
        Route::get('statistics', [DriverAccessController::class, 'getStatistics']);
        
        // Trip recording (internal use - called by trip completion webhook)
        Route::post('record-trip-complete', [DriverAccessController::class, 'recordTripComplete']);
    });
});

