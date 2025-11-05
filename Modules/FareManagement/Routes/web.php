<?php

use Illuminate\Support\Facades\Route;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\PlatformChargeController;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\TripFareController;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\ParcelFareController;
use Modules\FareManagement\Http\Controllers\Web\New\Admin\TieredFareController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    
    // Trip Fare Routes
    Route::group(['prefix' => 'fare', 'as' => 'fare.'], function () {
        Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
            Route::get('/', [TripFareController::class, 'index'])->name('index');
            Route::get('create/{zone_id}', [TripFareController::class, 'create'])->name('create');
            Route::post('store', [TripFareController::class, 'store'])->name('store');
        });

        Route::group(['prefix' => 'parcel', 'as' => 'parcel.'], function () {
            Route::get('/', [ParcelFareController::class, 'index'])->name('index');
            Route::get('create/{zone_id}', [ParcelFareController::class, 'create'])->name('create');
            Route::post('store', [ParcelFareController::class, 'store'])->name('store');
        });
    });

    // Platform Charges Routes
    Route::group(['prefix' => 'platform', 'as' => 'platform.'], function () {
        Route::get('charges', [PlatformChargeController::class, 'index'])->name('index');
        Route::put('charges/update', [PlatformChargeController::class, 'update'])->name('update');
        Route::get('statistics', [PlatformChargeController::class, 'statistics'])->name('statistics');
    });

    // Tiered Fare Routes
    Route::group(['prefix' => 'tiered', 'as' => 'tiered.'], function () {
        Route::get('/', [TieredFareController::class, 'index'])->name('index');
        Route::put('update', [TieredFareController::class, 'update'])->name('update');
        Route::post('preview', [TieredFareController::class, 'preview'])->name('preview');
    });
});
