<?php

namespace Modules\TapGateway\Listeners;

use App\Events\PaymentGatewayRegistration;
use App\Settings\PaymentSettings;
use Modules\TapGateway\Services\TapPaymentGateway;
use Nwidart\Modules\Facades\Module;

class RegisterTapGateway
{
    /**
     * Handle the event.
     */
    public function handle(PaymentGatewayRegistration $event): void
    {
        // Check if module is active
        if (! $this->isModuleActive()) {
            return;
        }

        // Get payment settings
        $settings = app(PaymentSettings::class);

        // Register Tap gateway only if enabled and configured
        if ($settings->tap_enabled &&
            ! empty($settings->tap_public_key) &&
            ! empty($settings->tap_secret_key)) {

            $event->billingManager->register('tap', function () {
                return app(TapPaymentGateway::class);
            });
        }
    }

    /**
     * Check if TapGateway module is active
     */
    private function isModuleActive(): bool
    {
        return Module::find('TapGateway')?->isEnabled() ?? false;
    }
}
