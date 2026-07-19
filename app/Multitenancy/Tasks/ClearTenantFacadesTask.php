<?php

namespace App\Multitenancy\Tasks;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Clear Tenant Facades Task
 *
 * Clears resolved facade instances when switching tenants to prevent
 * stale data from being served. Facades are singletons - once resolved,
 * they return the same instance. If the underlying service uses tenant-specific
 * configuration, the facade may serve data from the previous tenant.
 *
 * @see https://spatie.be/docs/laravel-multitenancy/v4/advanced-usage/using-tenant-specific-facades
 */
class ClearTenantFacadesTask implements SwitchTenantTask
{
    /**
     * Specific facade classes to clear on tenant switch.
     * Add any custom facades that hold tenant-specific state.
     */
    protected array $facadesToClear = [
        \App\Facades\Tenant::class,
        \App\Facades\TenantCache::class,
    ];

    public function makeCurrent(IsTenant $tenant): void
    {
        $this->clearFacades();
    }

    public function forgetCurrent(): void
    {
        $this->clearFacades();
    }

    /**
     * Clear specific facade instances and any App-namespace facades.
     */
    protected function clearFacades(): void
    {
        // Clear explicitly listed facades
        foreach ($this->facadesToClear as $facade) {
            if (class_exists($facade) && method_exists($facade, 'clearResolvedInstances')) {
                $facade::clearResolvedInstances();
            }
        }

        // Clear all App-namespace facades (catches any we missed)
        collect(get_declared_classes())
            ->filter(fn ($className) => is_subclass_of($className, Facade::class))
            ->filter(fn ($className) => Str::startsWith($className, 'App\\'))
            ->each(fn ($className) => $className::clearResolvedInstances());
    }
}
