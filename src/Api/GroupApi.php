<?php

namespace Ometra\Caronte\Api;

class GroupApi
{
    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function showGroupRoles(): array
    {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'get',
            endpoint: 'api/application-groups/current/roles'
        );
    }

    /**
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function showGroupUsers(string $search = ''): array
    {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'get',
            endpoint: 'api/application-groups/current/users',
            query: [
                'search' => $search,
            ]
        );
    }

    /**
     * @param  array<int, string>  $roleUris
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function syncGroupUserRoles(
        string $uriUser,
        string $appId,
        array $roleUris,
        ?string $actorToken = null
    ): array {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'put',
            endpoint: 'api/application-groups/current/users/' . $uriUser . '/applications/' . $appId . '/roles',
            payload: [
                'roles' => array_values(array_unique($roleUris)),
            ],
            userToken: $actorToken
        );
    }
}
