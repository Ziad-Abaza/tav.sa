<?php

namespace App\Multitenancy\Tasks;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Prefix Cache Task
 *
 * Isolates cache data per tenant by dynamically prefixing all cache keys
 * with the tenant ID. This prevents cache data from bleeding between tenants.
 *
 * CRITICAL: This task must run FIRST in the switch_tenant_tasks array,
 * before any other task that might use the cache.
 *
 * @see https://spatie.be/docs/laravel-multitenancy/v4/using-tasks-to-prepare-the-environment/prefixing-cache
 */
class PrefixCacheTask implements SwitchTenantTask
{
    /**
     * Store the original cache prefix to restore when forgetting tenant.
     * Static to persist across task instances (tasks are singletons in Spatie v4).
     */
    protected static ?string $originalPrefix = null;

    public function makeCurrent(IsTenant $tenant): void
    {
        // Store original prefix only on first tenant switch
        self::$originalPrefix ??= config('cache.prefix') ?? '';

        // Set tenant-specific cache prefix
        $tenantPrefix = self::$originalPrefix.'_tenant_'.$tenant->getKey();

        config(['cache.prefix' => $tenantPrefix]);

        // Force Laravel to create a new cache store instance with the updated prefix
        app('cache')->forgetDriver(config('cache.default'));
    }

    public function forgetCurrent(): void
    {
        if (self::$originalPrefix !== null) {
            // Restore the original (landlord) cache prefix
            config(['cache.prefix' => self::$originalPrefix]);

            // Force cache store to pick up the restored prefix
            app('cache')->forgetDriver(config('cache.default'));
        }
    }
}
