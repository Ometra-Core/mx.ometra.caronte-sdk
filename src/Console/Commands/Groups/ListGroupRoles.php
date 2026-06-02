<?php

namespace Ometra\Caronte\Console\Commands\Groups;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\GroupApi;

class ListGroupRoles extends Command
{
    protected $signature = 'caronte:groups:roles:list';

    protected $description = 'List roles exposed by applications in the configured Caronte application group.';

    public function handle(): int
    {
        try {
            $response = GroupApi::showGroupRoles();
            $applications = $response['data']['applications'] ?? [];

            if (!is_array($applications) || $applications === []) {
                $this->warn('No application group roles were returned by Caronte.');

                return self::SUCCESS;
            }

            $rows = [];

            foreach ($applications as $application) {
                if (!is_array($application)) {
                    continue;
                }

                foreach (($application['roles'] ?? []) as $role) {
                    if (!is_array($role)) {
                        continue;
                    }

                    $rows[] = [
                        $application['name'] ?? $application['app_id'] ?? '',
                        $role['name'] ?? '',
                        $role['uri_applicationRole'] ?? '',
                        (($role['manageable'] ?? true) === false) ? 'no' : 'yes',
                    ];
                }
            }

            $this->table(['Application', 'Role', 'URI', 'Manageable'], $rows);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
