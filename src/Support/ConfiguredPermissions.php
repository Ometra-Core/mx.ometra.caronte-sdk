<?php

namespace Ometra\Caronte\Support;

/**
 * @deprecated Use ConfiguredScopes instead.
 * This compatibility class will be removed in the next major version.
 */
class ConfiguredPermissions
{
    /**
     * @return array<int, array{permission: string, description: string}>
     */
    public static function all(): array
    {
        return array_map(
            fn(array $scope): array => [
                'permission' => $scope['scope'],
                'description' => $scope['description'],
            ],
            ConfiguredScopes::all()
        );
    }
}
