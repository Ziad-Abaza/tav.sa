<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Protected API routes
Route::middleware(['auth:sanctum'])->group(function () {

    // WhatsApp integration
    Route::prefix('whatsapp')->group(function () {
        // OTP Routes
        Route::prefix('otp')->group(function () {
            Route::post('/send', [\App\Http\Controllers\Tenant\WhatsappOtpController::class, 'send'])->name('whatsapp.otp.send');
            Route::post('/verify', [\App\Http\Controllers\Tenant\WhatsappOtpController::class, 'verify'])->name('whatsapp.otp.verify');
        });
    });
});
