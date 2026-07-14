<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\Support\LegacyDeprecation;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Use ValidateProtectedApiScopes instead.
 * This compatibility middleware will be removed in the next major version.
 */
class ValidateApplicationAccessPermissions extends ValidateProtectedApiScopes
{
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        LegacyDeprecation::warn('caronte.app-permissions middleware', 'caronte.protected-api-scopes');

        $response = parent::handle($request, $next, ...$scopes);
        $response->headers->set('Deprecation', 'true');

        return $response;
    }
}
