<?php

namespace Laragear\TwoFactor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as ValidatorContract;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class TwoFactorServiceProvider extends ServiceProvider
{
    public const CONFIG = __DIR__.'/../config/two-factor.php';
    public const VIEWS = __DIR__ . '/../resources/views';
    public const LANG = __DIR__.'/../lang';
    public const DB = __DIR__.'/../database/migrations';

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'two-factor');
    }

    /**
     * Bootstrap the application services.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot(Router $router): void
    {
        $this->loadViewsFrom(static::VIEWS, 'two-factor');
        $this->loadTranslationsFrom(static::LANG, 'two-factor');
        $this->loadMigrationsFrom(static::DB);

        $this->registerMiddleware($router);
        $this->registerRules();

        if ($this->app->runningInConsole()) {
            $this->publishFiles();
        }
    }

    /**
     * Register the middleware.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function registerMiddleware(Router $router): void
    {
        $router->aliasMiddleware('2fa.enabled', Http\Middleware\RequireTwoFactorEnabled::class);
        $router->aliasMiddleware('2fa.confirm', Http\Middleware\ConfirmTwoFactorCode::class);
    }

    /**
     * Register custom validation rules.
     *
     * @return void
     */
    protected function registerRules(): void
    {
        $this->callAfterResolving('validator', function (ValidatorContract $validator, Application $app): void {
            $validator->extendImplicit(
                'totp',
                Rules\Totp::class,
                $app->make('translator')->get('two-factor::validation.totp_code')
            );
        });
    }

    /**
     * Publish config, view and migrations files.
     *
     * @return void
     */
    protected function publishFiles(): void
    {
        $this->publishes([static::CONFIG => $this->app->configPath('two-factor.php')], 'config');
        $this->publishes([static::VIEWS => $this->app->viewPath('vendor/two-factor')], 'views');
        $this->publishes([static::LANG => $this->app->langPath('vendor/two-factor')], 'translations');
    }
}
