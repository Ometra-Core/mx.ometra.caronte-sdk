<?php

namespace Ometra\Caronte\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class CaronteTenantContextResolver
{
    public function resolve(
        Request $request,
        bool $required,
        ?CaronteForwardedUserContext $forwardedUserContext = null,
    ): ?Response
    {
        $tenantId = trim((string) $request->header('X-Tenant-Id'));
        $authenticatedTenantId = $forwardedUserContext?->tenantId;

        if (CaronteTenancy::isSingleTenant()) {
            $configuredTenantId = CaronteTenancy::requireConfiguredTenantId();

            if ($tenantId !== '' && $tenantId !== $configuredTenantId) {
                return CaronteResponse::forbidden(
                    message: 'Tenant mismatch.',
                    errors: ['Tenant mismatch.']
                );
            }

            if ($authenticatedTenantId !== null && $authenticatedTenantId !== $configuredTenantId) {
                return CaronteResponse::forbidden(
                    message: 'Tenant mismatch.',
                    errors: ['Tenant mismatch.']
                );
            }

            CaronteTenancy::bindTenantContext($configuredTenantId);

            return null;
        }

        if ($authenticatedTenantId !== null) {
            if ($tenantId !== '' && $tenantId !== $authenticatedTenantId) {
                return CaronteResponse::forbidden(
                    message: 'Tenant override is not allowed.',
                    errors: ['X-Tenant-Id must match the authenticated user tenant.']
                );
            }

            $tenantId = $authenticatedTenantId;
        }

        if ($tenantId === '') {
            return $required
                ? CaronteResponse::badRequest(
                    message: 'id_tenant is required',
                    errors: ['X-Tenant-Id header is required.']
                )
                : null;
        }

        CaronteTenancy::bindTenantContext($tenantId);

        return null;
    }
}
