<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * BelongsToTenant Trait
 *
 * Provides automatic tenant scoping for Eloquent models in a single-database
 * multi-tenant setup using Spatie Laravel Multitenancy v4.
 *
 * Features:
 * - Global scope with qualified table names (JOIN-safe)
 * - Auto-sets tenant_id on creation
 * - Prevents unauthorized tenant_id changes
 * - Provides scopeForTenant() and scopeWithoutTenantScope()
 *
 * Usage:
 *   class YourModel extends Model
 *   {
 *       use BelongsToTenant;
 *   }
 */
trait BelongsToTenant
{
    /**
     * Boot the trait to apply tenant scoping.
     */
    public static function bootBelongsToTenant(): void
    {
        // Apply tenant scope using qualified table name to prevent
        // ambiguous column errors in JOIN queries
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (Tenant::checkCurrent() && ! $builder->getQuery()->joins) {
                // Check if we already have a where clause for tenant_id to avoid duplicating it
                $alreadyScoped = collect($builder->getQuery()->wheres)
                    ->contains(function ($where) {
                        return isset($where['column']) ? $where['column'] === 'tenant_id' && (isset($where['value']) && $where['value'] == Tenant::current()->id) : null;
                    });

                if (! $alreadyScoped) {
                    $builder->where('tenant_id', Tenant::current()->id);
                }
            }
        });

        // Automatically set tenant_id on new models
        static::creating(function ($model): void {
            if (! $model->isDirty('tenant_id') && Tenant::checkCurrent()) {
                $model->tenant_id = Tenant::current()->id;
            }
        });

        // Protect against unauthorized tenant_id modification
        static::updating(function ($model): void {
            if ($model->isDirty('tenant_id')) {
                $user = Auth::user();

                // Allow tenant_id changes for super admins or console operations
                if (! $user || (! $user->is_admin && ! app()->runningInConsole())) {
                    throw new \InvalidArgumentException(
                        'Unauthorized tenant assignment change on '.class_basename($model)
                    );
                }
            }
        });
    }

    /**
     * Define the relationship to the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to only include records for a specific tenant.
     */
    public function scopeForTenant(Builder $query, int|Tenant $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where("{$this->getTable()}.tenant_id", $tenantId);
    }

    /**
     * Scope to bypass tenant filtering (for admin dashboards, system operations).
     *
     * Usage: Model::withoutTenantScope()->get()
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Check if this model belongs to the currently active tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        return Tenant::checkCurrent()
            && $this->tenant_id === Tenant::current()->id;
    }
}
