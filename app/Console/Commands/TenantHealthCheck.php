<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Multitenancy\TenantStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

/**
 * Tenant Health Check Command
 *
 * Checks tenant integrity including database records, settings, cache state,
 * and subscription status. Can run for a specific tenant or all tenants.
 *
 * Usage:
 *   php artisan tenant:health-check                  # Check all tenants
 *   php artisan tenant:health-check --tenant=1       # Check specific tenant
 */
class TenantHealthCheck extends Command
{
    use TenantAware;

    protected $signature = 'tenant:health-check {--tenant=*}';

    protected $description = 'Check health status of tenant(s) - database, settings, cache, and subscription';

    protected int $issues = 0;

    public function handle(): int
    {
        $tenant = Tenant::current();

        if ($tenant) {
            $this->checkTenant($tenant);
        } else {
            $this->warn('No tenant context - running for all tenants.');
            Tenant::all()->each(fn (Tenant $t) => $this->checkTenant($t));
        }

        $this->newLine();

        if ($this->issues > 0) {
            $this->error("Found {$this->issues} issue(s).");

            return Command::FAILURE;
        }

        $this->info('All health checks passed.');

        return Command::SUCCESS;
    }

    private function checkTenant(Tenant $tenant): void
    {
        $this->info("--- Tenant #{$tenant->id}: {$tenant->subdomain} ---");

        $this->checkDatabaseRecord($tenant);
        $this->checkSettings($tenant);
        $this->checkCacheConnectivity($tenant);
        $this->checkSubscription($tenant);
        $this->checkStatus($tenant);
    }

    private function checkDatabaseRecord(Tenant $tenant): void
    {
        // Verify tenant record exists and has required fields
        $required = ['subdomain', 'status'];
        foreach ($required as $field) {
            if (empty($tenant->{$field})) {
                $this->warn("  [ISSUE] Missing required field: {$field}");
                $this->issues++;
            }
        }

        // Check for orphaned tenant_id references
        $userCount = DB::table('users')->where('tenant_id', $tenant->id)->count();
        $this->line("  Users: {$userCount}");

        if ($userCount === 0) {
            $this->warn('  [WARN] Tenant has no users');
        }
    }

    private function checkSettings(Tenant $tenant): void
    {
        $settingsCount = DB::table('tenant_settings')
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->line("  Settings: {$settingsCount} entries");

        if ($settingsCount === 0) {
            $this->warn('  [WARN] Tenant has no settings configured');
        }
    }

    private function checkCacheConnectivity(Tenant $tenant): void
    {
        try {
            $testKey = "health_check_tenant_{$tenant->id}";
            Cache::put($testKey, true, 10);
            $result = Cache::get($testKey);
            Cache::forget($testKey);

            if ($result !== true) {
                $this->warn('  [ISSUE] Cache read/write failed');
                $this->issues++;
            } else {
                $this->line('  Cache: OK');
            }
        } catch (\Exception $e) {
            $this->error("  [ISSUE] Cache error: {$e->getMessage()}");
            $this->issues++;
        }
    }

    private function checkSubscription(Tenant $tenant): void
    {
        $subscription = $tenant->activeSubscription;

        if (! $subscription || ! $subscription->exists) {
            $this->warn('  [WARN] No active subscription');

            return;
        }

        $this->line("  Subscription: {$subscription->status}");

        if ($subscription->current_period_ends_at && $subscription->current_period_ends_at->isPast()) {
            $this->warn('  [ISSUE] Subscription period has ended');
            $this->issues++;
        }
    }

    private function checkStatus(Tenant $tenant): void
    {
        $statusService = app(TenantStatusService::class);

        if ($statusService->isAccessible($tenant)) {
            $this->line('  Status: Accessible');
        } else {
            $warning = $statusService->getStatusWarning($tenant);
            $this->warn("  [WARN] Status: {$warning['message']}");
        }

        if ($tenant->isMarkedForDeletion()) {
            $this->error('  [ISSUE] Tenant is marked for deletion');
            $this->issues++;
        }
    }
}
