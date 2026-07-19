<?php

namespace App\Multitenancy;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class PathTenantFinder extends TenantFinder
{
    /**
     * Find tenant for the given request with optimized caching and validation.
     */
    public function findForRequest(Request $request): ?Tenant
    {
        $subdomain = $this->extractSubdomainFromPath($request);

        if (! $subdomain || $this->isReservedPath($subdomain)) {
            return null;
        }

        return $this->findTenantBySubdomain($subdomain);
    }

    /**
     * Extract subdomain from the request path.
     */
    private function extractSubdomainFromPath(Request $request): ?string
    {
        $path = trim($request->path(), '/');

        if (empty($path) || $path === '/') {
            return null;
        }

        $segments = explode('/', $path);
        $subdomain = $segments[0] ?? null;

        // Validate subdomain format
        if (! $subdomain || ! $this->isValidSubdomain($subdomain)) {
            return null;
        }

        return $subdomain;
    }

    /**
     * Check if the given path is reserved.
     */
    private function isReservedPath(string $path): bool
    {
        $reservedPaths = config('restrictions.subdomains', []);

        return in_array(strtolower($path), $reservedPaths, true);
    }

    /**
     * Validate subdomain format.
     */
    private function isValidSubdomain(string $subdomain): bool
    {
        // Basic subdomain validation
        return preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/i', $subdomain) === 1;
    }

    /**
     * Find tenant by subdomain with efficient caching.
     * Cache key standardized to match application-wide pattern.
     */
    private function findTenantBySubdomain(string $subdomain): ?Tenant
    {
        // Standardized cache key format: tenant:subdomain:{value}
        $cacheKey = "tenant:subdomain:{$subdomain}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($subdomain) {
            return Tenant::select([
                'id', 'company_name', 'subdomain', 'domain',
                'status', 'expires_at', 'created_at', 'updated_at',
            ])
                ->where('subdomain', $subdomain)
                ->first();
        });
    }
}
