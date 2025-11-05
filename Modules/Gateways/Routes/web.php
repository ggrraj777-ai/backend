<?php

use Illuminate\Support\Facades\Route;
use Modules\Gateways\Http\Controllers\RazorPayController;
use Modules\Gateways\Http\Controllers\Web\Admin\RazorpaySettlementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Admin routes
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    
    // Razorpay Settlement Management
    Route::group(['prefix' => 'razorpay', 'as' => 'razorpay.'], function () {
        Route::controller(RazorpaySettlementController::class)->group(function () {
            Route::get('settlements', 'index')->name('settlements');
            Route::get('driver-accounts', 'driverAccounts')->name('driver-accounts');
            Route::get('driver-account/{driverId}', 'driverAccount')->name('driver-account');
        });
    });
});

// Razorpay payment routes (existing)
Route::group(['prefix' => 'payment/razor-pay', 'as' => 'razor-pay.'], function () {
    Route::controller(RazorPayController::class)->group(function () {
        Route::get('pay', 'index');
        Route::post('payment', 'payment')->name('payment');
        Route::post('callback', 'callback')->name('callback');
        Route::any('cancel', 'cancel')->name('cancel');
        Route::any('create-order', 'createOrder')->name('create-order');
        Route::any('verify-payment', 'verifyPayment')->name('verify-payment');
    });
});
