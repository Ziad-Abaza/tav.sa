<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckTenantDeleted;
use App\Http\Middleware\SanitizeInputs;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;
use Modules\Tickets\Http\Controllers\Api\Table\TenantTicketController;
use Modules\Tickets\Http\Controllers\Api\Table\TicketsTableController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your module. These
| routes are loaded by the ServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::middleware(['web', 'auth', TenantMiddleware::class, CheckTenantDeleted::class, EnsureEmailIsVerified::class])
    ->prefix('/{subdomain}/api')
    ->as('tenant.')
    ->group(function () {

        // Tenant Ticket
        Route::get('/tenant-ticket', [TenantTicketController::class, 'index'])->name('tenant-ticket.index');
        Route::get('/ticket-filters', [TenantTicketController::class, 'filters'])->name('tenant-ticket.filters');
        Route::get('/tenant-ticket/export', [TenantTicketController::class, 'export'])->name('tenant-ticket.export');
    });

Route::middleware(['web', 'auth', SanitizeInputs::class, AdminMiddleware::class, EnsureEmailIsVerified::class])
    ->prefix('/admin/api')
    ->as('admin.')
    ->group(function () {
        // Vue DataTable API endpoints

       Route::get('/tickets', [TicketsTableController::class, 'index'])->name('api.tickets.index');
        Route::get('/tickets/export', [TicketsTableController::class, 'export'])->name('api.tickets.export');
    });
