<?php

namespace Ometra\Caronte\Api;

use Ometra\Caronte\Support\LegacyDeprecation;

/**
 * @deprecated Use ScopeApi instead.
 * This compatibility API will be removed in the next major version.
 */
class PermissionApi
{
    /**
     * @deprecated Use ScopeApi::showScopes() instead.
     *
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function showPermissions(): array
    {
        LegacyDeprecation::warn('PermissionApi::showPermissions', 'ScopeApi::showScopes');

        return ScopeApi::showScopes();
    }

    /**
     * @deprecated Use ScopeApi::syncScopes() instead.
     *
     * @param  array<int, array{permission: string, description: string}>  $permissions
     * @return array{status: int, message: string, data: mixed, errors: array<int|string, mixed>}
     */
    public static function syncPermissions(array $permissions): array
    {
        LegacyDeprecation::warn('PermissionApi::syncPermissions', 'ScopeApi::syncScopes');

        return ScopeApi::syncScopes(
            array_map(
                fn(array $permission): array => [
                    'scope' => (string) ($permission['permission'] ?? $permission['scope'] ?? ''),
                    'description' => (string) ($permission['description'] ?? ''),
                ],
                $permissions
            )
        );
    }
}
