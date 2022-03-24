<?php

namespace Tests;

use Illuminate\Support\ServiceProvider;
use Laragear\Meta\Testing\InteractsWithServiceProvider;
use Laragear\TwoFactor\Http\Middleware\ConfirmTwoFactorCode;
use Laragear\TwoFactor\Http\Middleware\RequireTwoFactorEnabled;
use Laragear\TwoFactor\Rules\Totp;
use Laragear\TwoFactor\TwoFactorServiceProvider;

class ServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_merges_config(): void
    {
        static::assertSame(
            $this->app->make('files')->getRequire(TwoFactorServiceProvider::CONFIG),
            $this->app->make('config')->get('two-factor')
        );
    }

    public function test_load_views(): void
    {
        static::assertArrayHasKey('two-factor', $this->app->make('view')->getFinder()->getHints());
    }

    public function test_loads_translations(): void
    {
        static::assertArrayHasKey('two-factor', $this->app->make('translator')->getLoader()->namespaces());
    }

    public function test_publishes_migrations(): void
    {
        $this->assertPublishesMigrations(TwoFactorServiceProvider::DB);
    }

    public function test_publishes_middleware(): void
    {
        $middleware = $this->app->make('router')->getMiddleware();

        static::assertSame(RequireTwoFactorEnabled::class, $middleware['2fa.enabled']);
        static::assertSame(ConfirmTwoFactorCode::class, $middleware['2fa.confirm']);
    }

    public function test_registers_middleware(): void
    {
        static::assertArrayHasKey('2fa.enabled', $this->app->make('router')->getMiddleware());
        static::assertSame(RequireTwoFactorEnabled::class, $this->app->make('router')->getMiddleware()['2fa.enabled']);

        static::assertArrayHasKey('2fa.confirm', $this->app->make('router')->getMiddleware());
        static::assertSame(ConfirmTwoFactorCode::class, $this->app->make('router')->getMiddleware()['2fa.confirm']);
    }

    public function test_registers_validation_rule(): void
    {
        static::assertSame(
            ['totp' => Totp::class],
            $this->app->make('validator')->make([], [])->extensions
        );
    }

    public function test_publishes_config(): void
    {
        static::assertSame(
            [TwoFactorServiceProvider::CONFIG => $this->app->configPath('two-factor.php')],
            ServiceProvider::pathsToPublish(TwoFactorServiceProvider::class, 'config')
        );
    }

    public function test_publishes_views(): void
    {
        static::assertSame(
            [TwoFactorServiceProvider::VIEWS => $this->app->viewPath('vendor/two-factor')],
            ServiceProvider::pathsToPublish(TwoFactorServiceProvider::class, 'views')
        );
    }

    public function test_publishes_translation(): void
    {
        static::assertSame(
            [TwoFactorServiceProvider::LANG => $this->app->langPath('vendor/two-factor')],
            ServiceProvider::pathsToPublish(TwoFactorServiceProvider::class, 'translations')
        );
    }
}
