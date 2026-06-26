<?php

namespace Ometra\Caronte\Console\Commands\Groups;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\GroupApi;
use Ometra\Caronte\Console\Concerns\BindsTenantContext;

class ListGroupUsers extends Command
{
    use BindsTenantContext;

    protected $signature = 'caronte:groups:users:list
        {--tenant= : Tenant identifier required for group user endpoints}
        {--search= : Optional name or email filter}';

    protected $description = 'Search tenant users visible to the configured Caronte application group.';

    public function handle(): int
    {
        try {
            $this->bindTenantContextFromOption();

            $response = GroupApi::showGroupUsers((string) $this->option('search'));
            $users = $response['data']['users'] ?? [];

            if (!is_array($users) || $users === []) {
                $this->warn('No group users were returned by Caronte.');

                return self::SUCCESS;
            }

            $this->table(
                ['URI', 'Tenant', 'Name', 'Email', 'Group roles'],
                array_map(fn(array $user): array => [
                    $user['uri_user'] ?? '',
                    $user['id_tenant'] ?? '',
                    $user['name'] ?? '',
                    $user['email'] ?? '',
                    (string) count((array) ($user['roles'] ?? [])),
                ], $users)
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
