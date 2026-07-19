<?php

namespace Modules\TapGateway\Listeners;

use App\Events\PaymentSettingsViewRendering;
use App\Settings\PaymentSettings;
use Nwidart\Modules\Facades\Module;

class AddTapPaymentSettings
{
    /**
     * Handle the event.
     */
    public function handle(PaymentSettingsViewRendering $event): void
    {
        // Check if module is active
        if (! $this->isModuleActive()) {
            return;
        }

        // Get payment settings using the PaymentSettings class (with dynamic settings support)
        $paymentSettings = app(PaymentSettings::class);
        $tapEnabled = $paymentSettings->tap_enabled ?? false;

        // Add Tap payment gateway card to the event
        $cardHtml = $this->getTapGatewayCard($tapEnabled);

        $event->addPaymentGateway($cardHtml);
    }

    /**
     * Get the Tap payment gateway card HTML
     */
    private function getTapGatewayCard(bool $tapEnabled): string
    {
        $statusClass = $tapEnabled ? 'bg-success-400' : 'bg-gray-200';
        $statusText = $tapEnabled ? 'active' : 'not_configured';
        $statusTextClass = $tapEnabled
            ? 'text-success-600 dark:text-success-400'
            : 'text-gray-600 dark:text-gray-400';

        return view('TapGateway::partials.payment-settings-card', [
            'tapEnabled' => $tapEnabled,
            'statusClass' => $statusClass,
            'statusText' => $statusText,
            'statusTextClass' => $statusTextClass,
        ])->render();
    }

    /**
     * Check if TapGateway module is active
     */
    private function isModuleActive(): bool
    {
        return Module::find('TapGateway')?->isEnabled() ?? false;
    }
}
