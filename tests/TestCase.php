<?php

namespace Tests;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\Toolkit\Providers\EquidnaLaravelToolkitServiceProvider;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Orchestra\Testbench\TestCase as Orchestra;
use Ometra\Caronte\Providers\CaronteServiceProvider;
use Ometra\Caronte\Support\CaronteApplicationToken;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EquidnaLaravelToolkitServiceProvider::class,
            InertiaServiceProvider::class,
            CaronteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('app.url', 'https://client.test');
        $app['config']->set('session.driver', 'array');

        $compiledViewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
            . '.phpunit.cache' . DIRECTORY_SEPARATOR
            . 'views';

        if (! is_dir($compiledViewsPath)) {
            mkdir($compiledViewsPath, 0777, true);
        }

        $app['config']->set('view.compiled', $compiledViewsPath);

        $app['config']->set('caronte.url', 'https://caronte.test/');
        $app['config']->set('caronte.app_cn', 'test-app-id');
        $app['config']->set('caronte.app_secret', 'test-app-secret-with-minimum-length-32');
        $app['config']->set('caronte.routes.login_url', '/login');
        $app['config']->set('caronte.issuer_id', 'caronte');
        $app['config']->set('caronte.routes.prefix', '');
        $app['config']->set('caronte.ui.use_inertia', false);
        $app['config']->set('caronte.management.enabled', true);
        $app['config']->set('caronte.management.route_prefix', 'caronte/management');
        $app['config']->set('caronte.management.access_roles', ['root']);
        $app['config']->set('caronte.management.features.metadata', true);
        $app['config']->set('caronte.roles', [
            'root' => 'Default super administrator role',
            'admin' => 'Administrative access',
        ]);
    }

    protected function headerValue(object $request, string $name): string
    {
        $value = $request->header($name);

        if (is_array($value)) {
            return (string) ($value[0] ?? '');
        }

        return is_string($value) ? $value : '';
    }

    protected function hasValidApplicationTokenHeader(object $request): bool
    {
        return CaronteApplicationToken::matchType($this->headerValue($request, 'X-Application-Token')) === 'application';
    }

    protected function hasValidGroupTokenHeader(object $request): bool
    {
        return CaronteApplicationToken::matchType($this->headerValue($request, 'X-Group-Token')) === 'application_group';
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    protected function makeToken(
        ?array $user = null,
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $expiresAt = null,
        bool $group = false,
        ?string $sourceAppId = null,
    ): string {
        $issuedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt ??= $issuedAt->modify('+15 minutes');

        $user ??= [
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'tenant_id' => 'tenant-1',
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [],
        ];

        $signingKey = $group
            ? (string) config('caronte.application_group_secret')
            : (string) config('caronte.app_secret');

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($signingKey)
        );

        $builder = $config->builder(ChainedFormatter::default())
            ->identifiedBy('user-token-1')
            ->issuedBy((string) config('caronte.issuer_id', ''))
            ->permittedFor($group ? (string) config('caronte.application_group_id') : CaronteApplicationToken::appId())
            ->relatedTo($this->subjectForTokenUser($user))
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('tenant_id', $user['tenant_id'] ?? null)
            ->withClaim('tenant_name', $user['tenant_name'] ?? '')
            ->withClaim('name', $user['name'])
            ->withClaim('email', $user['email'])
            ->withClaim('roles', $user['roles'])
            ->withClaim('metadata', $user['metadata']);

        if ($group) {
            $builder = $builder
                ->withClaim('token_audience', 'application_group')
                ->withClaim('app_id', $sourceAppId ?? sha1('source-app'))
                ->withClaim('group_id', (string) config('caronte.application_group_id'))
                ->withClaim('source_app_id', $sourceAppId ?? sha1('source-app'));
        } else {
            $builder = $builder
                ->withClaim('token_audience', 'application')
                ->withClaim('app_id', CaronteApplicationToken::appId());
        }

        return $builder
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function subjectForTokenUser(array $user): string
    {
        return (string) ($user['uri_user'] ?? '');
    }

    /**
     * @param  array<int, string>  $scopes
     */
    protected function makeProtectedApiAccessToken(
        array $scopes = ['invoices.read'],
        string $tenantId = 'tenant-1',
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $expiresAt = null,
        string $audience = 'protected_api_access',
        ?string $jwtAudience = null,
    ): string {
        $issuedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt ??= $issuedAt->modify('+1 year');

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText((string) config('caronte.app_secret'))
        );

        return $config->builder(ChainedFormatter::default())
            ->issuedBy((string) config('caronte.issuer_id', ''))
            ->permittedFor($jwtAudience ?? CaronteApplicationToken::appId())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->identifiedBy('protected-api-token-1')
            ->withClaim('token_audience', $audience)
            ->withClaim('app_id', CaronteApplicationToken::appId())
            ->withClaim('tenant_id', $tenantId)
            ->withClaim('name', 'Integration token')
            ->withClaim('scopes', $scopes)
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

}
