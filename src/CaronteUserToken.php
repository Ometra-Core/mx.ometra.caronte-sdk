<?php

namespace Ometra\Caronte;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Equidna\Toolkit\Helpers\RouteHelper;
use Illuminate\Support\Arr;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ometra\Caronte\Api\AuthApi;
use Ometra\Caronte\Exceptions\CaronteApiException;
use Ometra\Caronte\Facades\Caronte;
use Ometra\Caronte\Oidc\Base64Url;
use Ometra\Caronte\Oidc\OidcClient;
use Ometra\Caronte\Oidc\OidcTokenValidator;
use Ometra\Caronte\Support\CaronteApplicationToken;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

final class CaronteUserToken
{
    public const MINIMUM_KEY_LENGTH = 32;

    private static bool $exchanging = false;

    public static function validateToken(string $rawToken, bool $skipExchange = false): Plain
    {
        if (static::shouldUseOidc($rawToken)) {
            return static::validateOidcToken($rawToken, $skipExchange);
        }

        $token = static::decodeToken($rawToken);

        static::assertSignatureAndIssuer($token);
        static::assertApplicationClaim($token);

        if (static::shouldRefresh($token)) {
            if (static::isExpired($token) && ($skipExchange || static::$exchanging)) {
                throw new UnprocessableEntityException('Token has expired. Please login again.');
            }

            if (! $skipExchange && ! static::$exchanging) {
                return static::exchangeToken($rawToken);
            }
        }

        static::assertNotBefore($token);

        if (config('caronte.update_local_user')) {
            Caronte::updateUserData(static::userPayload($token));
        }

        return $token;
    }

    public static function exchangeToken(string $rawToken): Plain
    {
        if (static::$exchanging) {
            throw new UnprocessableEntityException('Token exchange already in progress.');
        }

        static::$exchanging = true;

        try {
            $response = AuthApi::exchange($rawToken);

            $tokenString = Arr::get($response, 'data.token');

            if (!is_string($tokenString) || $tokenString === '') {
                throw new UnprocessableEntityException('Caronte did not return a refreshed token.');
            }

            $token = static::validateToken($tokenString, skipExchange: true);

            if (static::isWebRequest()) {
                Caronte::saveToken($token->toString());
            }

            Caronte::setTokenWasExchanged();

            return $token;
        } catch (CaronteApiException $exception) {
            if (static::isStillValidExchangeRejection($exception)) {
                return static::currentValidToken($rawToken);
            }

            Caronte::clearCurrentToken();

            throw new UnprocessableEntityException(
                'Cannot exchange token: ' . $exception->getMessage(),
                previous: $exception
            );
        } finally {
            static::$exchanging = false;
        }
    }

    private static function validateOidcToken(string $rawToken, bool $skipExchange): Plain
    {
        if (! static::oidcShouldRefresh($rawToken)) {
            return app(OidcTokenValidator::class)->validate($rawToken);
        }

        if ($skipExchange || static::$exchanging) {
            return app(OidcTokenValidator::class)->validate($rawToken);
        }

        return static::refreshOidcToken($rawToken);
    }

    private static function refreshOidcToken(string $rawToken): Plain
    {
        $refreshToken = static::oidcRefreshToken();

        if ($refreshToken === null) {
            return app(OidcTokenValidator::class)->validate($rawToken);
        }

        static::$exchanging = true;

        try {
            $tokens = app(OidcClient::class)->refresh($refreshToken);
            $idToken = (string) ($tokens['id_token'] ?? '');

            if ($idToken === '') {
                throw new UnprocessableEntityException('Caronte did not return a refreshed OIDC token.');
            }

            $token = app(OidcTokenValidator::class)->validate($idToken);
            Caronte::saveToken($idToken);

            $newRefreshToken = (string) ($tokens['refresh_token'] ?? '');

            if ($newRefreshToken !== '' && app()->bound('session')) {
                request()->session()->put('caronte.oidc.refresh_token', $newRefreshToken);
            }

            Caronte::setTokenWasExchanged();

            return $token;
        } catch (\Throwable $exception) {
            Caronte::clearToken();

            if (app()->bound('session')) {
                request()->session()->forget('caronte.oidc.refresh_token');
            }

            if ($exception instanceof UnprocessableEntityException) {
                throw $exception;
            }

            throw new UnprocessableEntityException(
                'Cannot refresh OIDC token: ' . $exception->getMessage(),
                previous: $exception
            );
        } finally {
            static::$exchanging = false;
        }
    }

