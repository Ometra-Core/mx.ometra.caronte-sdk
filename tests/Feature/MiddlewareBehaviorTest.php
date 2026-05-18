<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Ometra\Caronte\Oidc\OidcTokenValidator;
use Ometra\Caronte\Support\CaronteApplicationContext;
use Ometra\Caronte\Support\CaronteApplicationAccessContext;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Tests\TestCase;

class MiddlewareBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['caronte.application:tenant_required'])
            ->get('/api/_caronte/application-check', fn() => response()->json(['ok' => true]));

        Route::middleware(['caronte.application:tenant_required'])
            ->get('/api/_caronte/context-check', function (Request $request) {
                /** @var CaronteApplicationContext $context */
                $context = app(CaronteApplicationContext::class);

                return response()->json([
                    'tenant_context' => app(TenantContext::class)->get(),
                    'app_id' => $context->appId,
                ]);
            });

        Route::middleware(['caronte.application'])
            ->get('/api/_caronte/application-only-check', function () {
                /** @var CaronteApplicationContext $context */
                $context = app(CaronteApplicationContext::class);

                return response()->json([
                    'app_id' => $context->appId,
                    'tenant_context' => app()->bound(TenantContext::class)
                        ? app(TenantContext::class)->get()
                        : null,
                ]);
            });

        Route::middleware(['caronte.session'])
            ->get('/api/_caronte/session-check', fn() => response()->json(['ok' => true]));

        Route::middleware(['web', 'caronte.session'])
            ->get('/_caronte/session-check', function () {
                return response()->json([
                    'tenant_context' => app()->bound(TenantContext::class)
                        ? app(TenantContext::class)->get()
                        : null,
                ]);
            });

        Route::middleware(['caronte.session', 'caronte.roles:admin'])
            ->get('/api/_caronte/role-check', fn() => response()->json(['ok' => true]));

        Route::middleware(['caronte.app-token', 'caronte.app-permissions:invoices.read'])
            ->get('/api/_caronte/application-access-check', function () {
                /** @var CaronteApplicationAccessContext $context */
                $context = app(CaronteApplicationAccessContext::class);

                return response()->json([
                    'tenant_id' => $context->tenantId,
                    'permissions' => $context->permissions,
                ]);
            });
    }

    public function test_application_middleware_requires_tenant_when_requested(): void
    {
        $this->getJson('/api/_caronte/application-check')
            ->assertStatus(401);

        $this->getJson('/api/_caronte/application-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
        ])->assertStatus(400);

        $this->getJson('/api/_caronte/application-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-Tenant-Id' => 'tenant-1',
        ])->assertOk();
    }

    public function test_application_middleware_accepts_optional_tenant_context(): void
    {
        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
        ])
            ->assertOk()
            ->assertJsonPath('app_id', CaronteApplicationToken::appId())
            ->assertJsonPath('tenant_context', null);
    }

    public function test_application_middleware_accepts_group_application_token(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => CaronteApplicationToken::makeGroup(),
        ])
            ->assertOk()
            ->assertJsonPath('app_id', CaronteApplicationToken::appId());

        /** @var CaronteApplicationContext $context */
        $context = app(CaronteApplicationContext::class);
        $this->assertTrue($context->authenticatedAsGroup);
        $this->assertSame('core-suite', $context->groupId);
    }

    public function test_application_middleware_binds_tenant_context_for_the_request_lifecycle(): void
    {
        $this->getJson('/api/_caronte/context-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-Tenant-Id' => 'tenant-1',
        ])
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1')
            ->assertJsonPath('app_id', CaronteApplicationToken::appId());
    }

    public function test_single_tenant_application_middleware_binds_configured_tenant_without_header(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $this->getJson('/api/_caronte/context-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
        ])
            ->assertOk()
            ->assertJsonPath('tenant_context', 'mobig')
            ->assertJsonPath('app_id', CaronteApplicationToken::appId());
    }

    public function test_single_tenant_application_middleware_rejects_header_mismatch(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-Tenant-Id' => 'other-tenant',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant mismatch.');
    }

    public function test_application_middleware_rejects_header_that_overrides_authenticated_tenant(): void
    {
        $token = $this->makeToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/context-check', [
                'X-Application-Token' => CaronteApplicationToken::make(),
                'X-Tenant-Id' => 'other-tenant',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant override is not allowed.');
    }

    public function test_session_middleware_exchanges_expired_api_tokens_and_returns_the_refreshed_header(): void
    {
        $expired = $this->makeToken(
            issuedAt: new DateTimeImmutable('-30 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('-5 minutes', new DateTimeZone('UTC')),
        );
        $fresh = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/exchange' => Http::response([
                'status' => 200,
                'message' => 'Token exchanged',
                'data' => ['token' => $fresh],
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $expired)
            ->getJson('/api/_caronte/session-check');

        $response->assertOk();
        $response->assertHeader('X-User-Token', $fresh);
    }

    public function test_session_middleware_exchanges_tokens_inside_refresh_window(): void
    {
        config()->set('caronte.token_refresh_leeway_seconds', 60);

        $expiring = $this->makeToken(
            issuedAt: new DateTimeImmutable('-14 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('+30 seconds', new DateTimeZone('UTC')),
        );
        $fresh = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/exchange' => Http::response([
                'status' => 200,
                'message' => 'Token exchanged',
                'data' => ['token' => $fresh],
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $expiring)
            ->getJson('/api/_caronte/session-check');

        $response->assertOk();
        $response->assertHeader('X-User-Token', $fresh);
    }

    public function test_session_middleware_keeps_token_when_exchange_rejects_still_valid(): void
    {
        config()->set('caronte.token_refresh_leeway_seconds', 60);

        $expiring = $this->makeToken(
            issuedAt: new DateTimeImmutable('-14 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('+30 seconds', new DateTimeZone('UTC')),
        );

        Http::fake([
            'https://caronte.test/api/auth/exchange' => Http::response([
                'status' => 401,
                'message' => 'Token is still valid',
            ], 401),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $expiring)
            ->getJson('/api/_caronte/session-check');

        $response->assertOk();
        $response->assertHeaderMissing('X-User-Token');
    }

    public function test_oidc_session_middleware_refreshes_with_refresh_token(): void
    {
        config()->set('caronte.auth_mode', 'oidc');
        config()->set('caronte.oidc.issuer', 'https://caronte.test');
        config()->set('caronte.oidc.client_id', 'test-app-id');
        config()->set('caronte.oidc.client_secret', 'oidc-secret');

        $this->fakeOidcValidator();

        $expiring = $this->makeOidcToken(
            issuedAt: new DateTimeImmutable('-14 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('+30 seconds', new DateTimeZone('UTC')),
        );
        $fresh = $this->makeOidcToken();

        Http::fake([
            'https://caronte.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'id_token' => $fresh,
                'refresh_token' => 'new-refresh-token',
            ], 200),
        ]);

        $response = $this->withSession([
            (string) config('caronte.session_key', 'caronte.user_token') => $expiring,
            'caronte.oidc.refresh_token' => 'old-refresh-token',
        ])->get('/_caronte/session-check', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('X-User-Token', $fresh);
        $this->assertSame($fresh, session((string) config('caronte.session_key', 'caronte.user_token')));
        $this->assertSame('new-refresh-token', session('caronte.oidc.refresh_token'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/oauth/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'old-refresh-token';
        });
    }

    public function test_oidc_session_middleware_clears_session_when_refresh_fails(): void
    {
        config()->set('caronte.auth_mode', 'oidc');
        config()->set('caronte.oidc.issuer', 'https://caronte.test');
        config()->set('caronte.oidc.client_id', 'test-app-id');
        config()->set('caronte.oidc.client_secret', 'oidc-secret');

        $expired = $this->makeOidcToken(
            issuedAt: new DateTimeImmutable('-30 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('-1 minute', new DateTimeZone('UTC')),
        );

        Http::fake([
            'https://caronte.test/oauth/token' => Http::response([
                'error_description' => 'Invalid refresh token.',
            ], 400),
        ]);

        $this->withSession([
            (string) config('caronte.session_key', 'caronte.user_token') => $expired,
            'caronte.oidc.refresh_token' => 'old-refresh-token',
        ])->get('/_caronte/session-check', ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'))
            ->assertSessionMissing('caronte.oidc.refresh_token');
    }

    public function test_role_middleware_rejects_users_without_the_required_role(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'tenant_id' => 'tenant-1',
            'roles' => [
                [
                    'name' => 'rootless',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'rootless'),
                ],
            ],
            'metadata' => [],
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/role-check')
            ->assertStatus(403);
    }

    public function test_web_session_middleware_preserves_application_permission_error_detail(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Foreign App User',
            'email' => 'foreign@example.com',
            'tenant_id' => 'tenant-1',
            'roles' => [
                [
                    'name' => 'viewer',
                    'app_id' => 'other-app-id',
                    'uri_applicationRole' => sha1('other-app-id' . 'viewer'),
                ],
            ],
            'metadata' => [],
        ]);

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->from('/dashboard')
            ->get('/_caronte/session-check')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'general' => 'User does not have access to this application.',
            ])
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function test_inertia_session_middleware_uses_location_response_for_expired_sessions(): void
    {
        $this->get('/_caronte/session-check', [
            'X-Inertia' => 'true',
        ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', '/login')
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function test_single_tenant_session_middleware_binds_configured_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Mobig User',
            'email' => 'mobig@example.com',
            'tenant_id' => 'mobig',
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [],
        ]);

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertOk()
            ->assertJsonPath('tenant_context', 'mobig');
    }

    public function test_single_tenant_session_middleware_rejects_token_without_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Global User',
            'email' => 'global@example.com',
            'tenant_id' => null,
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [],
        ]);

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant is required for this application.')
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function test_single_tenant_session_middleware_rejects_token_tenant_mismatch(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $token = $this->makeToken();

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant mismatch.')
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function test_application_access_middleware_accepts_tokens_with_required_permission(): void
    {
        $token = $this->makeApplicationAccessToken(['invoices.read']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertOk()
            ->assertJsonPath('tenant_id', 'tenant-1')
            ->assertJsonPath('permissions.0', 'invoices.read');
    }

    public function test_application_access_middleware_rejects_missing_permission(): void
    {
        $token = $this->makeApplicationAccessToken(['invoices.write']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(403);
    }

    private function fakeOidcValidator(): void
    {
        app()->instance(OidcTokenValidator::class, new class extends OidcTokenValidator
        {
            public function validate(string $rawToken): Plain
            {
                $token = (new Parser(new JoseEncoder()))->parse($rawToken);

                if (! $token instanceof Plain) {
                    throw new \RuntimeException('Invalid OIDC token.');
                }

                return $token;
            }
        });
    }

    private function makeOidcToken(
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $expiresAt = null,
    ): string {
        $issuedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt ??= $issuedAt->modify('+15 minutes');

        $config = \Lcobucci\JWT\Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::plainText('oidc-test-secret-with-minimum-length')
        );

        return $config->builder(\Lcobucci\JWT\Encoding\ChainedFormatter::default())
            ->withHeader('kid', 'oidc-test-kid')
            ->identifiedBy('oidc-token-1')
            ->issuedBy('https://caronte.test')
            ->permittedFor('test-app-id')
            ->relatedTo('user-123')
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('tenant_id', 'tenant-1')
            ->withClaim('name', 'Root User')
            ->withClaim('email', 'root@example.com')
            ->withClaim('roles', ['root'])
            ->withClaim('metadata', [])
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }
}
