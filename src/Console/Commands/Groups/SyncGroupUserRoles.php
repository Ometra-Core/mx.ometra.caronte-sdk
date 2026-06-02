<?php

namespace Ometra\Caronte\Console\Commands\Groups;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\GroupApi;
use Ometra\Caronte\Console\Concerns\BindsTenantContext;

class SyncGroupUserRoles extends Command
{
    use BindsTenantContext;

    protected $signature = 'caronte:groups:users:roles:sync
        {uri_user? : Caronte user URI}
        {--tenant= : Tenant identifier required for group user endpoints}
        {--app= : Target application identifier within the configured group}
        {--role=* : Role names or role URIs to assign}
        {--clear : Remove every non-root assigned role for the user within the target application}';

    protected $description = 'Synchronize a user role set for an application in the configured Caronte application group.';

    public function handle(): int
    {
        $uriUser = trim((string) ($this->argument('uri_user') ?: $this->ask('User URI')));
        $appId = trim((string) ($this->option('app') ?: $this->ask('Target application ID')));

        if ($uriUser === '' || $appId === '') {
            $this->error('A user URI and --app value are required.');

            return self::FAILURE;
        }

        try {
            $this->bindTenantContextFromOption();

            $roles = $this->option('clear') ? [] : $this->resolveRoles($appId);
            $response = GroupApi::syncGroupUserRoles($uriUser, $appId, $roles);
            $this->info($response['message']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveRoles(string $appId): array
    {
        $roles = $this->manageableRolesForApp($appId);
        $selected = array_values(array_filter((array) $this->option('role')));

        if ($selected === []) {
            $choices = array_keys($roles);
            $selected = (array) $this->choice(
                'Select the desired non-root role set',
                $choices,
                default: null,
                attempts: null,
                multiple: true
            );
        }

        $uris = [];

        foreach ($selected as $role) {
            $role = trim((string) $role);

            if (isset($roles[$role])) {
                $uris[] = $roles[$role]['uri_applicationRole'];
                continue;
            }

            $byUri = collect($roles)->firstWhere('uri_applicationRole', $role);

            if (is_array($byUri)) {
                $uris[] = (string) $byUri['uri_applicationRole'];
                continue;
            }

            throw new \RuntimeException("Unknown manageable group role [{$role}] for application [{$appId}].");
        }

        return array_values(array_unique($uris));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manageableRolesForApp(string $appId): array
    {
        $response = GroupApi::showGroupRoles();
        $applications = $response['data']['applications'] ?? [];

        foreach ((array) $applications as $application) {
            if (!is_array($application) || (string) ($application['app_id'] ?? '') !== $appId) {
                continue;
            }

            $roles = [];

            foreach ((array) ($application['roles'] ?? []) as $role) {
                if (!is_array($role) || ($role['manageable'] ?? true) === false) {
                    continue;
                }

                $roles[(string) $role['name']] = $role;
            }

            return $roles;
        }

        throw new \RuntimeException("Application [{$appId}] was not found in the configured group.");
    }
}
