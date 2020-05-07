<?php

namespace Sebastienheyd\Boilerplate;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application as Laravel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Sebastienheyd\Boilerplate\View\Components\Card;
use Sebastienheyd\Boilerplate\View\Composers\DatatablesComposer;
use Sebastienheyd\Boilerplate\View\Composers\MenuComposer;

class BoilerplateServiceProvider extends ServiceProvider
{
    protected $defer = false;
    protected $loader;
    protected $router;

    /**
     * Create a new boilerplate service provider instance.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->loader = AliasLoader::getInstance();
        $this->router = app('router');
        parent::__construct($app);
    }

    /**
     * Bootstrap the boilerplate services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish files when calling php artisan vendor:publish
        $this->publishes([__DIR__.'/config' => config_path('boilerplate')], 'config');
        $this->publishes([__DIR__.'/public' => public_path('assets/vendor/boilerplate')], 'public');
        $this->publishLang();

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/routes/boilerplate.php');

        // Load migrations, views and translations from current directory
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'boilerplate');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang/boilerplate', 'boilerplate');

        // Loading dynamic menu when calling the view
        View::composer('boilerplate::layout.mainsidebar', MenuComposer::class);

        // For datatables locales
        View::composer('boilerplate::load.datatables', DatatablesComposer::class);

        // Register component
        if (version_compare(Laravel::VERSION, '7.0', '>=')) {
            Blade::component(Card::class, 'card');
        }

        // Add console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MenuItem::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Get config
        $this->mergeConfigFrom(__DIR__.'/config/app.php', 'boilerplate.app');
        $this->mergeConfigFrom(__DIR__.'/config/laratrust.php', 'boilerplate.laratrust');
        $this->mergeConfigFrom(__DIR__.'/config/auth.php', 'boilerplate.auth');
        $this->mergeConfigFrom(__DIR__.'/config/menu.php', 'boilerplate.menu');
        $this->mergeConfigFrom(__DIR__.'/config/theme.php', 'boilerplate.theme');

        // Overriding Laravel config
        config([
            'auth.providers.users.driver'  => config('boilerplate.auth.providers.users.driver', 'eloquent'),
            'auth.providers.users.model'   => config('boilerplate.auth.providers.users.model', 'App\User'),
            'auth.providers.users.table'   => config('boilerplate.auth.providers.users.table', 'users'),
            'log-viewer.route.enabled'     => false,
            'log-viewer.menu.filter-route' => 'boilerplate.logs.filter',
        ]);

        if (!in_array('daily', config('logging.channels.stack.channels'))) {
            config([
                'logging.channels.stack.channels' => array_merge(['daily'], config('logging.channels.stack.channels')),
            ]);
        }

        $this->router->aliasMiddleware('boilerplatelocale', Middleware\BoilerplateLocale::class);
        $this->router->aliasMiddleware('boilerplateauth', Middleware\BoilerplateAuthenticate::class);

        // Loading packages
        $this->registerLaratrust();
        $this->registerMenu();
        $this->registerNavbarItems();
    }

    /**
     * Publish Laravel lang files.
     */
    private function publishLang()
    {
        $toPublish = [];
        foreach (array_diff(scandir(__DIR__.'/resources/lang/boilerplate'), ['..', '.']) as $lang) {
            if ($lang === 'en') {
                continue;
            }
            $toPublish[base_path('vendor/caouecs/laravel-lang/src/'.$lang)] = resource_path('lang/'.$lang);
        }

        $this->publishes($toPublish, 'lang');
    }

    /**
     * Register package lavary/laravel-menu.
     */
    private function registerLaratrust()
    {
        $this->app->register(\Laratrust\LaratrustServiceProvider::class);
        $this->loader->alias('Laratrust', \Laratrust\LaratrustFacade::class);

        // Overriding config
        config([
            'laratrust.user_models.users' => config('boilerplate.laratrust.user', 'App\User'),
            'laratrust.models.role'       => config('boilerplate.laratrust.role', 'App\Role'),
            'laratrust.models.permission' => config('boilerplate.laratrust.permission', 'App\Permission'),
        ]);

        // Registering middlewares
        $this->router->aliasMiddleware('role', \Laratrust\Middleware\LaratrustRole::class);
        $this->router->aliasMiddleware('permission', \Laratrust\Middleware\LaratrustPermission::class);
        $this->router->aliasMiddleware('ability', \Laratrust\Middleware\LaratrustAbility::class);
    }

    /**
     * Register package lavary/laravel-menu.
     */
    private function registerMenu()
    {
        $this->app->register(\Lavary\Menu\ServiceProvider::class);
        $this->loader->alias('Menu', \Lavary\Menu\Facade::class);

        // Menu items repository singleton
        $this->app->singleton('boilerplate.menu.items', function () {
            return new Menu\MenuItemsRepository();
        });

        app('boilerplate.menu.items')->registerMenuItem([
            Menu\Users::class,
            Menu\Logs::class,
        ]);
    }

    /**
     * Register navbar items repository.
     */
    private function registerNavbarItems()
    {
        $this->app->singleton('boilerplate.navbar.items', function () {
            return new Navbar\NavbarItemsRepository();
        });
    }
}
