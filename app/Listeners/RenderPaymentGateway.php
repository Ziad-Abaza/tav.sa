<?php

namespace App\Listeners;

use App\Events\PaymentSettingsViewRendering;

class RenderPaymentGateway
{
    /**
     * Handle the event.
     */
    public function handle(PaymentSettingsViewRendering $event): void
    {
        $gateways = ['stripe', 'razorpay', 'paypal',  'paystack'];

        foreach ($gateways as $gateway) {
            $path = "admin.payment-gateways.{$gateway}";
            $settings = get_batch_settings(["payment.{$gateway}_enabled"]);
            if (view()->exists($path)) {
                $event->addPaymentGateway(view($path, compact('settings'))->render());
            }
        }
    }
}
