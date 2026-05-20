<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\Support\CaronteProtectedApiAccessContext;
use Ometra\Caronte\Support\CaronteResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateProtectedApiScopes
{
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        try {
            /** @var CaronteProtectedApiAccessContext $context */
            $context = app(CaronteProtectedApiAccessContext::class);

            $required = collect($scopes)
                ->map(fn(mixed $scope): string => strtolower(trim((string) $scope)))
                ->filter()
                ->values();

            if ($required->isNotEmpty() && $required->contains(fn(string $scope): bool => ! $context->hasScope($scope))) {
                return CaronteResponse::forbidden(
                    message: 'Protected API Access Token does not have access to this feature.',
                    errors: ['Protected API Access Token does not have the required scopes: ' . $required->implode(', ')]
                );
            }

            return $next($request);
        } catch (Throwable $exception) {
            return CaronteResponse::unauthorized(
                message: $exception->getMessage(),
                errors: [$exception->getMessage()]
            );
        }
    }
}
