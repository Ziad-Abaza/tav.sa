<?php

namespace App\Multitenancy;

/**
 * Tenant Cache Keys
 *
 * Centralized cache key generation for tenant-related data.
 * Ensures consistent key formats across the entire application.
 *
 * Standard format: tenant:{tenant_id}:{resource}:{identifier}
 *
 * Usage:
 *   TenantCacheKeys::subdomain('demo')           // "tenant:subdomain:demo"
 *   TenantCacheKeys::byId(123)                   // "tenant:123"
 *   TenantCacheKeys::settings(123)               // "tenant:123:settings"
 *   TenantCacheKeys::settingsGroup(123, 'smtp')  // "tenant:123:settings:smtp"
 *   TenantCacheKeys::setting(123, 'smtp', 'host') // "tenant:123:settings:smtp:host"
 */
class TenantCacheKeys
{
    /**
     * Default cache TTL in minutes for tenant data.
     */
    public const DEFAULT_TTL = 30;

    /**
     * Extended cache TTL in minutes for settings.
     */
    public const SETTINGS_TTL = 60;

    /**
     * Cache key for tenant lookup by subdomain.
     */
    public static function subdomain(string $subdomain): string
    {
        return "tenant:subdomain:{$subdomain}";
    }

    /**
     * Cache key for tenant model by ID.
     */
    public static function byId(int $tenantId): string
    {
        return "tenant:{$tenantId}";
    }

    /**
     * Cache key for all tenant settings.
     */
    public static function settings(int $tenantId): string
    {
        return "tenant:{$tenantId}:settings";
    }

    /**
     * Cache key for a settings group.
     */
    public static function settingsGroup(int $tenantId, string $group): string
    {
        return "tenant:{$tenantId}:settings:{$group}";
    }

    /**
     * Cache key for an individual setting.
     */
    public static function setting(int $tenantId, string $group, string $key): string
    {
        return "tenant:{$tenantId}:settings:{$group}:{$key}";
    }

    /**
     * Cache key for subdomain-to-ID reverse lookup.
     */
    public static function subdomainById(int $tenantId): string
    {
        return "tenant:{$tenantId}:subdomain";
    }

    /**
     * Cache key for tenant model data (selective columns).
     */
    public static function model(int $tenantId, string $suffix = ''): string
    {
        $key = "tenant:{$tenantId}:model";

        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    /**
     * Invalidate all cache keys for a tenant.
     * Returns an array of known cache key patterns to clear.
     */
    public static function allKeysFor(int $tenantId): array
    {
        return [
            self::byId($tenantId),
            self::settings($tenantId),
            self::model($tenantId),
            self::subdomainById($tenantId),
        ];
    }
}