    public static function decodeToken(string $rawToken): Plain
    {
        if ($rawToken === '') {
            throw new BadRequestException('Token not provided');
        }

        if (count(explode('.', $rawToken)) !== 3) {
            throw new BadRequestException('Malformed token');
        }

        $token = static::getConfig()->parser()->parse($rawToken);

        if (!$token instanceof Plain) {
            throw new BadRequestException('Invalid token');
        }

        foreach (['iss', 'aud', 'sub', 'jti', 'iat', 'nbf', 'exp', 'token_audience', 'id_tenant'] as $claim) {
            if (! $token->claims()->has($claim)) {
                throw new UnprocessableEntityException('Invalid token: missing required claim ' . $claim . '.');
            }
        }

        foreach (['iss', 'sub', 'jti', 'token_audience'] as $claim) {
            if (trim((string) $token->claims()->get($claim, '')) === '') {
                throw new UnprocessableEntityException('Invalid token: claim ' . $claim . ' must not be empty.');
            }
        }

        foreach (['iat', 'nbf', 'exp'] as $claim) {
            if (! $token->claims()->get($claim) instanceof DateTimeImmutable) {
                throw new UnprocessableEntityException('Invalid token: claim ' . $claim . ' must be a date.');
            }
        }

        $issuedAt = $token->claims()->get('iat');
        $expiresAt = $token->claims()->get('exp');

        if ($expiresAt <= $issuedAt) {
            throw new UnprocessableEntityException('Invalid token: exp must be after iat.');
        }

        return $token;
    }

    public static function userPayload(Plain $token): stdClass
    {
        return static::explicitUserPayload($token);
    }

    private static function shouldUseOidc(string $rawToken): bool
    {
        $mode = (string) config('caronte.auth_mode', 'jwt');

        if ($mode === 'jwt') {
            return false;
        }

        if ($mode === 'oidc') {
            return true;
        }

        if ($mode !== 'dual') {
            throw new InvalidArgumentException('Caronte: auth_mode must be jwt, oidc, or dual.');
        }

        $parts = explode('.', $rawToken);

        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode((string) Base64Url::decode($parts[0]), true);

        return is_array($header) && isset($header['kid']);
    }

    private static function oidcRefreshToken(): ?string
    {
        if (! app()->bound('session')) {
            return null;
        }

        $refreshToken = request()->session()->get('caronte.oidc.refresh_token');

        return is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
    }

    private static function explicitUserPayload(Plain $token): stdClass
    {
        $subject = (string) $token->claims()->get('sub', '');
        $tenantId = $token->claims()->get('id_tenant', null);

        $user = new stdClass();
        $user->uri_user = $subject;
        $user->name = (string) $token->claims()->get('name', '');
        $user->email = (string) $token->claims()->get('email', '');
        $user->id_tenant = $tenantId;
        $user->tenant_name = (string) $token->claims()->get('tenant_name', '');
        $user->roles = static::normalizeClaimItems($token->claims()->get('roles', []));
        $user->metadata = static::normalizeClaimItems($token->claims()->get('metadata', []));

        return $user;
    }

