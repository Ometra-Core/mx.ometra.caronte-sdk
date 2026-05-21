<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteApplicationContext;
use Ometra\Caronte\Support\CaronteForwardedUserContext;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\CaronteTenancy;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ResolveApplicationContext
{
    private const TENANT_REQUIRED = 'tenant_required';
    private const USER_REQUIRED = 'user_required';

    public function handle(Request $request, Closure $next, string ...$modes): Response
    {
        $applicationToken = trim((string) $request->header('X-Application-Token'));
        $groupToken       = trim((string) $request->header('X-Group-Token'));

        if ($applicationToken === '' && $groupToken === '') {
            return CaronteResponse::unauthorized(
                message: 'No application token provided.',
                errors: ['X-Application-Token or X-Group-Token header is required.']
            );
        }

        if ($groupToken !== '') {
            if ($applicationToken === '') {
                return CaronteResponse::unauthorized(
                    message: 'No application token provided.',
                    errors: ['X-Application-Token header is required when X-Group-Token is provided.']
                );
            }

            try {
                $decodedApplicationToken = CaronteApplicationToken::decodeApplicationToken($applicationToken);
                $validatedGroupToken = CaronteApplicationToken::validateGroupToken($groupToken);
            } catch (Throwable) {
                return CaronteResponse::unauthorized(
                    message: 'Invalid application token.',
                    errors: ['The provided X-Application-Token or X-Group-Token is invalid.']
                );
            }

            $sourceAppId = (string) $validatedGroupToken->claims()->get('source_app_id');
            $sourceAppCn = (string) $validatedGroupToken->claims()->get('source_app_cn');

            app()->instance(CaronteApplicationContext::class, new CaronteApplicationContext(
                appCn: CaronteApplicationToken::cn(),
                appId: CaronteApplicationToken::appId(),
                applicationToken: $applicationToken,
                authenticatedAsGroup: true,
                groupId: (string) $validatedGroupToken->claims()->get('group_id'),
                sourceAppId: $sourceAppId,
                sourceAppCn: $sourceAppCn,
                groupTokenId: (string) $validatedGroupToken->claims()->get('jti'),
                applicationTokenId: (string) $decodedApplicationToken->claims()->get('jti'),
            ));
        } else {
            try {
                $validatedApplicationToken = CaronteApplicationToken::validateApplicationToken($applicationToken);
            } catch (Throwable) {
                return CaronteResponse::unauthorized(
                    message: 'Invalid application token.',
                    errors: ['The provided X-Application-Token does not match the configured application.']
                );
            }

            app()->instance(CaronteApplicationContext::class, new CaronteApplicationContext(
                appCn: (string) $validatedApplicationToken->claims()->get('app_cn'),
                appId: (string) $validatedApplicationToken->claims()->get('app_id'),
                applicationToken: $applicationToken,
                authenticatedAsGroup: false,
                groupId: null,
                sourceAppId: (string) $validatedApplicationToken->claims()->get('app_id'),
                sourceAppCn: (string) $validatedApplicationToken->claims()->get('app_cn'),
                groupTokenId: null,
                applicationTokenId: (string) $validatedApplicationToken->claims()->get('jti'),
            ));
        }

        $modes = static::normalizeModes($modes);
        $forwardedUserContext = static::resolveForwardedUserContext(
            request: $request,
            required: in_array(self::USER_REQUIRED, $modes, true)
        );

        if ($forwardedUserContext instanceof Response) {
            return $forwardedUserContext;
        }

        $tenantResponse = static::resolveTenant(
            request: $request,
            required: in_array(self::TENANT_REQUIRED, $modes, true),
            forwardedUserContext: $forwardedUserContext
        );

        if ($tenantResponse instanceof Response) {
            return $tenantResponse;
        }

        return $next($request);
    }

    public static function resolveTenant(
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
                    message: 'tenant_id is required',
                    errors: ['X-Tenant-Id header is required.']
                )
                : null;
        }

        CaronteTenancy::bindTenantContext($tenantId);

        return null;
    }

    /**
     * @param  array<int, string>  $modes
     * @return array<int, string>
     */
    private static function normalizeModes(array $modes): array
    {
        return array_values(array_filter(array_map(
            fn(string $mode): string => strtolower(trim($mode)),
            $modes
        )));
    }

    private static function resolveForwardedUserContext(
        Request $request,
        bool $required,
    ): CaronteForwardedUserContext|Response|null
    {
        $userToken = trim((string) $request->header('X-User-Token'));

        if ($userToken === '') {
            return $required
                ? CaronteResponse::unauthorized(
                    message: 'No user token provided.',
                    errors: ['X-User-Token header is required.']
                )
                : null;
        }

        try {
            $token = CaronteUserToken::validateToken($userToken, skipExchange: true);
            $user = CaronteUserToken::userPayload($token);
        } catch (Throwable) {
            return CaronteResponse::unauthorized(
                message: 'Invalid user token.',
                errors: ['The provided X-User-Token is invalid.']
            );
        }

        $tenantId = isset($user->tenant_id) && trim((string) $user->tenant_id) !== ''
            ? trim((string) $user->tenant_id)
            : null;

        $tokenId = $token->claims()->has('jti')
            ? trim((string) $token->claims()->get('jti'))
            : null;

        $context = new CaronteForwardedUserContext(
            userToken: $userToken,
            user: $user,
            tenantId: $tenantId,
            tokenId: $tokenId !== '' ? $tokenId : null,
        );

        app()->instance(CaronteForwardedUserContext::class, $context);

        return $context;
    }
}
