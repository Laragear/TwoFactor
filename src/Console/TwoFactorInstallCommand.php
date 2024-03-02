<?php

namespace Laragear\TwoFactor\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleContract;
use Laragear\TwoFactor\TwoFactorServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'two-factor:install')]
class TwoFactorInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'two-factor:install {--force : Overwrite any existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Two Factor package files';

    /**
     * Execute the console command.
     */
    public function handle(ConsoleContract $console): void
    {
        foreach (['migrations', 'config', 'views', 'translations'] as $tag) {
            $console->call('vendor:publish', [
                '--provider' => TwoFactorServiceProvider::class,
                '--force' => $this->option('force'),
                '--tag' => $tag,
            ]);
        }
    }
}
