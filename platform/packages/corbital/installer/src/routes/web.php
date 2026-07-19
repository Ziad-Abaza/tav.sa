<?php

use Corbital\Installer\Http\Controllers\DatabaseUpgrade;
use Corbital\Installer\Http\Controllers\InstallController;
use Corbital\Installer\Http\Middleware\CanInstall;
use Illuminate\Support\Facades\Route;

Route::get('/database-upgrade', [DatabaseUpgrade::class, 'index'])->name('database.upgrade');
Route::post('/upgrade', [DatabaseUpgrade::class, 'upgrade'])->name('upgrade');

// Public test route - accessible without login and without install
Route::middleware(['web'])->withoutMiddleware([\Corbital\Installer\Http\Middleware\RedirectIfNotInstalled::class])->group(function () {
    Route::get('/pre-install', [InstallController::class, 'prepareInstall'])->name('public.test');
    Route::post('/pre-install', [InstallController::class, 'preInstall'])->name('public.test.submit');
});

Route::group([
    'prefix' => config('installer.routes.prefix', 'install'),
    'middleware' => array_merge(
        (array) config('installer.routes.middleware', ['web']),
        [CanInstall::class]
    ),
    'as' => config('installer.routes.as', 'install.'),
], function () {
    // Welcome/Introduction screen
    Route::get('/', [InstallController::class, 'index'])->name('index');

    // Requirements check
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');

    // Permissions check
    Route::get('/permissions', [InstallController::class, 'permissions'])->name('permissions');

    // Database and environment setup
    Route::get('/setup', [InstallController::class, 'setup'])->name('setup');
    Route::post('/setup', [InstallController::class, 'setupStore'])->name('setup.store');

    // Admin user creation
    Route::get('/user', [InstallController::class, 'user'])->name('user');
    Route::post('/user', [InstallController::class, 'userStore'])->name('user.store');

    // Installation completed
    Route::get('/finished', [InstallController::class, 'finished'])->name('finished');

    // License verification
    Route::get('/license', [InstallController::class, 'license'])->name('license');
    Route::post('/license', [InstallController::class, 'licenseVerify'])->name('license.verify');

});
