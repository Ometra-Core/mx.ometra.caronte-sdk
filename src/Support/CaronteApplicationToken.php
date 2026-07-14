<?php

namespace Ometra\Caronte\Support;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use RuntimeException;
use Throwable;

class CaronteApplicationToken
{
    public const MINIMUM_KEY_LENGTH = 32;

    public const APPLICATION_AUDIENCE = 'application_auth';

    public const GROUP_AUDIENCE = 'application_group_auth';

    public static function cn(): string
    {
        return strtolower(trim((string) config('caronte.app_cn')));
    }

    public static function appId(): string
    {
        return static::appIdForCn(static::cn());
    }

    public static function groupId(): string
    {
        return strtolower(trim((string) config('caronte.application_group_id', '')));
    }

    public static function hasGroup(): bool
    {
        return static::groupId() !== ''
            && trim((string) config('caronte.application_group_secret', '')) !== '';
    }

    public static function make(): string
    {
        return static::makeFor(
            appCn: static::cn(),
            appSecret: (string) config('caronte.app_secret')
        );
    }

    public static function makeFor(
        string $appCn,
        string $appSecret,
        ?DateTimeImmutable $issuedAt = null,
    ): string {
        $appCn = strtolower(trim($appCn));
        $appId = static::appIdForCn($appCn);
        $issuedAt ??= static::now();
        $expiresAt = $issuedAt->modify('+' . static::tokenTtlSeconds() . ' seconds');
        $config = static::configForSigningKey($appSecret, 'CARONTE_APP_SECRET');

        return $config->builder(ChainedFormatter::default())
            ->issuedBy((string) config('caronte.issuer_id', ''))
            ->permittedFor($appId)
            ->identifiedBy(static::tokenId())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('token_audience', static::APPLICATION_AUDIENCE)
            ->withClaim('app_id', $appId)
            ->withClaim('app_cn', $appCn)
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    public static function makeGroup(?DateTimeImmutable $issuedAt = null): string
    {
        if (! static::hasGroup()) {
            return '';
        }

        $issuedAt ??= static::now();
        $expiresAt = $issuedAt->modify('+' . static::tokenTtlSeconds() . ' seconds');
        $config = static::groupConfig();

        return $config->builder(ChainedFormatter::default())
            ->issuedBy((string) config('caronte.issuer_id', ''))
            ->permittedFor(static::groupId())
            ->identifiedBy(static::tokenId())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('token_audience', static::GROUP_AUDIENCE)
            ->withClaim('group_id', static::groupId())
            ->withClaim('source_app_id', static::appId())
            ->withClaim('source_app_cn', static::cn())
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    public static function matches(?string $token): bool
    {
        return static::matchType($token) !== null;
    }

    public static function matchType(?string $token): ?string
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $token = trim($token);

        try {
            static::validateApplicationToken($token);

            return 'application';
        } catch (Throwable) {
            //
        }

        try {
            static::validateGroupToken($token);

            return 'application_group';
        } catch (Throwable) {
            //
        }

        return null;
    }

    public static function decodeApplicationToken(string $rawToken): Plain
    {
        $token = static::parseToken($rawToken, 'application token');

        static::assertRequiredClaims($token, [
            'iss',
            'aud',
            'jti',
            'iat',
            'nbf',
            'exp',
            'token_audience',
            'app_id',
            'app_cn',
        ]);
        static::assertAudience($token, static::APPLICATION_AUDIENCE, 'application token');

        return $token;
    }

    public static function validateApplicationToken(string $rawToken): Plain
    {
        $token = static::decodeApplicationToken($rawToken);
        $config = static::applicationConfig();

        static::assertSignatureAndIssuer($token, $config, 'Invalid application token signature or issuer.');
        static::assertTemporalClaims($token, 'Application token');

        if ((string) $token->claims()->get('app_id') !== static::appId()) {
            throw new UnprocessableEntityException('Application token does not match the configured Caronte application.');
        }

        if ((string) $token->claims()->get('app_cn') !== static::cn()) {
            throw new UnprocessableEntityException('Application token canonical name does not match the configured Caronte application.');
        }

        if (! $token->isPermittedFor(static::appId())) {
            throw new UnprocessableEntityException('Application token does not match the configured Caronte application audience.');
        }

        return $token;
    }

    public static function validateGroupToken(string $rawToken): Plain
    {
        if (! static::hasGroup()) {
            throw new UnprocessableEntityException('Caronte application group is not configured.');
        }

        $token = static::parseToken($rawToken, 'application group token');
        $config = static::groupConfig();

        static::assertRequiredClaims($token, [
            'iss',
            'aud',
            'jti',
            'iat',
            'nbf',
            'exp',
            'token_audience',
            'group_id',
            'source_app_id',
            'source_app_cn',
        ]);

        static::assertAudience($token, static::GROUP_AUDIENCE, 'application group token');
        static::assertSignatureAndIssuer($token, $config, 'Invalid application group token signature or issuer.');
        static::assertTemporalClaims($token, 'Application group token');

        if ((string) $token->claims()->get('group_id') !== static::groupId()) {
            throw new UnprocessableEntityException('Application group token does not match the configured Caronte application group.');
        }

        if (! $token->isPermittedFor(static::groupId())) {
            throw new UnprocessableEntityException('Application group token does not match the configured Caronte application group audience.');
        }

        if (trim((string) $token->claims()->get('source_app_id')) === '') {
            throw new UnprocessableEntityException('Application group token source app id is required.');
        }

        if (trim((string) $token->claims()->get('source_app_cn')) === '') {
            throw new UnprocessableEntityException('Application group token source app canonical name is required.');
        }

        return $token;
    }

    private static function appIdForCn(string $appCn): string
    {
        return sha1(strtolower(trim($appCn)));
    }

    private static function applicationConfig(): Configuration
    {
        return static::configForSigningKey(
            signingKey: (string) config('caronte.app_secret'),
            configName: 'CARONTE_APP_SECRET'
        );
    }

    private static function groupConfig(): Configuration
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

    private static function parseToken(string $rawToken, string $label): Plain
    {
        if (trim($rawToken) === '') {
            throw new BadRequestException(ucfirst($label) . ' not provided.');
        }

        if (count(explode('.', $rawToken)) !== 3) {
            throw new BadRequestException('Malformed ' . $label . '.');
        }

        $token = (new Parser(new JoseEncoder()))->parse($rawToken);

        if (! $token instanceof Plain) {
            throw new BadRequestException('Invalid ' . $label . '.');
        }

        return $token;
    }

    /**
     * @param  array<int, string>  $claims
     */
    private static function assertRequiredClaims(Plain $token, array $claims): void
    {
        foreach ($claims as $claim) {
            if (! $token->claims()->has($claim)) {
                throw new UnprocessableEntityException('Invalid application authentication token.');
            }
        }
    }

    private static function assertAudience(Plain $token, string $audience, string $label): void
    {
        if ((string) $token->claims()->get('token_audience', '') !== $audience) {
            throw new UnprocessableEntityException('Invalid ' . $label . ' audience.');
        }
    }

    private static function assertSignatureAndIssuer(Plain $token, Configuration $config, string $message): void
    {
        $constraints = [
            new SignedWith($config->signer(), $config->signingKey()),
        ];

        $constraints[] = new IssuedBy((string) config('caronte.issuer_id'));

        if (! $config->validator()->validate($token, ...$constraints)) {
            throw new UnprocessableEntityException($message);
        }
    }

    private static function assertTemporalClaims(Plain $token, string $label): void
    {
        $now = static::now();
        $leewaySeconds = max(0, (int) config('caronte.token.clock_skew_seconds', 60));

        foreach (['iat', 'nbf'] as $claim) {
            if (! $token->claims()->has($claim)) {
                continue;
            }

            $value = $token->claims()->get($claim);

            if (
                $value instanceof DateTimeImmutable
                && $value->getTimestamp() > ($now->getTimestamp() + $leewaySeconds)
            ) {
                throw new UnprocessableEntityException($label . ' is not yet valid.');
            }
        }

        $issuedAt = $token->claims()->get('iat');
        $notBefore = $token->claims()->get('nbf');
        $expiresAt = $token->claims()->get('exp');

        if (
            ! $issuedAt instanceof DateTimeImmutable
            || ! $notBefore instanceof DateTimeImmutable
            || ! $expiresAt instanceof DateTimeImmutable
            || $expiresAt <= $issuedAt
        ) {
            throw new UnprocessableEntityException($label . ' has invalid temporal claims.');
        }

        if ($expiresAt->getTimestamp() <= ($now->getTimestamp() - $leewaySeconds)) {
            throw new UnprocessableEntityException($label . ' has expired.');
        }
    }

    private static function tokenTtlSeconds(): int
    {
        return max(1, (int) config('caronte.token.ttl_seconds', 300));
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private static function tokenId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
