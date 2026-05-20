<?php

namespace Ometra\Caronte\Api;

class ScopeApi
{
    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function showScopes(): array
    {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'get',
            endpoint: 'api/applications/scopes'
        );
    }

    /**
     * @param  array<int, array{scope: string, description: string}>  $scopes
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function syncScopes(array $scopes): array
    {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'put',
            endpoint: 'api/applications/scopes',
            payload: [
                'scopes' => array_values($scopes),
            ]
        );
    }
}
