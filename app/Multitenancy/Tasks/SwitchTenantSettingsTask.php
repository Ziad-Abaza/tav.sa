<?php

namespace App\Multitenancy\Tasks;

use Corbital\Settings\Models\TenantSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Switch Tenant Settings Task
 *
 * Loads tenant-specific settings from the database into the application config
 * under the `tenant.*` namespace. Properly cleans up config when forgetting
 * the current tenant to prevent config pollution between tenants.
 *
 * Settings are cached per tenant for performance (30-minute TTL).
 */
class SwitchTenantSettingsTask implements SwitchTenantTask
{
    /**
     * Track which config groups were loaded so we can clean them up.
     * Static because Spatie tasks are singletons.
     */
    protected static array $loadedGroups = [];

    public function makeCurrent(IsTenant $tenant): void
    {
        // Reset tracking from previous tenant
        self::$loadedGroups = [];

        $cacheKey = "tenant_{$tenant->getKey()}_settings";

        $settings = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($tenant) {
            return TenantSetting::where('tenant_id', $tenant->getKey())
                ->get()
                ->groupBy('group')
                ->map(function ($groupSettings) {
                    return $groupSettings->mapWithKeys(function ($setting) {
                        return [$setting->key => $setting->value];
                    });
                })
                ->toArray();
        });

        // Track which groups we're loading
        self::$loadedGroups = array_keys($settings);

        // Apply settings to config namespace
        foreach ($settings as $group => $groupSettings) {
            Config::set("tenant.{$group}", $groupSettings);
        }

        // Store current tenant ID in config for easy access
        Config::set('tenant.current_id', $tenant->getKey());
    }

    public function forgetCurrent(): void
    {
        // Clear all tenant config groups that were loaded
        foreach (self::$loadedGroups as $group) {
            Config::set("tenant.{$group}", null);
        }

        // Clear the tenant ID from config
        Config::set('tenant.current_id', null);

        // Clear the entire tenant config namespace to be safe
        Config::set('tenant', null);

        // Reset tracking
        self::$loadedGroups = [];
    }
}
