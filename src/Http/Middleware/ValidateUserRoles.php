<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\Helpers\PermissionHelper;
use Ometra\Caronte\Support\CaronteResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateUserRoles
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            if (!PermissionHelper::hasRoles($roles)) {
                return CaronteResponse::forbidden(
                    message: 'User does not have access to this feature.',
                    errors: ['User does not have the required roles: ' . implode(', ', $roles)],
                    forwardUrl: $this->resolveFeatureFallbackUrl($request)
                );
            }

            return $next($request);
        } catch (Throwable $exception) {
            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                forwardUrl: (string) config('caronte.routes.login_url')
            );
        }
    }

    private function resolveFeatureFallbackUrl(Request $request): string
    {
        $previous = trim((string) url()->previous());
        $current = url()->current();

        if ($previous !== '' && $previous !== 'about:blank' && $previous !== $current) {
            return $previous;
        }

        $successUrl = trim((string) config('caronte.routes.success_url'));

        if ($successUrl !== '') {
            return $successUrl;
        }

        return $this->loginForwardUrl($request);
    }

    private function loginForwardUrl(Request $request): string
    {
        $loginUrl = (string) config('caronte.routes.login_url');

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $loginUrl;
        }

        $separator = str_contains($loginUrl, '?') ? '&' : '?';

        return $loginUrl . $separator . http_build_query([
            'callback_url' => base64_encode($request->fullUrl()),
        ]);
    }
}
