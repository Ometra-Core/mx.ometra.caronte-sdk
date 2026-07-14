<?php

namespace Ometra\Caronte\Console\Commands\Tenants;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\TenantApi;

class ShowTenant extends Command
{
    protected $signature = 'caronte:tenants:show
        {tenant : Tenant identifier}';

    protected $description = 'Show a Caronte tenant.';

    public function handle(): int
    {
        try {
            $response = TenantApi::showTenant((string) $this->argument('tenant'));
            $data = is_array($response['data']) ? $response['data'] : [];
            $tenant = is_array($data['tenant'] ?? null) ? $data['tenant'] : [];

            if ($tenant === []) {
                $this->warn('Tenant was not returned by Caronte.');

                return self::SUCCESS;
            }

            $this->table(
                ['Field', 'Value'],
                [
                    ['tenant_id', (string) ($tenant['tenant_id'] ?? '')],
                    ['external_id', (string) ($tenant['external_id'] ?? '')],
                    ['name', (string) ($tenant['name'] ?? '')],
                    ['description', (string) ($tenant['description'] ?? '')],
                    ['status', (string) ($tenant['status'] ?? '')],
                    ['users_count', (string) ($tenant['users_count'] ?? 0)],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
