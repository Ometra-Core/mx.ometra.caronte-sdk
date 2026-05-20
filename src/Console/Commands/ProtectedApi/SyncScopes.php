<?php

namespace Ometra\Caronte\Console\Commands\ProtectedApi;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\ScopeApi;
use Ometra\Caronte\Support\ConfiguredScopes;

class SyncScopes extends Command
{
    protected $signature = 'caronte:protected-api:scopes:sync {--dry-run : Show the normalized configured scopes without pushing them to Caronte}';

    protected $description = 'Synchronize protected API scopes defined in config/caronte.php with the Caronte server.';

    public function handle(): int
    {
        $scopes = ConfiguredScopes::all();

        if ($this->option('dry-run')) {
            $this->table(['scope', 'description'], $scopes);

            return self::SUCCESS;
        }

        $response = ScopeApi::syncScopes($scopes);
        $this->info((string) ($response['message'] ?? 'Protected API scopes synchronized'));

        return self::SUCCESS;
    }
}
