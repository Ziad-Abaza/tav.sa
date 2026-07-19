<?php

namespace Modules\TapGateway\Listeners;

use App\Events\PaymentSettingsExtending;
use Nwidart\Modules\Facades\Module;

class ExtendPaymentSettings
{
    /**
     * Handle the event.
     */
    public function handle(PaymentSettingsExtending $event): void
    {
        // Check if module is active
        if (! $this->isModuleActive()) {
            return;
        }

        // Add Tap payment settings extensions
        $event->addExtension('tap_enabled', false);
        $event->addExtension('tap_mode', 'sandbox'); // sandbox or live
        $event->addExtension('tap_public_key', '');
        $event->addExtension('tap_secret_key', '');
        $event->addExtension('tap_sandbox_mode', true);
    }

    /**
     * Check if TapGateway module is active
     */
    private function isModuleActive(): bool
    {
        return Module::find('TapGateway')?->isEnabled() ?? false;
    }
}
