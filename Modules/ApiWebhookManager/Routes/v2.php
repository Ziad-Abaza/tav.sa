<?php

use Illuminate\Support\Facades\Route;
use Modules\ApiWebhookManager\Http\Controllers\V2\AccountController;
use Modules\ApiWebhookManager\Http\Controllers\V2\AuthenticationController;
use Modules\ApiWebhookManager\Http\Controllers\V2\ContactController;
use Modules\ApiWebhookManager\Http\Controllers\V2\GroupController;
use Modules\ApiWebhookManager\Http\Controllers\V2\MessageController;
use Modules\ApiWebhookManager\Http\Controllers\V2\SourceController;
use Modules\ApiWebhookManager\Http\Controllers\V2\StatusController;
use Modules\ApiWebhookManager\Http\Controllers\V2\TemplateController;
use Modules\ApiWebhookManager\Http\Controllers\V2\TokenController;
use Modules\ApiWebhookManager\Http\Middleware\V2\AuthenticateApiToken;
use Modules\ApiWebhookManager\Http\Middleware\V2\CheckApiScope;
use Modules\ApiWebhookManager\Http\Middleware\V2\RateLimitApi;

Route::prefix('v2')->group(function () {

    Route::middleware([AuthenticateApiToken::class, RateLimitApi::class])->group(function () {

        // ============================================
        // CONTACTS
        // ============================================
        Route::middleware(CheckApiScope::class.':contacts:read')->group(function () {
            Route::get('/contacts', [ContactController::class, 'index']);
            Route::get('/contacts/{id}', [ContactController::class, 'show']);
        });

        Route::middleware(CheckApiScope::class.':contacts:write')->group(function () {
            Route::post('/contacts', [ContactController::class, 'store']);
            Route::patch('/contacts/{id}', [ContactController::class, 'update']);
            Route::post('/contacts/batch', [ContactController::class, 'batch']);
        });

        Route::middleware(CheckApiScope::class.':contacts:delete')->group(function () {
            Route::delete('/contacts/batch', [ContactController::class, 'batchDelete']);
            Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
        });

        // ============================================
        // MESSAGES
        // ============================================
        Route::middleware(CheckApiScope::class.':messages:send')->group(function () {
            Route::post('/messages/text', [MessageController::class, 'sendText']);
            Route::post('/messages/template', [MessageController::class, 'sendTemplate']);
            Route::post('/messages/media', [MessageController::class, 'sendMedia']);
            Route::post('/messages/interactive', [MessageController::class, 'sendInteractive']);
        });

        Route::middleware(CheckApiScope::class.':messages:read')->group(function () {
            Route::get('/messages', [MessageController::class, 'index']);
            Route::get('/messages/{id}', [MessageController::class, 'show']);
        });

        // ============================================
        // AUTHENTICATION (OTP/Verification)
        // ============================================
        Route::middleware(CheckApiScope::class.':messages:send')->group(function () {
            Route::post('/auth/send-otp', [AuthenticationController::class, 'sendAuthenticationTemplate']);
            Route::post('/auth/verify', [AuthenticationController::class, 'verifyOtp']);
            Route::post('/auth/resend', [AuthenticationController::class, 'resendOtp']);
            Route::post('/auth/status', [AuthenticationController::class, 'checkOtpStatus']);
        });

        Route::middleware(CheckApiScope::class.':templates:read')->group(function () {
            Route::get('/auth/templates', [AuthenticationController::class, 'listAuthenticationTemplates']);
        });

        // ============================================
        // GROUPS
        // ============================================
        Route::middleware(CheckApiScope::class.':groups:read')->group(function () {
            Route::get('/groups', [GroupController::class, 'index']);
            Route::get('/groups/{id}', [GroupController::class, 'show']);
        });

        Route::middleware(CheckApiScope::class.':groups:write')->group(function () {
            Route::post('/groups', [GroupController::class, 'store']);
            Route::patch('/groups/{id}', [GroupController::class, 'update']);
            Route::post('/groups/{id}/contacts', [GroupController::class, 'addContacts']);
            Route::delete('/groups/{id}/contacts', [GroupController::class, 'removeContacts']);
        });

        Route::middleware(CheckApiScope::class.':groups:delete')->group(function () {
            Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
        });

        // ============================================
        // TEMPLATES
        // ============================================
        Route::middleware(CheckApiScope::class.':templates:read')->group(function () {
            Route::get('/templates', [TemplateController::class, 'index']);
            Route::get('/templates/{id}', [TemplateController::class, 'show']);
        });

        Route::middleware(CheckApiScope::class.':templates:sync')->group(function () {
            Route::post('/templates/sync', [TemplateController::class, 'sync']);
        });

        // ============================================
        // SOURCES
        // ============================================
        Route::middleware(CheckApiScope::class.':sources:read')->group(function () {
            Route::get('/sources', [SourceController::class, 'index']);
            Route::get('/sources/{id}', [SourceController::class, 'show']);
        });

        Route::middleware(CheckApiScope::class.':sources:write')->group(function () {
            Route::post('/sources', [SourceController::class, 'store']);
            Route::patch('/sources/{id}', [SourceController::class, 'update']);
            Route::delete('/sources/{id}', [SourceController::class, 'destroy']);
        });

        // ============================================
        // STATUSES
        // ============================================
        Route::middleware(CheckApiScope::class.':statuses:read')->group(function () {
            Route::get('/statuses', [StatusController::class, 'index']);
            Route::get('/statuses/{id}', [StatusController::class, 'show']);
        });

        Route::middleware(CheckApiScope::class.':statuses:write')->group(function () {
            Route::post('/statuses', [StatusController::class, 'store']);
            Route::patch('/statuses/{id}', [StatusController::class, 'update']);
            Route::delete('/statuses/{id}', [StatusController::class, 'destroy']);
        });

        // ============================================
        // ACCOUNT & USAGE
        // ============================================
        Route::middleware(CheckApiScope::class.':account:read')->group(function () {
            Route::get('/account', [AccountController::class, 'show']);
            Route::get('/account/usage', [AccountController::class, 'usage']);
            Route::get('/account/limits', [AccountController::class, 'limits']);
        });

        // ============================================
        // API TOKENS (Admin)
        // ============================================
        Route::middleware(CheckApiScope::class.':tokens:manage')->group(function () {
            Route::get('/tokens', [TokenController::class, 'index']);
            Route::post('/tokens', [TokenController::class, 'store']);
            Route::get('/tokens/{id}', [TokenController::class, 'show']);
            Route::patch('/tokens/{id}', [TokenController::class, 'update']);
            Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);
            Route::post('/tokens/{id}/rotate', [TokenController::class, 'rotate']);
        });
    });
});
