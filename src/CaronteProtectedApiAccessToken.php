<?php

namespace Ometra\Caronte;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Ometra\Caronte\Support\CaronteApplicationAccessContext;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteProtectedApiAccessContext;
use Ometra\Caronte\Support\LegacyDeprecation;
use RuntimeException;

final class CaronteProtectedApiAccessToken
{
    public const MINIMUM_KEY_LENGTH = 32;

    public const AUDIENCE = 'protected_api_access';

    public const LEGACY_AUDIENCE = 'application_token';

    public static function validateToken(string $rawToken): CaronteProtectedApiAccessContext
    {
        $token = static::decodeToken($rawToken);
        static::assertSignatureAndIssuer($token);
        static::assertApplicationClaim($token);
        static::assertTemporalClaims($token);

        $scopes = static::scopeClaims($token);

        return new CaronteApplicationAccessContext(
            tokenId: (string) $token->claims()->get('jti'),
            appId: (string) $token->claims()->get('app_id'),
            tenantId: (string) $token->claims()->get('tenant_id'),
            name: (string) $token->claims()->get('name', ''),
            scopes: collect($scopes)
                ->map(fn(mixed $scope): string => strtolower(trim((string) $scope)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    public static function decodeToken(string $rawToken): Plain
    {
        if ($rawToken === '') {
            throw new BadRequestException('Protected API Access Token not provided');
        }

        if (count(explode('.', $rawToken)) !== 3) {
            throw new BadRequestException('Malformed Protected API Access Token');
        }

        $token = static::getConfig()->parser()->parse($rawToken);

        if (! $token instanceof Plain) {
            throw new BadRequestException('Invalid Protected API Access Token');
        }

        foreach (['jti', 'app_id', 'tenant_id', 'token_audience'] as $claim) {
            if (! $token->claims()->has($claim)) {
                throw new UnprocessableEntityException('Invalid Protected API Access Token');
            }
        }

        $audience = (string) $token->claims()->get('token_audience', '');

        if (! in_array($audience, [static::AUDIENCE, static::LEGACY_AUDIENCE], true)) {
            throw new UnprocessableEntityException('Invalid Protected API Access Token audience.');
        }

        if ($audience === static::LEGACY_AUDIENCE) {
            LegacyDeprecation::warn('protected API audience application_token', 'protected_api_access');
        }

        if ($audience === static::AUDIENCE) {
            foreach (['iss', 'aud', 'iat', 'nbf', 'exp'] as $claim) {
                if (! $token->claims()->has($claim)) {
                    throw new UnprocessableEntityException('Invalid Protected API Access Token');
                }
            }
        }

        static::scopeClaims($token);

        return $token;
    }

    public static function getConfig(): Configuration
    {
        $signingKey = (string) config('caronte.app_secret');

        if (mb_strlen($signingKey) < static::MINIMUM_KEY_LENGTH) {
            throw new RuntimeException(
                sprintf(
                    'CARONTE_APP_SECRET must be at least %d characters long. Current length: %d.',
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

    /**
     * @return array<int, mixed>
     */
    private static function scopeClaims(Plain $token): array
    {
        $scopes = $token->claims()->get('scopes', null);

        if (is_array($scopes)) {
            return $scopes;
        }

        $legacyPermissions = $token->claims()->get('permissions', null);

        if (is_array($legacyPermissions)) {
            LegacyDeprecation::warn('permissions JWT claim', 'scopes JWT claim');
            return $legacyPermissions;
        }

        throw new UnprocessableEntityException('Invalid Protected API Access Token scopes.');
    }

    private static function assertSignatureAndIssuer(Plain $token): void
    {
        $config = static::getConfig();
        $constraints = [
            new SignedWith($config->signer(), $config->signingKey()),
        ];

        if (config('caronte.enforce_issuer')) {
            $constraints[] = new IssuedBy((string) config('caronte.issuer_id'));
        }

        if ($token->claims()->has('aud')) {
            $constraints[] = new PermittedFor(CaronteApplicationToken::appId());
        }

        if (! $config->validator()->validate($token, ...$constraints)) {
            throw new UnprocessableEntityException('Invalid Protected API Access Token signature, issuer, or audience.');
        }
    }

    private static function assertApplicationClaim(Plain $token): void
    {
        if ((string) $token->claims()->get('app_id') !== CaronteApplicationToken::appId()) {
            throw new UnprocessableEntityException('Protected API Access Token does not match the configured Caronte application.');
        }

        if ((string) $token->claims()->get('tenant_id', '') === '') {
            throw new UnprocessableEntityException('Protected API Access Token tenant is required.');
        }
    }

    private static function assertTemporalClaims(Plain $token): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        foreach (['iat', 'nbf'] as $claim) {
            if (! $token->claims()->has($claim)) {
                continue;
            }

            $value = $token->claims()->get($claim);

            if ($value instanceof DateTimeImmutable && $value > $now) {
                throw new UnprocessableEntityException('Protected API Access Token is not yet valid.');
            }
        }

        $expiresAt = $token->claims()->get('exp');

        if ($expiresAt instanceof DateTimeImmutable && $expiresAt <= $now) {
            throw new UnprocessableEntityException('Protected API Access Token has expired.');
        }
    }
}
