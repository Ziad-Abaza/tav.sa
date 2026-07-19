<?php

namespace App\Providers;

use App\Events\PaymentGatewayRegistration;
use App\Services\Billing\BillingManager;
use App\Services\PaymentGateways\OfflinePaymentGateway;
use App\Services\PaymentGateways\RegisterPaymentGateways;
use App\Services\Subscription\SubscriptionManager;
use App\Settings\PaymentSettings;
use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OfflinePaymentGateway::class, function ($app) {
            $settings = $app->make(PaymentSettings::class);
            $instructions = $settings->offline_instructions ?? 'Please make payment to our bank account and provide your invoice number in the payment details.';

            return new OfflinePaymentGateway($instructions);
        });

        // Register Billing Manager
        $this->app->singleton('billing.manager', function ($app) {
            $manager = new BillingManager;

            RegisterPaymentGateways::register($manager, $app);

            // Register Offline Payment
            $manager->register('offline', function () use ($app) {
                return $app->make(OfflinePaymentGateway::class);
            });

            // Dispatch event for modules to register their payment gateways
            event(new PaymentGatewayRegistration($manager));

            return $manager;
        });

        // Register Subscription Manager
        $this->app->singleton(SubscriptionManager::class, function ($app) {
            return new SubscriptionManager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
