<?php

namespace Ometra\Caronte\Support;

class CaronteProtectedApiAccessContext
{
    /**
     * @var array<int, string>
     */
    public readonly array $scopes;

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
    }

    public function hasScope(string $scope): bool
    {
        return in_array(strtolower(trim($scope)), $this->scopes, true);
    }

}
