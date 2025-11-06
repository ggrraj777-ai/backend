<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DriverAccessRulesController;

/*
|--------------------------------------------------------------------------
| Admin - Driver Access Rules Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    
    Route::group(['prefix' => 'driver-access', 'as' => 'driver-access.'], function () {
        Route::controller(DriverAccessRulesController::class)->group(function () {
            
            // Dashboard
            Route::get('/', 'dashboard')->name('dashboard');
            
            // Fee Configurations
            Route::get('fee-configurations', 'feeConfigurations')->name('fee-configurations');
            Route::put('fee-configurations/{id}', 'updateConfiguration')->name('update-configuration');
            
            // Daily Activities
            Route::get('daily-activities', 'dailyActivities')->name('daily-activities');
            Route::post('process-fees', 'processFeesManually')->name('process-fees');
            Route::get('export-activities', 'exportActivities')->name('export-activities');
            
            // Driver Statistics
            Route::get('driver-statistics/{driverId}', 'driverStatistics')->name('driver-statistics');
        });
    });
});

