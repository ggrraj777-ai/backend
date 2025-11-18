<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Gateways\Http\Controllers\Api\V1\PaymentConfigController;
use Modules\Gateways\Http\Controllers\RazorPayController;
use Modules\Gateways\Http\Controllers\Api\V1\RazorpayAccountController;

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

Route::middleware('auth:api')->get('/Gateways', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'v1', 'as'=>'v1.'], function () {
    Route::get('payment-config', [PaymentConfigController::class, 'payment_config_get']);
    
    // Driver Razorpay routes
    Route::group(['prefix' => 'driver/payments/razorpay'], function () {
        Route::post('create-order', [RazorPayController::class, 'createDriverFareOrder']);
        Route::post('verify', [RazorPayController::class, 'verifyDriverFare']);
        Route::post('generate-qr', [RazorPayController::class, 'generateDriverQRCode']);
        Route::get('qr-status/{qrCodeId}', [RazorPayController::class, 'checkQRCodeStatus']);
    });

    // Customer Razorpay routes (with auto-split)
    Route::group(['prefix' => 'customer/payments/razorpay', 'middleware' => 'auth:sanctum'], function () {
        Route::post('create-order-with-split', [RazorPayController::class, 'createOrderWithAutoSplit']);
    });

    // Driver Razorpay Account Management
    Route::group(['prefix' => 'driver/razorpay', 'middleware' => 'auth:sanctum'], function () {
        Route::post('link-account', [RazorpayAccountController::class, 'linkBankAccount']);
        Route::post('link-upi', [RazorpayAccountController::class, 'linkUPI']);
        Route::get('account-status', [RazorpayAccountController::class, 'getAccountStatus']);
        Route::get('settlements', [RazorpayAccountController::class, 'getSettlements']);
    });
});

// Webhooks
Route::post('webhooks/razorpay', [RazorPayController::class, 'webhookRazorpay']);
