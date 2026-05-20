<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\CaronteProtectedApiAccessToken;
use Ometra\Caronte\Support\CaronteApplicationAccessContext;
use Ometra\Caronte\Support\CaronteProtectedApiAccessContext;
use Ometra\Caronte\Support\CaronteResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateProtectedApiAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $rawToken = (string) $request->bearerToken();
            $context = CaronteProtectedApiAccessToken::validateToken($rawToken);

            app()->instance(CaronteProtectedApiAccessContext::class, $context);
            app()->instance(CaronteApplicationAccessContext::class, $context);

            return $next($request);
        } catch (Throwable $exception) {
            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                errors: [$exception->getMessage()]
            );
        }
    }
}
