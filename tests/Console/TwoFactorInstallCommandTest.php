<?php

namespace Tests\Console;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class TwoFactorInstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteInstalledFiles();
    }

    protected function tearDown(): void
    {
        $this->deleteInstalledFiles();

        parent::tearDown();
    }

    protected function deleteInstalledFiles(): void
    {
        $migrations = Collection::make(File::files($this->app->databasePath('migrations')))
            ->filter(static function (SplFileInfo $file): bool {
                return Str::endsWith($file->getRealPath(), [
                    'create_two_factor_authentications_table.php',
                ]);
            })->map->getRealPath();

        File::delete($migrations->toArray());

        File::delete([
            $this->app->configPath('two-factor.php'),
            $this->app->viewPath('vendor/two-factor'),
            $this->app->langPath('vendor/two-factor'),
        ]);
    }

    public function test_publishes_all_files(): void
    {
        $this->artisan('two-factor:install');

        Collection::make(File::files($this->app->databasePath('migrations')))
            ->each(static function (SplFileInfo $file): void {
                static::assertTrue(Str::endsWith($file->getFilename(), [
                    'create_two_factor_authentications_table.php',
                ]));
            });

        static::assertFileExists($this->app->configPath('two-factor.php'));
        static::assertFileExists($this->app->viewPath('vendor/two-factor'));
        static::assertFileExists($this->app->langPath('vendor/two-factor'));
    }
}
