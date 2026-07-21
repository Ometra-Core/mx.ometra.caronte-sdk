<?php

namespace Ometra\Caronte\Api;

class GroupApi
{
    /**
     * List public identities and roles for applications in the configured group.
     *
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function showGroup(): array
    {
        return app(CaronteApiClient::class)->applicationRequest(
            method: 'get',
            endpoint: 'api/group'
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
            endpoint: 'api/group/users/' . $uriUser . '/applications/' . $appId . '/roles',
            payload: [
                'roles' => array_values(array_unique($roleUris)),
            ],
            userToken: $actorToken
        );
    }
}
