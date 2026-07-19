<?php

namespace App\Services\PaymentGateways;

use App\Services\Billing\BillingManager;
use App\Settings\PaymentSettings;

class RegisterPaymentGateways
{
    public static function register(BillingManager $manager, $app): void
    {
        $settings = $app->make(PaymentSettings::class);

        // Stripe
        if ($settings->stripe_enabled && $settings->stripe_key && $settings->stripe_secret) {
            $manager->register('stripe', function () use ($app) {
                $settings = $app->make(PaymentSettings::class);

                return new StripePaymentGateway(
                    $settings->stripe_key,
                    $settings->stripe_secret
                );
            });
        }

        // Razorpay
        if ($settings->razorpay_enabled && $settings->razorpay_key_id && $settings->razorpay_key_secret) {
            $manager->register('razorpay', function () use ($app) {
                $settings = $app->make(PaymentSettings::class);

                return new RazorpayPaymentGateway(
                    $settings->razorpay_key_id,
                    $settings->razorpay_key_secret
                );
            });
        }

        // PayPal
        if ($settings->paypal_enabled && $settings->paypal_client_id && $settings->paypal_client_secret) {
            $manager->register('paypal', function () use ($app) {
                return new PayPalPaymentGateway(
                    $app->make(PaymentSettings::class)
                );
            });
        }

        // Paystack
        if ($settings->paystack_enabled && $settings->paystack_public_key && $settings->paystack_secret_key) {
            $manager->register('paystack', function () use ($app) {
                $settings = $app->make(PaymentSettings::class);

                return new PaystackPaymentGateway(
                    $settings->paystack_public_key,
                    $settings->paystack_secret_key
                );
            });
        }
    }
}
