<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\SanitizeInputs;
use Illuminate\Support\Facades\Route;
use Modules\TapGateway\Http\Controllers\Admin\TapSettingsController;
use Modules\TapGateway\Http\Controllers\PaymentGateways\TapController;

/*
|--------------------------------------------------------------------------
| Tap Gateway Module Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the TapGateway module including payment processing,
| admin settings, and auto-billing setup.
|
*/

// Admin routes for Tap settings (protected by middleware in controller)
Route::middleware(['web', 'auth', AdminMiddleware::class, SanitizeInputs::class])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('settings/payment')->name('settings.payment.')->group(function () {
        Route::get('/tap', [TapSettingsController::class, 'index'])->name('tap');
        Route::put('/tap', [TapSettingsController::class, 'update'])->name('tap.update');
        Route::post('/tap/test-connection', [TapSettingsController::class, 'testConnection'])->name('tap.test');
    });
});

// Tenant payment routes
Route::middleware(['web', 'tenant'])->group(function () {
    Route::prefix('{subdomain}')->group(function () {
        Route::prefix('payment/tap')->name('tenant.payment.tap.')->group(function () {
            // Checkout page
            Route::get('/checkout/{invoice}', [TapController::class, 'checkout'])->name('checkout');

            // Payment callback from Tap
            Route::get('/callback/{invoice}', [TapController::class, 'callback'])->name('callback');

            // Auto-billing setup page
            Route::get('/setup', [TapController::class, 'autoBillingData'])->name('setup');

            // Payment status checking (for polling)
            Route::get('/status/{transaction}', [TapController::class, 'checkPaymentStatus'])->name('status');
        });
    });
});
