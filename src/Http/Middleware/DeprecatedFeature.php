<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\Support\LegacyDeprecation;
use Symfony\Component\HttpFoundation\Response;

final class DeprecatedFeature
{
    public function handle(Request $request, Closure $next, string $feature, string $replacement): Response
    {
        LegacyDeprecation::warn(str_replace('_', ' ', $feature), str_replace('_', ' ', $replacement));

        $response = $next($request);
        $response->headers->set('Deprecation', 'true');

        return $response;
    }
}
