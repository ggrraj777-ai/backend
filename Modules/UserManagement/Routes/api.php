<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\AddressController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\CustomerController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\CustomerLevelController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\LoyaltyPointController;
use Modules\UserManagement\Http\Controllers\Api\New\Customer\WalletTransferController;

use Modules\UserManagement\Http\Controllers\Api\New\Driver\TimeTrackController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverActivityController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverLevelController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\LoyaltyPointController as DriverLoyaltyPointController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\WithdrawController;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\WithdrawMethodInfoController;
use Modules\UserManagement\Http\Controllers\Api\New\User\LocationController;
use Modules\UserManagement\Http\Controllers\Api\New\AppNotificationController;

Route::group(['prefix' => 'customer'], function () {
    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::group(['prefix' => 'loyalty-points'], function () {
            Route::controller(LoyaltyPointController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('convert', 'convert');
            });
        });
        Route::group(['prefix' => 'level'], function () {
            Route::get('/', [CustomerLevelController::class, 'getCustomerLevelWithTrip']);
        });
        Route::get('notification-list', [AppNotificationController::class, 'index']);
        Route::controller(CustomerController::class)->group(function () {
            Route::put('update/profile', 'updateProfile');
            Route::get('info', 'profileInfo');
            Route::post('get-data', 'getCustomer');
            Route::post('external-update-data', 'externalUpdateCustomer')->withoutMiddleware('auth:api');
            Route::post('applied-coupon', 'applyCoupon');
            Route::post('change-language', 'changeLanguage');
            Route::get('referral-details', 'referralDetails');
        });

        //old controller
        Route::group(['prefix' => 'address'], function () {
            Route::controller(AddressController::class)->group(function () {
                Route::get('all-address', 'getAddresses');
                Route::post('add', 'store');
                Route::get('edit/{id}', 'edit');
                Route::put('update', 'update');
                Route::delete('delete', 'destroy');
            });
        });

        Route::group(['prefix' => 'wallet'], function () {
            Route::controller(WalletTransferController::class)->group(function () {
                Route::post('transfer-drivemond-to-mart', 'transferDrivemondToMartWallet');
                Route::post('transfer-drivemond-from-mart', 'transferDrivemondFromMartWallet')->withoutMiddleware('auth:api');
            });
        });
    });

});

Route::group(['prefix' => 'driver'], function () {
    Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::controller(TimeTrackController::class)->group(function () {
            Route::get('time-tracking', 'store');
            Route::post('update-online-status', 'onlineStatus');
        });
        Route::get('notification-list', [AppNotificationController::class, 'index']);
        Route::group(['prefix' => 'activity'], function () {
            Route::controller(DriverActivityController::class)->group(function () {
                Route::get('leaderboard', 'leaderboard');
                Route::get('daily-income', 'dailyIncome');
            });
        });
        Route::controller(DriverController::class)->group(function () {
            Route::get('my-activity', 'myActivity');
            Route::post('change-language', 'changeLanguage');
            Route::get('info', 'profileInfo');
            Route::get('income-statement', 'incomeStatement');
            Route::put('update/profile', 'updateProfile');
            Route::get('referral-details', 'referralDetails');
        });
        Route::group(['prefix' => 'level'], function () {
            Route::controller(DriverLevelController::class)->group(function () {
                Route::get('/', 'getDriverLevelWithTrip');
            });
        });
        Route::group(['prefix' => 'loyalty-points'], function () {
            Route::controller(DriverLoyaltyPointController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('convert', 'convert');
            });
        });
        Route::group(['prefix' => 'withdraw'], function () {
            Route::controller(WithdrawController::class)->group(function () {
                Route::get('methods', 'methods');
                Route::post('request', 'create');
                Route::get('pending-request', 'getPendingWithdrawRequests');
                Route::get('settled-request', 'getSettledWithdrawRequests');
            });
        });
        Route::group(['prefix' => 'withdraw-method-info'], function () {
            Route::controller(WithdrawMethodInfoController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('create', 'create');
                Route::get('edit/{id}', 'edit');
                Route::post('update/{id}', 'update');
                Route::post('delete/{id}', 'destroy');
            });
        });
    });

});

Route::group(['prefix' => 'user'], function () {
    Route::controller(LocationController::class)->group(function () {
        Route::post('store-live-location', 'storeLastLocation');
        Route::post('get-live-location', 'getLastLocation');
    });

});


