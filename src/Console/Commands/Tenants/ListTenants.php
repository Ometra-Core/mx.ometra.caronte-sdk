<?php

namespace Ometra\Caronte\Console\Commands\Tenants;

use Illuminate\Console\Command;
use Ometra\Caronte\Api\TenantApi;

class ListTenants extends Command
{
    protected $signature = 'caronte:tenants:list
        {--search= : Optional tenant id, external id, name or description filter}';

    protected $description = 'List Caronte tenants visible to the configured application.';

    public function handle(): int
    {
        try {
            $response = TenantApi::listTenants((string) $this->option('search'));
            $data = is_array($response['data']) ? $response['data'] : [];
            $tenants = is_array($data['tenants'] ?? null) ? $data['tenants'] : [];

            if ($tenants === []) {
                $this->warn('No tenants were returned by Caronte.');

                return self::SUCCESS;
            }

            $this->table(
                ['Tenant', 'External ID', 'Name', 'Status', 'Users'],
                array_map(fn(array $tenant): array => [
                    $tenant['tenant_id'] ?? '',
                    $tenant['external_id'] ?? '',
                    $tenant['name'] ?? '',
                    $tenant['status'] ?? '',
                    (string) ($tenant['users_count'] ?? 0),
                ], $tenants)
            );

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