    /**
     * @return list<object>
     */
    private static function normalizeClaimItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn(mixed $item): object => is_array($item)
                ? (object) $item
                : (is_object($item) ? $item : (object) ['name' => (string) $item]),
            $items
        ));
    }

    public static function getConfig(): Configuration
    {
        return static::configForSigningKey(
            signingKey: (string) config('caronte.app_secret'),
            configName: 'CARONTE_APP_SECRET'
        );
    }

    public static function getGroupConfig(): Configuration
    {
        return static::configForSigningKey(
            signingKey: (string) config('caronte.application_group_secret'),
            configName: 'CARONTE_APPLICATION_GROUP_SECRET'
        );
    }

    private static function configForSigningKey(string $signingKey, string $configName): Configuration
    {

        if (mb_strlen($signingKey) < static::MINIMUM_KEY_LENGTH) {
            throw new RuntimeException(
                sprintf(
                    '%s must be at least %d characters long. Current length: %d.',
                    $configName,
                    static::MINIMUM_KEY_LENGTH,
                    mb_strlen($signingKey)
                )
            );
        }

        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($signingKey)
        );
    }

    private static function assertSignatureAndIssuer(Plain $token): void
    {
        $config = static::configForToken($token);
        $validator = $config->validator();
        $constraints = [
            new SignedWith($config->signer(), $config->signingKey()),
        ];

        $constraints[] = new IssuedBy((string) config('caronte.issuer_id'));

        if (!$validator->validate($token, ...$constraints)) {
            throw new UnprocessableEntityException('Invalid token signature or issuer.');
        }
    }

    private static function assertApplicationClaim(Plain $token): void
    {
        $audience = (string) $token->claims()->get('token_audience', '');

        if ($audience === 'application_group') {
            $groupId = (string) $token->claims()->get('group_id', '');
            $appId = trim((string) $token->claims()->get('app_id', ''));
            $sourceAppId = trim((string) $token->claims()->get('source_app_id', ''));

            if (
                $groupId === ''
                || $groupId !== CaronteApplicationToken::groupId()
                || ! $token->isPermittedFor($groupId)
            ) {
                throw new UnprocessableEntityException('Token application group does not match the configured Caronte application group.');
            }

            if ($appId === '' || $sourceAppId === '' || ! hash_equals($appId, $sourceAppId)) {
                throw new UnprocessableEntityException('Token source application is invalid.');
            }

            return;
        }

        if ($audience !== 'application') {
            throw new UnprocessableEntityException('Invalid token audience.');
        }

        $appId = trim((string) $token->claims()->get('app_id', ''));
        if (
            $appId === ''
            || $appId !== CaronteApplicationToken::appId()
            || ! $token->isPermittedFor($appId)
        ) {
            throw new UnprocessableEntityException('Token application does not match the configured Caronte application.');
        }
    }

    private static function assertNotBefore(Plain $token): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $leewaySeconds = (int) config('caronte.token.clock_skew_seconds', 60);

        foreach (['iat', 'nbf'] as $claim) {
            if (!$token->claims()->has($claim)) {
                continue;
            }

            $value = $token->claims()->get($claim);

            if (
                $value instanceof DateTimeImmutable
                && $value->getTimestamp() > ($now->getTimestamp() + $leewaySeconds)
            ) {
                throw new UnprocessableEntityException('Token is not yet valid.');
            }
        }
    }

    private static function isExpired(Plain $token): bool
    {
        $expiresAt = $token->claims()->get('exp');
        $clockSkew = max(0, (int) config('caronte.token.clock_skew_seconds', 60));

        return $expiresAt instanceof DateTimeImmutable
            && $expiresAt->getTimestamp() <= (
                (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp() - $clockSkew
            );
    }

    private static function shouldRefresh(Plain $token): bool
    {
        $expiresAt = $token->claims()->get('exp');

        return $expiresAt instanceof DateTimeImmutable
            && $expiresAt->getTimestamp() <= (
                (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp()
                + static::refreshLeewaySeconds()
            );
    }

    private static function oidcShouldRefresh(string $rawToken): bool
    {
        try {
            $token = (new Parser(new JoseEncoder()))->parse($rawToken);
        } catch (\Throwable) {
            return false;
        }

        return $token instanceof Plain && static::shouldRefresh($token);
    }

    private static function refreshLeewaySeconds(): int
    {
        return max(0, (int) config('caronte.token.refresh_leeway_seconds', 60));
    }

    private static function isStillValidExchangeRejection(CaronteApiException $exception): bool
    {
        return $exception->getCode() === 401
            && strcasecmp($exception->getMessage(), 'Token is still valid') === 0;
    }

    private static function currentValidToken(string $rawToken): Plain
    {
        $token = static::decodeToken($rawToken);

        static::assertSignatureAndIssuer($token);
        static::assertApplicationClaim($token);
        static::assertNotBefore($token);

        if (static::isExpired($token)) {
            throw new UnprocessableEntityException('Token has expired. Please login again.');
        }

        if (config('caronte.update_local_user')) {
            Caronte::updateUserData(static::userPayload($token));
        }

        return $token;
    }

    private static function isWebRequest(): bool
    {
        return RouteHelper::isWeb();
    }

    private static function configForToken(Plain $token): Configuration
    {
        return match ((string) $token->claims()->get('token_audience', '')) {
            'application' => static::getConfig(),
            'application_group' => static::getGroupConfig(),
            default => throw new UnprocessableEntityException('Invalid token audience.'),
        };
    }
}
