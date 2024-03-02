<?php

namespace Tests;

use Laragear\TwoFactor\TwoFactorServiceProvider;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as BaseTestCase;

#[WithMigration]
abstract class TestCase extends BaseTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [TwoFactorServiceProvider::class];
    }
}
