<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\Cache\CacheSynchronizationService;

/**
 * Plan Observer
 *
 * Handles cache invalidation when plans are modified.
 * Uses bidirectional cache synchronization to ensure admin
 * and tenant caches remain consistent.
 *
 * Invalidation Strategy:
 * - Admin cache: plans, features
 * - Tenant cascade: subscriptions, features, limits, billing
 *
 * @see \App\Models\Plan
 * @see \App\Services\Cache\CacheSynchronizationService
 */
class PlanObserver
{
    /**
     * Cache synchronization service
     */
    protected CacheSynchronizationService $cacheSync;

    /**
     * Constructor
     *
     * @param  CacheSynchronizationService  $cacheSync  Cache sync service
     */
    public function __construct(CacheSynchronizationService $cacheSync)
    {
        $this->cacheSync = $cacheSync;
    }

    /**
     * Handle plan created event
     *
     * @param  Plan  $plan  The created plan
     */
    public function created(Plan $plan): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle plan updated event
     *
     * When a plan is updated (price, features, limits), all tenants
     * with active subscriptions to this plan must see the changes.
     *
     * @param  Plan  $plan  The updated plan
     */
    public function updated(Plan $plan): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle plan deleted event
     *
     * @param  Plan  $plan  The deleted plan
     */
    public function deleted(Plan $plan): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle plan restored event
     *
     * @param  Plan  $plan  The restored plan
     */
    public function restored(Plan $plan): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate plan-related caches
     *
     * Clears admin cache and cascades to all tenant caches
     * to ensure plan changes are immediately reflected.
     */
    protected function invalidateCache(): void
    {
        // Invalidate admin cache and cascade to all tenants
        $this->cacheSync->invalidateAdminCache(
            adminTags: ['plans', 'features'],
            propagateToTenants: true,
            useQueue: true
        );
    }
}
