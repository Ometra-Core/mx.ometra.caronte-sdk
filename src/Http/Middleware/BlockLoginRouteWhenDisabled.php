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
                $callbackUrl = (string) config('caronte.routes.success_url', (string) config('app.url', ''));

                if ($callbackUrl === '') {
                    $callbackUrl = (string) config('app.url', '');
                }

                if ($callbackUrl === '') {
                    return redirect()->away($redirectUrl);
                }

                $separator = str_contains($redirectUrl, '?') ? '&' : '?';

                return redirect()->away($redirectUrl . $separator . http_build_query([
                    'callback_url' => base64_encode($callbackUrl),
                ]));
            }

            abort(404);
        }

        return $next($request);
    }
}
