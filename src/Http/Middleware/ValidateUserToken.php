<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Equidna\Toolkit\Helpers\RouteHelper;
use Illuminate\Http\Request;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Helpers\PermissionHelper;
use Ometra\Caronte\Support\CaronteResponse;
use Ometra\Caronte\Support\CaronteTenancy;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        Caronte::resetTokenWasExchanged();

        try {
            if (! RouteHelper::isApi()) {
                $tenantResolution = $this->resolveWebTenant($request);
                if ($tenantResolution instanceof Response) {
                    return $tenantResolution;
                }
            }

            $token = Caronte::getToken();
            $request->attributes->set('caronte.user_token', $token);
            $request->attributes->set('caronte.user', CaronteUserToken::userPayload($token));

            if (!PermissionHelper::hasApplication()) {
                Caronte::clearCurrentToken();

                return CaronteResponse::forbidden(
                    message: 'User does not have access to this application.',
                    errors: ['User does not have access to this application.'],
                    forwardUrl: $this->loginForwardUrl($request)
                );
            }

            try {
                $tokenTenantId = Caronte::getTenantId();
            } catch (Throwable) {
                Caronte::clearCurrentToken();

                return CaronteResponse::forbidden(
                    message: 'Tenant is required for this application.',
                    errors: ['Tenant is required for this application.'],
                    forwardUrl: $this->loginForwardUrl($request)
                );
            }

            $requestedTenantId = $request->attributes->get('caronte.current_tenant_id');
            if (is_string($requestedTenantId) && $requestedTenantId !== $tokenTenantId) {
                return CaronteResponse::forbidden(
                    message: 'Tenant override is not allowed.',
                    errors: ['The selected tenant is not available in this session.']
                );
            }

            if (CaronteTenancy::isSingleTenant()) {
                $configuredTenantId = CaronteTenancy::requireConfiguredTenantId();

                if ($tokenTenantId !== $configuredTenantId) {
                    Caronte::clearCurrentToken();

                    return CaronteResponse::forbidden(
                        message: 'Tenant mismatch.',
                        errors: ['Tenant mismatch.'],
                        forwardUrl: $this->loginForwardUrl($request)
                    );
                }
            }

            CaronteTenancy::bindTenantContext($tokenTenantId);

            $response = $next($request);

            if (
                Caronte::tokenWasExchanged()
                && RouteHelper::wantsJson()
            ) {
                $response->headers->set('X-User-Token', $token->toString());
            }

            return $response;
        } catch (Throwable $exception) {
            Caronte::clearCurrentToken();

            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                forwardUrl: $this->loginForwardUrl($request)
            );
        } finally {
            Caronte::resetTokenWasExchanged();
        }
    }

    private function resolveWebTenant(Request $request): ?Response
    {
        $queryTenant = $request->query('id_tenant');
        $inputTenantId = is_string($queryTenant) ? trim($queryTenant) : '';
        $headerTenantId = trim((string) $request->header('X-Tenant-Id', ''));

        if ($inputTenantId !== '' && $headerTenantId !== '' && $inputTenantId !== $headerTenantId) {
            return CaronteResponse::forbidden(
                message: 'Conflicting tenant selection.',
                errors: ['id_tenant and X-Tenant-Id must match.']
            );
        }

        $requestedTenantId = $inputTenantId !== '' ? $inputTenantId : $headerTenantId;
        $portfolio = Caronte::tenantTokenPortfolio();
        if ($portfolio === []) {
            if ($requestedTenantId !== '') {
                $request->attributes->set('caronte.current_tenant_id', $requestedTenantId);
            }
            return null;
        }

        if ($requestedTenantId !== '' && ! isset($portfolio[$requestedTenantId])) {
            return CaronteResponse::forbidden(
                message: 'Tenant is not available in this session.',
                errors: ['The selected tenant is not available in this session.']
            );
        }

        if ($requestedTenantId === '') {
            $lastTenantId = $request->session()->get(
                (string) config('caronte.last_tenant_session_key', 'caronte.last_tenant_id')
            );
            $requestedTenantId = is_string($lastTenantId) && isset($portfolio[$lastTenantId])
                ? $lastTenantId
                : (string) array_key_first($portfolio);
        } else {
            $request->session()->put(
                (string) config('caronte.last_tenant_session_key', 'caronte.last_tenant_id'),
                $requestedTenantId
            );
        }

        $request->attributes->set('caronte.current_tenant_id', $requestedTenantId);

        return null;
    }

    private function loginForwardUrl(Request $request): string
    {
        $loginUrl = (string) config('caronte.routes.login_url');

        if (! $this->shouldRememberIntendedUrl($request)) {
            return $loginUrl;
        }

        $separator = str_contains($loginUrl, '?') ? '&' : '?';

        return $loginUrl . $separator . http_build_query([
            'callback_url' => base64_encode($request->fullUrl()),
        ]);
    }

    private function shouldRememberIntendedUrl(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD'], true)
            && ! RouteHelper::wantsJson();
    }
}
