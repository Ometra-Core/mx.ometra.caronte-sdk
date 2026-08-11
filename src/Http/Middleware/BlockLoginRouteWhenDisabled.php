<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockLoginRouteWhenDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $loginRouteEnabled = (bool) config('caronte.routes.login_view_enabled', true);

        // Block package-provided login form (GET) when disabled by env.
        if (! $loginRouteEnabled && $request->isMethod('GET') && $request->is('login*')) {
            $redirectUrl = config('caronte.routes.login_url');

            if (is_string($redirectUrl) && $redirectUrl !== '') {
                return redirect()->away($redirectUrl);
            }

            abort(404);
        }

        return $next($request);
    }
}
