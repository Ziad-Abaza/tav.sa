<?php

use Illuminate\Support\Facades\Route;
use Modules\TapGateway\Http\Controllers\PaymentGateways\TapController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the TapGateway module, primarily used for
| webhook endpoints and API-based payment processing.
|
*/

Route::middleware('api')->prefix('api')->group(function () {
    Route::prefix('tap')->name('api.tap.')->group(function () {
        // Optional: Payment status check endpoint
        Route::get('/payment/{transactionId}/status', [TapController::class, 'checkPaymentStatus'])->name('status');
    });
});
