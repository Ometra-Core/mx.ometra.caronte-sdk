<?php

namespace Ometra\Caronte\Console\Commands;

use Illuminate\Console\Command;

class ManagementCaronte extends Command
{
    protected $signature = 'caronte:admin';

    protected $description = 'Interactive entry point for Caronte user management commands.';

    public function handle(): int
    {
        do {
            $selected = $this->choice('Choose an operation', [
                'Sync configured roles',
                'List tenants',
                'Show tenant',
                'List users',
                'Create user',
                'Update user',
                'Delete user',
                'Sync user roles',
                'Show group',
                'Sync group user roles',
                'Exit',
            ]);

            match ($selected) {
                'Sync configured roles' => $this->call('caronte:roles:sync'),
                'List tenants' => $this->call('caronte:tenants:list'),
                'Show tenant' => $this->call('caronte:tenants:show', [
                    'tenant' => (string) $this->ask('Tenant identifier'),
                ]),
                'List users' => $this->call('caronte:users:list'),
                'Create user' => $this->call('caronte:users:create'),
                'Update user' => $this->call('caronte:users:update'),
                'Delete user' => $this->call('caronte:users:delete'),
                'Sync user roles' => $this->call('caronte:users:roles:sync'),
                'Show group' => $this->call('caronte:group:show'),
                'Sync group user roles' => $this->call('caronte:group:users:roles:sync'),
                'Exit' => null,
            };

            if ($selected === 'Exit') {
                break;
            }
        } while (true);

        return self::SUCCESS;
    }
}
