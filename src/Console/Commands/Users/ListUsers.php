<?php

namespace Ometra\Caronte\Console\Commands\Users;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\ClientApi;
use Ometra\Caronte\Console\Concerns\BindsTenantContext;

class ListUsers extends Command
{
    use BindsTenantContext;

    protected $signature = 'caronte:users:list
        {--tenant= : Tenant identifier required for user-scoped Caronte endpoints}
        {--search= : Optional name or email filter}
        {--app-users : Only include users currently linked to the application}
        {--all : Deprecated alias kept for compatibility; all tenant users are now returned by default}';

    protected $description = 'Search Caronte users in a tenant.';

    public function handle(): int
    {
        try {
            $this->bindTenantContextFromOption();

            $response = ClientApi::showUsers(
                search: (string) $this->option('search'),
                usersApp: (bool) $this->option('app-users')
            );

            $users = is_array($response['data']) ? $response['data'] : [];

            if ($users === []) {
                $this->warn('No users were returned by Caronte.');

                return self::SUCCESS;
            }

            $this->table(
                ['URI', 'Tenant', 'Name', 'Email'],
                array_map(fn(array $user): array => [
                    $user['uri_user'] ?? '',
                    $user['tenant_id'] ?? '',
                    $user['name'] ?? '',
                    $user['email'] ?? '',
                ], $users)
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
