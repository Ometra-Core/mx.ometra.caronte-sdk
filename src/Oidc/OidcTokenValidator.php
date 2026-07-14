<?php

namespace Ometra\Caronte\Oidc;

use DateTimeZone;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

class OidcTokenValidator
{
    public function validate(string $rawToken, ?string $expectedNonce = null): Plain
    {
        $token = (new Parser(new JoseEncoder()))->parse($rawToken);

        if (! $token instanceof Plain) {
            throw new UnprocessableEntityException('Invalid OIDC token.');
        }

        $kid = $token->headers()->get('kid');

        if (! is_string($kid) || $kid === '') {
            throw new UnprocessableEntityException('OIDC token kid is missing.');
        }

        $jwk = app(JwksCache::class)->key($kid);
        $publicKey = Jwk::toPem($jwk);
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($publicKey),
            InMemory::plainText($publicKey)
        );

        $constraints = [
            new SignedWith($config->signer(), $config->verificationKey()),
            new LooseValidAt(new SystemClock(new DateTimeZone('UTC'))),
            new PermittedFor((string) config('caronte.oidc.client_id')),
            new IssuedBy((string) config('caronte.oidc.issuer')),
        ];

        if (! $config->validator()->validate($token, ...$constraints)) {
            throw new UnprocessableEntityException('Invalid OIDC token signature, issuer, audience or lifetime.');
        }

        if ($expectedNonce !== null) {
            $nonce = $token->claims()->get('nonce', null);

            if (
                $expectedNonce === ''
                || ! is_string($nonce)
                || $nonce === ''
                || ! hash_equals($expectedNonce, $nonce)
            ) {
                throw new UnprocessableEntityException('Invalid OIDC token nonce.');
            }
        }

        return $token;
    }
}
