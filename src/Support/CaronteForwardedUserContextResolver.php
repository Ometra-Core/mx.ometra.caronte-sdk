<?php

namespace Ometra\Caronte\Support;

use Illuminate\Http\Request;
use Ometra\Caronte\CaronteUserToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class CaronteForwardedUserContextResolver
{
    public function resolve(Request $request, bool $required): CaronteForwardedUserContext|Response|null
    {
        $userToken = trim((string) $request->header('X-User-Token'));

        if ($userToken === '') {
            return $required
                ? CaronteResponse::unauthorized(
                    message: 'No user token provided.',
                    errors: ['X-User-Token header is required.']
                )
                : null;
        }

        try {
            $token = CaronteUserToken::validateToken($userToken, skipExchange: true);
            $user = CaronteUserToken::userPayload($token);
        } catch (Throwable) {
            return CaronteResponse::unauthorized(
                message: 'Invalid user token.',
                errors: ['The provided X-User-Token is invalid.']
            );
        }

        $tenantId = isset($user->id_tenant) && trim((string) $user->id_tenant) !== ''
            ? trim((string) $user->id_tenant)
            : null;

        $tokenId = $token->claims()->has('jti')
            ? trim((string) $token->claims()->get('jti'))
            : null;

        return new CaronteForwardedUserContext(
            userToken: $userToken,
            user: $user,
            tenantId: $tenantId,
            tokenId: $tokenId !== '' ? $tokenId : null,
        );
    }
}
