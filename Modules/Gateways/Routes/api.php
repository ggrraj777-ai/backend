<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Gateways\Http\Controllers\Api\V1\PaymentConfigController;
use Modules\Gateways\Http\Controllers\RazorPayController;

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
    Route::group(['prefix' => 'driver/payments/razorpay'], function () {
        Route::post('create-order', [RazorPayController::class, 'createDriverFareOrder']);
        Route::post('verify', [RazorPayController::class, 'verifyDriverFare']);
    });
});

// Webhooks
Route::post('webhooks/razorpay', [RazorPayController::class, 'webhookRazorpay']);
