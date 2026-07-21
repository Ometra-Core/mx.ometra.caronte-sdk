<?php

namespace Ometra\Caronte\Console\Commands\Users;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\ClientApi;
use Ometra\Caronte\Console\Concerns\BindsTenantContext;
use Ometra\Caronte\Support\ConfiguredRoles;

class SyncUserRoles extends Command
{
    use BindsTenantContext;


    protected $signature = 'caronte:users:roles:sync
        {uri_user? : Caronte user URI}
        {--tenant= : Tenant identifier required for user-scoped Caronte endpoints}
        {--role=* : Configured role names to assign}
        {--clear : Remove every assigned role for the user within this application}
        {--force : Apply the synchronization without interactive confirmation}';

    protected $description = 'Synchronize the configured role set for a user.';

    public function handle(): int
    {
        $uriUser = trim((string) ($this->argument('uri_user') ?: $this->ask('User URI')));

        if ($uriUser === '') {
            $this->error('A user URI is required.');

            return self::FAILURE;
        }

        try {
            $this->bindTenantContextFromOption();

            $desiredRoles = $this->option('clear') ? [] : $this->resolveRoles();
            $currentRoles = $this->currentRoles($uriUser);

            $this->renderDiff($currentRoles, $desiredRoles);

            if (! $this->option('force')) {
                if (! $this->input->isInteractive()) {
                    $this->error('Confirmation is required. Re-run interactively or use --force.');

                    return self::FAILURE;
                }

                if (! $this->confirm('Apply this final role set?', false)) {
                    $this->warn('Role synchronization cancelled.');

                    return self::SUCCESS;
                }
            }

            $response = ClientApi::syncUserRoles($uriUser, $desiredRoles);
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
    private function resolveRoles(): array
    {
        $configured = ConfiguredRoles::keyedByName();
        $selected = array_values(array_filter((array) $this->option('role')));

        if ($selected === []) {
            $choices = array_keys($configured);
            $selected = (array) $this->choice(
                'Select the desired role set',
                $choices,
                default: null,
                attempts: null,
                multiple: true
            );
        }

        $uris = [];

        foreach ($selected as $role) {
            if (!isset($configured[$role])) {
                throw new \RuntimeException("Unknown configured role [{$role}].");
            }

            $uris[] = $configured[$role]['uri_applicationRole'];
        }

        return $uris;
    }

    /**
     * @return array<string, string> Role URI keyed by URI with its display name as value.
     */
    private function currentRoles(string $uriUser): array
    {
        $response = ClientApi::showUserRoles($uriUser);
        $roles = is_array($response['data'] ?? null) ? $response['data'] : [];

        return collect($roles)
            ->filter(fn (mixed $role): bool => is_array($role) && is_string($role['uri_applicationRole'] ?? null))
            ->mapWithKeys(fn (array $role): array => [
                $role['uri_applicationRole'] => (string) ($role['name'] ?? $role['uri_applicationRole']),
            ])
            ->all();
    }

    /**
     * @param  array<string, string>  $currentRoles
     * @param  array<int, string>  $desiredRoleUris
     */
    private function renderDiff(array $currentRoles, array $desiredRoleUris): void
    {
        $configuredNames = collect(ConfiguredRoles::all())
            ->mapWithKeys(fn (array $role): array => [$role['uri_applicationRole'] => $role['name']]);
        $desiredRoles = collect($desiredRoleUris)
            ->mapWithKeys(fn (string $uri): array => [$uri => (string) $configuredNames->get($uri, $uri)])
            ->all();

        $format = fn (array $roles): string => $roles === [] ? '(none)' : implode(', ', array_values($roles));

        $this->newLine();
        $this->components->twoColumnDetail('Current roles', $format($currentRoles));
        $this->components->twoColumnDetail('Final roles', $format($desiredRoles));
        $this->components->twoColumnDetail('Will add', $format(array_diff_key($desiredRoles, $currentRoles)));
        $this->components->twoColumnDetail('Will remove', $format(array_diff_key($currentRoles, $desiredRoles)));
    }
}
