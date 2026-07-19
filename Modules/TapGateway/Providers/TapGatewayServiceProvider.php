<?php

namespace Modules\TapGateway\Providers;

use App\Events\PaymentGatewayRegistration;
use App\Events\PaymentSettingsExtending;
use App\Events\PaymentSettingsViewRendering;
use Illuminate\Support\ServiceProvider;
use Modules\TapGateway\Listeners\AddTapPaymentSettings;
use Modules\TapGateway\Listeners\ExtendPaymentSettings;
use Modules\TapGateway\Listeners\RegisterTapGateway;

class TapGatewayServiceProvider extends ServiceProvider
{
    /**
     * The module name.
     *
     * @var string
     */
    protected $moduleName = 'TapGateway';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerRoutes();
        $this->registerEventListeners();
        $this->registerHooks();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register TapPaymentGateway service for payment processing
        $this->app->bind(\Modules\TapGateway\Services\TapPaymentGateway::class, function ($app) {
            $settings = $app->make(\App\Settings\PaymentSettings::class);

            return new \Modules\TapGateway\Services\TapPaymentGateway(
                $settings->tap_secret_key,
                $settings->tap_public_key,
                $settings->tap_sandbox_mode
            );
        });

        // Register TapGatewayService for API interactions and testing
        $this->app->bind(\Modules\TapGateway\Services\TapGatewayService::class, function ($app) {
            $settings = $app->make(\App\Settings\PaymentSettings::class);

            return new \Modules\TapGateway\Services\TapGatewayService(
                $settings->tap_secret_key ?? '',
                $settings->tap_sandbox_mode ?? true
            );
        });
    }

    /**
     * Register translations.
     *
     * @return void
     */
    protected function registerTranslations()
    {
        $langPath = resource_path('lang/modules/'.strtolower($this->moduleName));

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleName);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'resources/lang'), $this->moduleName);
        }
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleName.'.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleName
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    protected function registerViews()
    {
        $viewPath = resource_path('views/modules/'.strtolower($this->moduleName));

        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge([$sourcePath], [
            $viewPath,
        ]), $this->moduleName);
    }

    /**
     * Register routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/web.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/api.php'));
    }

    /**
     * Register event listeners.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        $this->app['events']->listen(
            PaymentGatewayRegistration::class,
            RegisterTapGateway::class
        );

        $this->app['events']->listen(
            PaymentSettingsViewRendering::class,
            AddTapPaymentSettings::class
        );

        $this->app['events']->listen(
            PaymentSettingsExtending::class,
            ExtendPaymentSettings::class
        );
    }

    /**
     * Register hooks for WordPress-style integration
     *
     * @return void
     */
    protected function registerHooks()
    {
        // Register billing details hooks
        add_filter('billing_details.payment_settings_keys', function ($settingsKeys) {
            // Add Tap-specific settings keys
            $settingsKeys[] = 'payment.tap_enabled';

            return $settingsKeys;
        });

        add_filter('billing_details.enabled_gateways', function ($enabledGateways) {
            // Get Tap settings directly
            $tapSettings = get_batch_settings([
                'payment.tap_enabled',
            ]);

            // Determine if Tap gateway should be enabled
            $tapEnabled = ($tapSettings['payment.tap_enabled'] ?? false);

            // Add Tap gateway to enabled gateways
            $enabledGateways['tap'] = $tapEnabled;

            return $enabledGateways;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
