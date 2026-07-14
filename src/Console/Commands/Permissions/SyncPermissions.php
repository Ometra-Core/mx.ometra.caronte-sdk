<?php

namespace Ometra\Caronte\Console\Commands\Permissions;

use Ometra\Caronte\Console\Commands\ProtectedApi\SyncScopes;
use Ometra\Caronte\Support\LegacyDeprecation;

/**
 * @deprecated Use caronte:protected-api:scopes:sync instead.
 * This compatibility command will be removed in the next major version.
 */
class SyncPermissions extends SyncScopes
{
    protected $signature = 'caronte:permissions:sync {--dry-run : Show the normalized configured permissions without pushing them to Caronte}';

    protected $description = 'Deprecated alias for caronte:protected-api:scopes:sync.';

    public function handle(): int
    {
        LegacyDeprecation::warn('caronte:permissions:sync command', 'caronte:protected-api:scopes:sync');

        return parent::handle();
    }
}
