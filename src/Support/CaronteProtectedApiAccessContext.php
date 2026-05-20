<?php

namespace Ometra\Caronte\Support;

class CaronteProtectedApiAccessContext
{
    /**
     * @var array<int, string>
     */
    public readonly array $scopes;

    /**
     * @deprecated Use $scopes instead. This alias will be removed in the next major version.
     *
     * @var array<int, string>
     */
    public readonly array $permissions;

    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly string $appId,
        public readonly string $tenantId,
        public readonly string $name,
        array $scopes,
    ) {
        $this->scopes = $scopes;
        $this->permissions = $scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array(strtolower(trim($scope)), $this->scopes, true);
    }

    /**
     * @deprecated Use hasScope() instead. This alias will be removed in the next major version.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->hasScope($permission);
    }
}
