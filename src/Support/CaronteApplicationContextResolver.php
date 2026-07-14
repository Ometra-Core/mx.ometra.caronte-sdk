<?php

namespace Ometra\Caronte\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class CaronteApplicationContextResolver
{
    public function resolve(Request $request): CaronteApplicationContext|Response
    {
        $applicationToken = trim((string) $request->header('X-Application-Token'));
        $groupToken       = trim((string) $request->header('X-Group-Token'));

        if ($applicationToken !== '' && $groupToken !== '') {
            return CaronteResponse::badRequest(
                message: 'Ambiguous application credentials.',
                errors: ['Send either X-Application-Token or X-Group-Token, never both.']
            );
        }

        if ($applicationToken === '' && $groupToken === '') {
            return CaronteResponse::unauthorized(
                message: 'No application token provided.',
                errors: ['X-Application-Token or X-Group-Token header is required.']
            );
        }

        if ($groupToken !== '') {
            return $this->resolveGroupContext($groupToken);
        }

        return $this->resolveApplicationContext($applicationToken);
    }

    private function resolveGroupContext(string $groupToken): CaronteApplicationContext|Response
    {
        try {
            $validatedGroupToken = CaronteApplicationToken::validateGroupToken($groupToken);
        } catch (Throwable) {
            return CaronteResponse::unauthorized(
                message: 'Invalid application group token.',
                errors: ['The provided X-Group-Token is invalid.']
            );
        }

        $sourceAppId = (string) $validatedGroupToken->claims()->get('source_app_id');
        $sourceAppCn = (string) $validatedGroupToken->claims()->get('source_app_cn');

        return new CaronteApplicationContext(
            appCn: CaronteApplicationToken::cn(),
            appId: CaronteApplicationToken::appId(),
            applicationToken: null,
            authenticatedAsGroup: true,
            groupId: (string) $validatedGroupToken->claims()->get('group_id'),
            sourceAppId: $sourceAppId,
            sourceAppCn: $sourceAppCn,
            groupTokenId: (string) $validatedGroupToken->claims()->get('jti'),
            applicationTokenId: null,
        );
    }

    private function resolveApplicationContext(string $applicationToken): CaronteApplicationContext|Response
    {
        try {
            $validatedApplicationToken = CaronteApplicationToken::validateApplicationToken($applicationToken);
        } catch (Throwable) {
            return CaronteResponse::unauthorized(
                message: 'Invalid application token.',
                errors: ['The provided X-Application-Token does not match the configured application.']
            );
        }

        return new CaronteApplicationContext(
            appCn: (string) $validatedApplicationToken->claims()->get('app_cn'),
            appId: (string) $validatedApplicationToken->claims()->get('app_id'),
            applicationToken: $applicationToken,
            authenticatedAsGroup: false,
            groupId: null,
            sourceAppId: (string) $validatedApplicationToken->claims()->get('app_id'),
            sourceAppCn: (string) $validatedApplicationToken->claims()->get('app_cn'),
            groupTokenId: null,
            applicationTokenId: (string) $validatedApplicationToken->claims()->get('jti'),
        );
    }
}
