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
            $token = Caronte::getToken();
            $request->attributes->set('caronte.user_token', $token);
            $request->attributes->set('caronte.user', CaronteUserToken::userPayload($token));

            if (!PermissionHelper::hasApplication()) {
                Caronte::clearToken();

                return CaronteResponse::forbidden(
                    message: 'User does not have access to this application.',
                    errors: ['User does not have access to this application.'],
                    forwardUrl: $this->loginForwardUrl($request)
                );
            }

            if (CaronteTenancy::isSingleTenant()) {
                $configuredTenantId = CaronteTenancy::requireConfiguredTenantId();

                try {
                    $tokenTenantId = Caronte::getTenantId();
                } catch (Throwable) {
                    Caronte::clearToken();

                    return CaronteResponse::forbidden(
                        message: 'Tenant is required for this application.',
                        errors: ['Tenant is required for this application.'],
                        forwardUrl: $this->loginForwardUrl($request)
                    );
                }

                if ($tokenTenantId !== $configuredTenantId) {
                    Caronte::clearToken();

                    return CaronteResponse::forbidden(
                        message: 'Tenant mismatch.',
                        errors: ['Tenant mismatch.'],
                        forwardUrl: $this->loginForwardUrl($request)
                    );
                }

                CaronteTenancy::bindTenantContext($configuredTenantId);
            }

            $response = $next($request);

            if (
                Caronte::tokenWasExchanged()
                && RouteHelper::wantsJson()
            ) {
                $response->headers->set('X-User-Token', $token->toString());
            }

            return $response;
        } catch (Throwable $exception) {
            Caronte::clearToken();

            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                forwardUrl: $this->loginForwardUrl($request)
            );
        } finally {
            Caronte::resetTokenWasExchanged();
        }
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
