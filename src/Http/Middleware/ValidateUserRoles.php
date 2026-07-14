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
                    forwardUrl: (string) config('caronte.routes.login_url')
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
}
