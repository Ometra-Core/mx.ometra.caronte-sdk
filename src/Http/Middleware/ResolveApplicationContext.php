<?php

namespace Ometra\Caronte\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ometra\Caronte\Support\CaronteApplicationContext;
use Ometra\Caronte\Support\CaronteApplicationContextResolver;
use Ometra\Caronte\Support\CaronteForwardedUserContext;
use Ometra\Caronte\Support\CaronteForwardedUserContextResolver;
use Ometra\Caronte\Support\CaronteTenantContextResolver;
use Symfony\Component\HttpFoundation\Response;

class ResolveApplicationContext
{
    private const TENANT_REQUIRED = 'tenant_required';
    private const USER_REQUIRED = 'user_required';

    public function __construct(
        private readonly CaronteApplicationContextResolver $applicationContextResolver,
        private readonly CaronteForwardedUserContextResolver $forwardedUserContextResolver,
        private readonly CaronteTenantContextResolver $tenantContextResolver,
    ) {
        //
    }

    public function handle(Request $request, Closure $next, string ...$modes): Response
    {
        $applicationContext = $this->applicationContextResolver->resolve($request);

        if ($applicationContext instanceof Response) {
            return $applicationContext;
        }

        $modes = static::normalizeModes($modes);
        app()->instance(CaronteApplicationContext::class, $applicationContext);

        $forwardedUserContext = $this->forwardedUserContextResolver->resolve(
            request: $request,
            required: in_array(self::USER_REQUIRED, $modes, true)
        );

        if ($forwardedUserContext instanceof Response) {
            return $forwardedUserContext;
        }

        if ($forwardedUserContext instanceof CaronteForwardedUserContext) {
            app()->instance(CaronteForwardedUserContext::class, $forwardedUserContext);
        }

        $tenantResponse = $this->tenantContextResolver->resolve(
            request: $request,
            required: in_array(self::TENANT_REQUIRED, $modes, true),
            forwardedUserContext: $forwardedUserContext
        );

        if ($tenantResponse instanceof Response) {
            return $tenantResponse;
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $modes
     * @return array<int, string>
     */
    private static function normalizeModes(array $modes): array
    {
        return array_values(array_filter(array_map(
            fn(string $mode): string => strtolower(trim($mode)),
            $modes
        )));
    }
}
