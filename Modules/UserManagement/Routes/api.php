<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\DocumentController;

/*
|--------------------------------------------------------------------------
| API Routes - Driver Documents
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v1', 'as' => 'v1.'], function () {
    
    // Driver document routes (protected)
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'driver'], function () {
        
        // Document upload endpoints
        Route::post('documents/license/upload', [DocumentController::class, 'uploadLicense']);
        Route::post('documents/rc/upload', [DocumentController::class, 'uploadRCBook']);
        Route::post('documents/aadhar/upload', [DocumentController::class, 'uploadAadhar']);
        Route::post('documents/photo/upload', [DocumentController::class, 'uploadPhoto']);
        
        // Document management
        Route::get('documents', [DocumentController::class, 'getDocuments']);
        Route::delete('documents/{documentId}', [DocumentController::class, 'deleteDocument']);
    });
});
