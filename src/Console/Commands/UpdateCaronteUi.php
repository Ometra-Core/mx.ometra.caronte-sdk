<?php

namespace Ometra\Caronte\Console\Commands;

use Illuminate\Console\Command;
use Ometra\Caronte\Providers\CaronteServiceProvider;

class UpdateCaronteUi extends Command
{
    protected $signature = 'caronte:ui:update
        {--views : Also overwrite published Blade views}';

    protected $description = 'Refresh the published Caronte assets and Inertia components.';

    public function handle(): int
    {
        $tags = ['caronte-assets', 'caronte:inertia'];

        if ((bool) $this->option('views')) {
            $tags[] = 'caronte:views';
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--provider' => CaronteServiceProvider::class,
                '--tag' => $tag,
                '--force' => true,
            ]);
        }

        $this->components->info('Caronte UI assets and components were updated.');

        return self::SUCCESS;
    }
}
