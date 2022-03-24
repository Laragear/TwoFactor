<?php

namespace Laragear\TwoFactor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laragear\Meta\BootHelpers;
use Laragear\Meta\PublishesMigrations;

class TwoFactorServiceProvider extends ServiceProvider
{
    use PublishesMigrations;
    use BootHelpers;

    public const CONFIG = __DIR__.'/../config/two-factor.php';
    public const VIEWS = __DIR__.'/../resources/views';
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

        $this->withMiddleware(Http\Middleware\RequireTwoFactorEnabled::class)->as('2fa.enabled');
        $this->withMiddleware(Http\Middleware\ConfirmTwoFactorCode::class)->as('2fa.confirm');

        $this->withValidationRule('totp',
            Rules\Totp::class, static function ($validator, Application $app): string {
                return $app->make('translator')->get('two-factor::validation.totp_code');
            }
        );

        if ($this->app->runningInConsole()) {
            $this->publishFiles();
        }
    }

    /**
     * Publish config, view and migrations files.
     *
     * @return void
     */
    protected function publishFiles(): void
    {
        $this->publishesMigrations(static::DB);

        $this->publishes([static::CONFIG => $this->app->configPath('two-factor.php')], 'config');
        $this->publishes([static::VIEWS => $this->app->viewPath('vendor/two-factor')], 'views');
        $this->publishes([static::LANG => $this->app->langPath('vendor/two-factor')], 'translations');
    }
}
