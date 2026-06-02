<?php

namespace Ometra\Caronte\Support;

use Equidna\BeeHive\Tenancy\TenantContext;
use InvalidArgumentException;

final class CaronteTenancy
{
    public const MODE_MULTI  = 'multi';
    public const MODE_SINGLE = 'single';

    public static function mode(): string
    {
        return strtolower(trim((string) config('caronte.tenancy.mode', self::MODE_MULTI)));
    }

    public static function isSingleTenant(): bool
    {
        return static::mode() === self::MODE_SINGLE;
    }

    public static function configuredTenantId(): ?string
    {
        $tenantId = trim((string) config('caronte.tenancy.tenant_id', ''));

        return $tenantId !== '' ? $tenantId : null;
    }

    public static function requireConfiguredTenantId(): string
    {
        $tenantId = static::configuredTenantId();

        if ($tenantId === null) {
            throw new InvalidArgumentException(
                'Caronte: caronte.tenancy.tenant_id is required when caronte.tenancy.mode is single.'
            );
        }

        return $tenantId;
    }

    public static function bindTenantContext(string $tenantId): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->set($tenantId);

        app()->instance(TenantContext::class, $tenantContext);
    }

    public static function validateConfig(): void
    {
        $mode = static::mode();

        if (! in_array($mode, [self::MODE_MULTI, self::MODE_SINGLE], true)) {
            throw new InvalidArgumentException(
                'Caronte: caronte.tenancy.mode must be either multi or single.'
            );
        }

        if ($mode === self::MODE_SINGLE) {
            static::requireConfiguredTenantId();
        }
    }
}
