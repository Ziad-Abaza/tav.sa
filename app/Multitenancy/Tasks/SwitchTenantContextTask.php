<?php

namespace App\Multitenancy\Tasks;

use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Switch Tenant Context Task
 *
 * Sets the current tenant ID in the MySQL session variable and Laravel container.
 * This enables raw SQL queries to access the current tenant ID via @current_tenant_id
 * and provides a container binding for global access.
 *
 * Note: This does NOT switch databases (single-database mode).
 * Previously named SwitchTenantDatabaseTask - renamed for clarity.
 */
class SwitchTenantContextTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        // Set the tenant ID in the MySQL session for raw queries
        DB::statement("SET @current_tenant_id = {$tenant->getKey()}");

        // Bind tenant ID in the container for global access
        app()->instance('current_tenant_id', $tenant->getKey());
    }

    public function forgetCurrent(): void
    {
        // Reset the MySQL session variable
        DB::statement('SET @current_tenant_id = NULL');

        // Remove the container binding
        app()->forgetInstance('current_tenant_id');
    }
}
