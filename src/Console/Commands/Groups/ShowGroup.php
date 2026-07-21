<?php

namespace Ometra\Caronte\Console\Commands\Groups;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\GroupApi;
use Ometra\Caronte\Console\Concerns\BindsTenantContext;

class ShowGroup extends Command
{
    use BindsTenantContext;

    protected $signature = 'caronte:group:show
        {--tenant= : Tenant identifier used for user role mappings}';

    protected $description = 'Show the configured Caronte group, applications, assignable roles, API scopes and tenant users.';

    public function handle(): int
    {
        try {
            $this->bindTenantContextFromOption();
            $response = GroupApi::showGroup();
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $group = is_array($data['group'] ?? null) ? $data['group'] : [];
            $applications = is_array($data['applications'] ?? null) ? $data['applications'] : [];
            $users = is_array($data['users'] ?? null) ? $data['users'] : [];

            $this->components->twoColumnDetail('Group ID', (string) ($group['group_id'] ?? ''));
            $this->components->twoColumnDetail('Name', (string) ($group['name'] ?? ''));

            $this->table(
                ['Application', 'Canonical name', 'Assignable roles', 'API scopes'],
                collect($applications)
                    ->filter(fn (mixed $application): bool => is_array($application))
                    ->map(fn (array $application): array => [
                        $application['name'] ?? $application['app_id'] ?? '',
                        $application['cn'] ?? '',
                        collect($application['roles'] ?? [])->pluck('name')->implode(', '),
                        collect($application['scopes'] ?? [])->pluck('scope')->implode(', '),
                    ])->all()
            );

            $this->table(
                ['User', 'Email', 'Tenant', 'Role assignments'],
                collect($users)
                    ->filter(fn (mixed $user): bool => is_array($user))
                    ->map(fn (array $user): array => [
                        $user['name'] ?? $user['uri_user'] ?? '',
                        $user['email'] ?? '',
                        $user['id_tenant'] ?? '',
                        collect($user['role_assignments'] ?? [])->sum(
                            fn (array $assignment): int => count($assignment['roles'] ?? [])
                        ),
                    ])->all()
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
