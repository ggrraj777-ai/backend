<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\FareManagement\Http\Controllers\Api\V1\PlatformChargeController;
use Modules\FareManagement\Http\Controllers\Api\V1\TieredFareController;
use Modules\FareManagement\Http\Controllers\Api\Customer\TripFareController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
    
    // Public platform charges
    Route::get('platform/charges', [PlatformChargeController::class, 'index']);
    Route::get('platform/charges/{vehicleType}', [PlatformChargeController::class, 'show']);

    // Public tiered fare config
    Route::get('fare/tiered/config', [TieredFareController::class, 'getConfig']);
    Route::get('fare/tiered/config/{vehicleType}', [TieredFareController::class, 'getVehicleConfig']);
    Route::post('fare/calculate/tiered', [TieredFareController::class, 'calculateFare']);

    // Protected routes (require authentication)
    Route::group(['middleware' => ['auth:sanctum']], function () {
        
        // Driver routes
        Route::group(['prefix' => 'driver'], function () {
            Route::post('purchase-day-pass', [PlatformChargeController::class, 'purchaseDayPass']);
            Route::get('day-pass/status', [PlatformChargeController::class, 'checkDayPassStatus']);
            Route::get('bonus/progress', [PlatformChargeController::class, 'getBonusProgress']);
        });

        // Customer routes
        Route::group(['prefix' => 'customer'], function () {
            Route::get('cashback/history', [PlatformChargeController::class, 'getCashbackHistory']);
        });

        // Fare calculation routes
        Route::post('fare/calculate/complete', [TieredFareController::class, 'calculateCompleteFare']);
        Route::get('fare/breakdown/{tripId}', [TieredFareController::class, 'getTripFareBreakdown']);
    });
});

// Customer fare routes
Route::group(['prefix' => 'customer'], function (){
    Route::get('vehicle-category-list', [TripFareController::class, 'categoryFareList']);
});
