<?php

namespace App\Providers;

use App\Services\PaymentGateways\PayPalPaymentGateway;
use App\Services\PaymentGateways\PaystackPaymentGateway;
use App\Services\PaymentGateways\RazorpayPaymentGateway;
use App\Services\PaymentGateways\StripePaymentGateway;
use App\Settings\PaymentSettings;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind payment gateway classes
        $this->app->singleton(StripePaymentGateway::class, function ($app) {
            $settings = $app->make(PaymentSettings::class);

            return new StripePaymentGateway(
                $settings->stripe_key,
                $settings->stripe_secret
            );
        });

        $this->app->singleton(RazorpayPaymentGateway::class, function ($app) {
            $settings = $app->make(PaymentSettings::class);

            return new RazorpayPaymentGateway(
                $settings->razorpay_key_id,
                $settings->razorpay_key_secret
            );
        });

        $this->app->singleton(PayPalPaymentGateway::class, function ($app) {
            $settings = $app->make(PaymentSettings::class);

            return new PayPalPaymentGateway(
                $settings->paypal_client_id,
                $settings->paypal_client_secret
            );
        });

        $this->app->singleton(PaystackPaymentGateway::class, function ($app) {
            $settings = $app->make(PaymentSettings::class);

            return new PaystackPaymentGateway(
                $settings->paystack_public_key,
                $settings->paystack_secret_key
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
