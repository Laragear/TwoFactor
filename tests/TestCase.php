<?php

namespace Tests;

use Laragear\TwoFactor\TwoFactorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TwoFactorServiceProvider::class];
    }
}
