<?php

namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Spatie\Multitenancy\Events\ForgettingCurrentTenantEvent;
use Spatie\Multitenancy\Events\ForgotCurrentTenantEvent;
use Spatie\Multitenancy\Events\MadeTenantCurrentEvent;
use Spatie\Multitenancy\Events\MakingTenantCurrentEvent;
use Spatie\Multitenancy\Events\TenantNotFoundForRequestEvent;

/**
 * Tenant Event Subscriber
 *
 * Listens to all 5 Spatie multitenancy lifecycle events for logging,
 * debugging, and hook integration. This provides a single place to
 * monitor and react to tenant context changes.
 *
 * Events fired by Spatie in order:
 * 1. MakingTenantCurrentEvent - Before tasks execute
 * 2. MadeTenantCurrentEvent - After all tasks execute, tenant bound
 * 3. ForgettingCurrentTenantEvent - Before tasks execute on forget
 * 4. ForgotCurrentTenantEvent - After tasks, container cleared
 * 5. TenantNotFoundForRequestEvent - When TenantFinder returns null
 */
class TenantEventSubscriber
{
    /**
     * Handle tenant making current (before tasks).
     */
    public function handleMakingCurrent(MakingTenantCurrentEvent $event): void {}

    /**
     * Handle tenant made current (after all tasks).
     */
    public function handleMadeCurrent(MadeTenantCurrentEvent $event): void
    {
        // Fire WordPress-style hook for module integration
        if (function_exists('do_action')) {
            do_action('tenant.made_current', $event->tenant);
        }
    }

    /**
     * Handle tenant forgetting (before tasks).
     */
    public function handleForgetting(ForgettingCurrentTenantEvent $event): void {}

    /**
     * Handle tenant forgotten (after tasks, container cleared).
     */
    public function handleForgot(ForgotCurrentTenantEvent $event): void
    {
        if (function_exists('do_action')) {
            do_action('tenant.forgot_current');
        }
    }

    /**
     * Handle tenant not found for request.
     */
    public function handleNotFound(TenantNotFoundForRequestEvent $event): void {}

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            MakingTenantCurrentEvent::class,
            [self::class, 'handleMakingCurrent']
        );

        $events->listen(
            MadeTenantCurrentEvent::class,
            [self::class, 'handleMadeCurrent']
        );

        $events->listen(
            ForgettingCurrentTenantEvent::class,
            [self::class, 'handleForgetting']
        );

        $events->listen(
            ForgotCurrentTenantEvent::class,
            [self::class, 'handleForgot']
        );

        $events->listen(
            TenantNotFoundForRequestEvent::class,
            [self::class, 'handleNotFound']
        );
    }
}
