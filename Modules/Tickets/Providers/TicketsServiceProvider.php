<?php

namespace Modules\Tickets\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Tickets\Events\TicketAssigned;
use Modules\Tickets\Events\TicketCreated;
use Modules\Tickets\Events\TicketReplyCreated;
use Modules\Tickets\Events\TicketStatusChanged;
use Modules\Tickets\Listeners\SendTicketAssignedNotification;
use Modules\Tickets\Listeners\SendTicketCreatedNotification;
use Modules\Tickets\Listeners\SendTicketReplyNotification;
use Modules\Tickets\Listeners\SendTicketStatusChangedNotification;
use Modules\Tickets\Table\TenantTicketTable;
use Modules\Tickets\Table\TicketsTable;

class TicketsServiceProvider extends ServiceProvider
{
    /**
     * The module name.
     *
     * @var string
     */
    protected $moduleName = 'Tickets';

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        TicketCreated::class => [
            SendTicketCreatedNotification::class,
        ],
        TicketReplyCreated::class => [
            SendTicketReplyNotification::class,
        ],
        TicketAssigned::class => [
            SendTicketAssignedNotification::class,
        ],
        TicketStatusChanged::class => [
            SendTicketStatusChangedNotification::class,
        ],
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerViews();
        $this->loadMigrationsFrom(base_path('Modules/'.$this->moduleName.'/Database/Migrations'));
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerTableSettings();
        // Routes are now handled by RouteServiceProvider
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register the RouteServiceProvider
        $this->app->register(RouteServiceProvider::class);

        // Register config first before boot
        $this->registerConfig();

        // Register observers
        $this->registerObservers();
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/web.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/api.php'));
    }

    /**
     * Register model observers for better cache management
     *
     * @return void
     */
    protected function registerObservers()
    {
        \Modules\Tickets\Models\Ticket::observe(\Modules\Tickets\Observers\TicketObserver::class);
        \Modules\Tickets\Models\Department::observe(\Modules\Tickets\Observers\DepartmentObserver::class);
    }

    /**
     * Register event listeners for the module.
     *
     * @return void
     */
    protected function registerEvents()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
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
            $this->loadTranslationsFrom(base_path('Modules/'.$this->moduleName.'/resources/lang'), $this->moduleName);
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
            base_path('Modules/'.$this->moduleName.'/Config/config.php') => config_path($this->moduleName.'.php'),
        ], 'config');
        $this->mergeConfigFrom(
            base_path('Modules/'.$this->moduleName.'/Config/config.php'),
            strtolower($this->moduleName)
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

        $sourcePath = base_path('Modules/'.$this->moduleName.'/resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $paths = array_values(array_filter([$viewPath, $sourcePath], 'is_dir'));

        $this->loadViewsFrom($paths, $this->moduleName);
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

    protected function registerTableSettings(): void
    {
        add_filter('table_settings_register', function ($registry) {

            $registry['tenant-ticket'] = TenantTicketTable::class;

            return $registry;
        });

        add_filter('admin_table_settings_register', function ($registry) {

            $registry['tickets'] = TicketsTable::class;

            return $registry;
        });

    }
}
