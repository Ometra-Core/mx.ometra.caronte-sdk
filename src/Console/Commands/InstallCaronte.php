<?php

namespace Ometra\Caronte\Console\Commands;

use Illuminate\Console\Command;
use Ometra\Caronte\Providers\CaronteServiceProvider;

class InstallCaronte extends Command
{
    protected $signature = 'caronte:install {--force : Overwrite previously published configuration and assets}';

    protected $description = 'Publish the configuration, assets, and migrations required by Caronte.';

    public function handle(): int
    {
        $arguments = [
            '--provider' => CaronteServiceProvider::class,
            '--force' => (bool) $this->option('force'),
        ];

        foreach (['caronte:config', 'caronte-assets', 'caronte:migrations'] as $tag) {
            $this->call('vendor:publish', [...$arguments, '--tag' => $tag]);
        }

        $this->components->info('Caronte is installed. Configuration, assets, and migrations are ready.');

        return self::SUCCESS;
    }
}
