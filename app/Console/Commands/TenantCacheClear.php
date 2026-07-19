<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Multitenancy\TenantCacheKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

/**
 * Tenant Cache Clear Command
 *
 * Clears cache for specific tenant(s). Unlike `cache:clear` which flushes
 * everything, this command only removes cache entries for the specified tenant.
 *
 * Usage:
 *   php artisan tenant:cache-clear                  # Clear for all tenants
 *   php artisan tenant:cache-clear --tenant=1       # Clear for specific tenant
 *   php artisan tenant:cache-clear --settings       # Clear only settings cache
 */
class TenantCacheClear extends Command
{
    use TenantAware;

    protected $signature = 'tenant:cache-clear
        {--tenant=* : Tenant ID(s) to clear cache for}
        {--settings : Only clear settings cache}';

    protected $description = 'Clear cache for specific tenant(s)';

    public function handle(): int
    {
        $tenant = Tenant::current();

        if ($tenant) {
            $this->clearTenantCache($tenant);
        } else {
            $this->warn('No tenant context - clearing for all tenants.');
            Tenant::all()->each(fn (Tenant $t) => $this->clearTenantCache($t));
        }

        return Command::SUCCESS;
    }

    private function clearTenantCache(Tenant $tenant): void
    {
        $tenantId = $tenant->id;

        if ($this->option('settings')) {
            $this->clearSettingsCache($tenantId);
            $this->info("Cleared settings cache for tenant #{$tenantId} ({$tenant->subdomain})");

            return;
        }

        // Clear all known tenant cache keys
        $keys = TenantCacheKeys::allKeysFor($tenantId);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Clear subdomain cache
        if ($tenant->subdomain) {
            Cache::forget(TenantCacheKeys::subdomain($tenant->subdomain));
        }

        // Clear legacy cache keys (backward compatibility)
        Cache::forget("tenant_{$tenantId}_settings");
        Cache::forget("tenant:{$tenantId}");
        Cache::forget("tenant:subdomain:{$tenant->subdomain}");

        $this->clearSettingsCache($tenantId);

        $this->info("Cleared all cache for tenant #{$tenantId} ({$tenant->subdomain})");
    }

    private function clearSettingsCache(int $tenantId): void
    {
        // Clear settings cache using known patterns
        Cache::forget(TenantCacheKeys::settings($tenantId));

        // Clear legacy settings cache key
        Cache::forget("tenant_{$tenantId}_settings");

        // We can't enumerate all group/key combinations, but clearing
        // the main settings cache will force a reload on next access
    }
}
