<?php

namespace Laragear\TwoFactor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laragear\Meta\BootHelpers;

class TwoFactorServiceProvider extends ServiceProvider
{
    use BootHelpers;

    public const CONFIG = __DIR__.'/../config/two-factor.php';
    public const VIEWS = __DIR__.'/../resources/views';
    public const LANG = __DIR__.'/../lang';
    public const MIGRATIONS = __DIR__.'/../database/migrations';

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'two-factor');

        $this->commands(Console\TwoFactorInstallCommand::class);

        $this->app->bind(TwoFactorLoginHelper::class, static function (Application $app): TwoFactorLoginHelper {
            $config = $app->make('config');

            return new TwoFactorLoginHelper(
                $app->make('auth'),
                $app->make('session.store'),
                $app->make('request'),
                $config->get('two-factor.login.view'),
                $config->get('two-factor.login.key'),
                $config->get('two-factor.login.flash')
            );
        });
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
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
     */
    protected function publishFiles(): void
    {
        $this->publishMigrations();

        $this->publishes([static::CONFIG => $this->app->configPath('two-factor.php')], 'config');
        // @phpstan-ignore-next-line
        $this->publishes([static::VIEWS => $this->app->viewPath('vendor/two-factor')], 'views');
        $this->publishes([static::LANG => $this->app->langPath('vendor/two-factor')], 'translations');
    }

    /**
     * Small helper to publish migrations from the paths.
     */
    protected function publishMigrations(): void
    {
        if (method_exists($this, 'publishesMigrations')) {
            $this->publishesMigrations([static::MIGRATIONS => $this->app->databasePath('migrations')], 'migrations');

            return;
        }

        $now = now();

        $files = Collection::make(File::files(static::MIGRATIONS))
            ->mapWithKeys(fn (\SplFileInfo $file): array => [
                $file->getRealPath() => Str::of($file->getFileName())
                        ->after('0000_00_00_000000')
                        ->prepend($now->addSecond()->format('Y_m_d_His'))
                        ->prepend('/')
                        ->prepend($this->app->databasePath('migrations'))
                        ->toString(),
            ]);

        $this->publishes($files->toArray(), 'migrations');
    }
}
