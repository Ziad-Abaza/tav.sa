<?php

namespace App\Multitenancy;

use App\Models\Tenant;

/**
 * Tenant Status Service
 *
 * Centralizes tenant status checking logic that was previously duplicated
 * across DetermineTenantFromPath and EnsureTenantSecurity middleware.
 *
 * Usage:
 *   $service = new TenantStatusService();
 *   if (!$service->isAccessible($tenant)) {
 *       $warning = $service->getStatusWarning($tenant);
 *   }
 */
class TenantStatusService
{
    /**
     * Check if a tenant is currently accessible (active and not expired).
     */
    public function isAccessible(Tenant $tenant): bool
    {
        return $tenant->status === 'active'
            && (! $tenant->expires_at || ! $tenant->expires_at->isPast());
    }

    /**
     * Get the status warning data for a tenant, or null if tenant is accessible.
     */
    public function getStatusWarning(Tenant $tenant): ?array
    {
        if ($this->isAccessible($tenant)) {
            return null;
        }

        return [
            'status' => $tenant->status,
            'expires_at' => $tenant->expires_at,
            'message' => $this->getStatusMessage($tenant),
        ];
    }

    /**
     * Apply tenant status warning to session (set or forget).
     */
    public function applyStatusToSession(Tenant $tenant): void
    {
        $warning = $this->getStatusWarning($tenant);

        if ($warning) {
            session()->put('tenant_status_warning', $warning);
        } else {
            session()->forget('tenant_status_warning');
        }
    }

    /**
     * Get appropriate user-facing status message for a tenant.
     *
     * @param  string  $suffix  Additional context (e.g., 'You can still create support tickets.')
     */
    public function getStatusMessage(Tenant $tenant, string $suffix = ''): string
    {
        $message = $this->resolveStatusMessage($tenant);

        return $suffix ? "{$message} {$suffix}" : $message;
    }

    /**
     * Resolve the base status message for the tenant.
     */
    private function resolveStatusMessage(Tenant $tenant): string
    {
        if ($tenant->expires_at && $tenant->expires_at->isPast()) {
            return 'Your subscription has expired. Please renew to continue using all features.';
        }

        return match ($tenant->status) {
            'suspended' => 'Your account has been suspended. Please contact support.',
            'expired' => 'Your subscription has expired. Please renew to continue using all features.',
            'deactive' => 'Your account is currently inactive. Please contact support.',
            'inactive' => 'Your account is currently inactive. Please contact support.',
            default => 'Your account has limited access. Please contact support.',
        };
    }
}
