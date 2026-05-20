<?php

namespace Ometra\Caronte;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\Plain;
use Ometra\Caronte\Support\CaronteApplicationAccessContext;

/**
 * @deprecated Use CaronteProtectedApiAccessToken instead.
 * This compatibility class will be removed in the next major version.
 */
class CaronteApplicationAccessToken
{
    public static function validateToken(string $rawToken): CaronteApplicationAccessContext
    {
        $context = CaronteProtectedApiAccessToken::validateToken($rawToken);

        return $context instanceof CaronteApplicationAccessContext
            ? $context
            : new CaronteApplicationAccessContext(
                tokenId: $context->tokenId,
                appId: $context->appId,
                tenantId: $context->tenantId,
                name: $context->name,
                scopes: $context->scopes,
            );
    }

    public static function decodeToken(string $rawToken): Plain
    {
        return CaronteProtectedApiAccessToken::decodeToken($rawToken);
    }

    public static function getConfig(): Configuration
    {
        return CaronteProtectedApiAccessToken::getConfig();
    }
}
