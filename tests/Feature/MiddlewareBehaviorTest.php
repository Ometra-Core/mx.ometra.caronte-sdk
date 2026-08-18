<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\BeeHive\Tenancy\TenantContext;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Http\Middleware\ResolveApplicationContext;
use Ometra\Caronte\Oidc\OidcTokenValidator;
use Ometra\Caronte\Support\CaronteApplicationContext;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Ometra\Caronte\Support\CaronteForwardedUserContext;
use Ometra\Caronte\Support\CaronteProtectedApiAccessContext;
use Ometra\Caronte\Support\CaronteResponse;
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
                    'source_app_id' => $context->sourceAppId,
                    'source_app_cn' => $context->sourceAppCn,
                    'application_token_id' => $context->applicationTokenId,
                    'group_token_id' => $context->groupTokenId,
                    'tenant_context' => app()->bound(TenantContext::class)
                        ? app(TenantContext::class)->get()
                        : null,
                ]);
            });

        Route::middleware(['caronte.application:user_required'])
            ->get('/api/_caronte/forwarded-user-check', function () {
                /** @var CaronteForwardedUserContext $context */
                $context = app(CaronteForwardedUserContext::class);

                return response()->json([
                    'uri_user' => $context->user->uri_user ?? null,
                    'id_tenant' => $context->tenantId,
                    'token_id' => $context->tokenId,
                    'tenant_context' => app()->bound(TenantContext::class)
                        ? app(TenantContext::class)->get()
                        : null,
                ]);
            });

        Route::middleware(['caronte.application:tenant_required,user_required'])
            ->get('/api/_caronte/forwarded-user-tenant-check', function () {
                /** @var CaronteForwardedUserContext $context */
                $context = app(CaronteForwardedUserContext::class);

                return response()->json([
                    'uri_user' => $context->user->uri_user ?? null,
                    'id_tenant' => $context->tenantId,
                    'tenant_context' => app(TenantContext::class)->get(),
                ]);
            });

        Route::middleware(['caronte.session'])
            ->get('/api/_caronte/session-check', fn() => response()->json(['ok' => true]));

        Route::middleware(['web', 'caronte.session'])
            ->match(['get', 'post'], '/_caronte/session-check', function () {
                return response()->json([
                    'tenant_context' => app()->bound(TenantContext::class)
                        ? app(TenantContext::class)->get()
                        : null,
                ]);
            });

        Route::middleware(['web'])
            ->post('/_caronte/inertia-forward-error', fn() => CaronteResponse::unprocessable(
                message: 'Validation failed.',
                errors: ['email' => ['Invalid email.']],
                forwardUrl: '/login'
            ));

        Route::middleware(['caronte.session', 'caronte.roles:admin'])
            ->get('/api/_caronte/role-check', fn() => response()->json(['ok' => true]));

        Route::middleware(['caronte.protected-api-token', 'caronte.protected-api-scopes:invoices.read'])
            ->get('/api/_caronte/application-access-check', function () {
                /** @var CaronteProtectedApiAccessContext $context */
                $context = app(CaronteProtectedApiAccessContext::class);

                return response()->json([
                    'id_tenant' => $context->tenantId,
                    'scopes' => $context->scopes,
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

    public function test_blocked_login_route_uses_app_url_when_success_url_is_root(): void
    {
        config()->set('caronte.routes.login_view_enabled', false);
        config()->set('caronte.routes.login_url', 'https://identity.example.test/login');
        config()->set('caronte.routes.success_url', '/');

        $response = $this->get('/login');

        $response->assertRedirect();

        $location = (string) $response->baseResponse->headers->get('Location');

        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('callback_url', $query);
        $this->assertSame('https://client.test', base64_decode((string) $query['callback_url'], true));
    }

    public function test_blocked_login_route_redirects_without_callback_when_no_fallback_is_available(): void
    {
        config()->set('caronte.routes.login_view_enabled', false);
        config()->set('caronte.routes.login_url', 'https://identity.example.test/login');
        config()->set('caronte.routes.success_url', '');
        config()->set('app.url', '');

        $response = $this->get('/login');

        $response->assertRedirect('https://identity.example.test/login');

        $location = (string) $response->baseResponse->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertArrayNotHasKey('callback_url', $query);
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

    public function test_application_middleware_accepts_group_token_without_application_token(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $this->getJson('/api/_caronte/application-only-check', [
            'X-Group-Token' => CaronteApplicationToken::makeGroup(),
        ])
            ->assertOk()
            ->assertJsonPath('app_id', CaronteApplicationToken::appId())
            ->assertJsonPath('source_app_id', CaronteApplicationToken::appId())
            ->assertJsonPath('source_app_cn', CaronteApplicationToken::cn());

        /** @var CaronteApplicationContext $context */
        $context = app(CaronteApplicationContext::class);
        $this->assertTrue($context->authenticatedAsGroup);
        $this->assertSame('core-suite', $context->groupId);
        $this->assertNull($context->applicationToken);
        $this->assertNull($context->applicationTokenId);
        $this->assertNotEmpty($context->groupTokenId);
    }

    public function test_application_middleware_rejects_ambiguous_application_and_group_tokens(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-Group-Token' => CaronteApplicationToken::makeGroup(),
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Ambiguous application credentials.');
    }

    public function test_application_middleware_rejects_legacy_base64_application_tokens(): void
    {
        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => base64_encode(CaronteApplicationToken::appId() . ':' . (string) config('caronte.app_secret')),
        ])->assertStatus(401);
    }

    public function test_application_middleware_rejects_legacy_base64_group_tokens(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $this->getJson('/api/_caronte/application-only-check', [
            'X-Group-Token' => base64_encode('core-suite:group-secret-with-minimum-length-32'),
        ])->assertStatus(401);
    }

    public function test_application_auth_token_contains_expected_jwt_claims(): void
    {
        $token = CaronteApplicationToken::validateApplicationToken(CaronteApplicationToken::make());

        $this->assertSame('application_auth', $token->claims()->get('token_audience'));
        $this->assertSame(CaronteApplicationToken::appId(), $token->claims()->get('app_id'));
        $this->assertSame(CaronteApplicationToken::cn(), $token->claims()->get('app_cn'));
        $this->assertTrue($token->claims()->has('jti'));
        $this->assertTrue($token->claims()->has('exp'));
    }

    public function test_group_auth_token_contains_expected_jwt_claims(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = CaronteApplicationToken::validateGroupToken(CaronteApplicationToken::makeGroup());

        $this->assertSame('application_group_auth', $token->claims()->get('token_audience'));
        $this->assertSame('core-suite', $token->claims()->get('group_id'));
        $this->assertSame(CaronteApplicationToken::appId(), $token->claims()->get('source_app_id'));
        $this->assertSame(CaronteApplicationToken::cn(), $token->claims()->get('source_app_cn'));
        $this->assertTrue($token->claims()->has('jti'));
        $this->assertTrue($token->claims()->has('exp'));
    }

    public function test_application_auth_token_rejects_expired_tokens(): void
    {
        $expired = CaronteApplicationToken::makeFor(
            appCn: CaronteApplicationToken::cn(),
            appSecret: (string) config('caronte.app_secret'),
            issuedAt: new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC')),
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Application token has expired.');

        CaronteApplicationToken::validateApplicationToken($expired);
    }

    public function test_group_auth_token_rejects_expired_tokens(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $expired = CaronteApplicationToken::makeGroup(
            issuedAt: new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC')),
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Application group token has expired.');

        CaronteApplicationToken::validateGroupToken($expired);
    }

    public function test_application_auth_token_rejects_wrong_audience(): void
    {
        $wrongAudience = $this->makeApplicationAuthToken(['token_audience' => 'application']);

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Invalid application token audience.');

        CaronteApplicationToken::validateApplicationToken($wrongAudience);
    }

    public function test_application_auth_token_rejects_invalid_signature(): void
    {
        $token = CaronteApplicationToken::makeFor(
            appCn: CaronteApplicationToken::cn(),
            appSecret: 'different-secret-with-minimum-length-32'
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Invalid application token signature or issuer.');

        CaronteApplicationToken::validateApplicationToken($token);
    }

    public function test_group_auth_token_rejects_wrong_group(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = CaronteApplicationToken::makeGroup();

        config()->set('caronte.application_group_id', 'other-suite');

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Application group token does not match the configured Caronte application group.');

        CaronteApplicationToken::validateGroupToken($token);
    }

    public function test_group_auth_token_rejects_invalid_signature(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = CaronteApplicationToken::makeGroup();

        config()->set('caronte.application_group_secret', 'other-group-secret-with-minimum-length-32');

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Invalid application group token signature or issuer.');

        CaronteApplicationToken::validateGroupToken($token);
    }

    public function test_user_token_rejects_each_missing_required_claim(): void
    {
        foreach (['iss', 'aud', 'sub', 'jti', 'iat', 'nbf', 'exp', 'token_audience', 'id_tenant'] as $claim) {
            try {
                CaronteUserToken::validateToken($this->makeUserTokenOmitting([$claim]), skipExchange: true);
                $this->fail('Token without ' . $claim . ' should have been rejected.');
            } catch (UnprocessableEntityException $exception) {
                $this->assertStringContainsString($claim, $exception->getMessage());
            }
        }
    }

    public function test_user_token_rejects_unknown_audience(): void
    {
        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Invalid token audience.');

        CaronteUserToken::validateToken(
            $this->makeUserTokenOmitting([], ['token_audience' => 'unknown']),
            skipExchange: true
        );
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

    public function test_application_middleware_binds_tenant_context_from_forwarded_user_token(): void
    {
        $token = $this->makeToken();

        $this->getJson('/api/_caronte/context-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => $token,
        ])
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1')
            ->assertJsonPath('app_id', CaronteApplicationToken::appId());
    }

    public function test_single_tenant_application_middleware_binds_configured_tenant_without_header(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

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
        config()->set('caronte.tenancy.id_tenant', 'mobig');

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

        $this->getJson('/api/_caronte/context-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => $token,
            'X-Tenant-Id' => 'other-tenant',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant override is not allowed.');
    }

    public function test_application_middleware_rejects_invalid_forwarded_user_token(): void
    {
        $this->getJson('/api/_caronte/application-only-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => 'not-a-valid-token',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid user token.');
    }

    public function test_application_middleware_requires_forwarded_user_token_when_requested(): void
    {
        $this->getJson('/api/_caronte/forwarded-user-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'No user token provided.');
    }

    public function test_application_middleware_binds_forwarded_user_context_when_required(): void
    {
        $token = $this->makeToken();

        $this->getJson('/api/_caronte/forwarded-user-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => $token,
        ])
            ->assertOk()
            ->assertJsonPath('uri_user', 'user-123')
            ->assertJsonPath('id_tenant', 'tenant-1')
            ->assertJsonPath('token_id', 'user-token-1')
            ->assertJsonPath('tenant_context', 'tenant-1');
    }

    public function test_application_middleware_requires_user_and_resolved_tenant_when_both_modes_are_used(): void
    {
        $this->getJson('/api/_caronte/forwarded-user-tenant-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-Tenant-Id' => 'tenant-1',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'No user token provided.');

        $token = $this->makeToken([
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'id_tenant' => null,
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [],
        ]);

        $this->getJson('/api/_caronte/forwarded-user-tenant-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => $token,
        ])->assertStatus(400);

        $this->getJson('/api/_caronte/forwarded-user-tenant-check', [
            'X-Application-Token' => CaronteApplicationToken::make(),
            'X-User-Token' => $token,
            'X-Tenant-Id' => 'tenant-1',
        ])
            ->assertOk()
            ->assertJsonPath('uri_user', 'user-123')
            ->assertJsonPath('id_tenant', null)
            ->assertJsonPath('tenant_context', 'tenant-1');
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
        Http::assertSentCount(1);
    }

    public function test_session_middleware_does_not_leak_exchange_header_to_later_requests(): void
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

        $this->withHeader('Authorization', 'Bearer ' . $expired)
            ->getJson('/api/_caronte/session-check')
            ->assertOk()
            ->assertHeader('X-User-Token', $fresh);

        $this->withHeader('Authorization', 'Bearer ' . $fresh)
            ->getJson('/api/_caronte/session-check')
            ->assertOk()
            ->assertHeaderMissing('X-User-Token');
    }

    public function test_session_middleware_exchanges_tokens_inside_refresh_window(): void
    {
        config()->set('caronte.token.refresh_leeway_seconds', 60);

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
        config()->set('caronte.token.refresh_leeway_seconds', 60);

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

    public function test_session_middleware_persists_refreshed_jwt_for_web_json_requests(): void
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

        $sessionKey = (string) config('caronte.session_key', 'caronte.user_token');
        $response = $this->withSession([$sessionKey => $expired])
            ->get('/_caronte/session-check', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('X-User-Token', $fresh);
        $this->assertSame($fresh, session($sessionKey));
        Http::assertSentCount(1);
    }

    public function test_web_session_selects_a_portfolio_token_per_request(): void
    {
        $tenantA = $this->makeToken();
        $tenantB = $this->makeToken([
            'uri_user' => 'user-123', 'name' => 'Root User', 'email' => 'root@example.com',
            'id_tenant' => 'tenant-2', 'roles' => [[
                'name' => 'root', 'app_id' => CaronteApplicationToken::appId(),
                'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
            ]], 'metadata' => [],
        ]);
        $portfolio = [
            'tenant-1' => ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $tenantA],
            'tenant-2' => ['id_tenant' => 'tenant-2', 'name' => 'Tenant 2', 'token' => $tenantB],
        ];

        $this->withSession(['caronte.tenant_tokens' => $portfolio, 'caronte.last_tenant_id' => 'tenant-1'])
            ->get('/_caronte/session-check?id_tenant=tenant-2')
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-2');

        $this->withSession(['caronte.tenant_tokens' => $portfolio, 'caronte.last_tenant_id' => 'tenant-2'])
            ->withHeader('X-Tenant-Id', 'tenant-1')
            ->get('/_caronte/session-check')
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1');
    }

    public function test_web_session_rejects_conflicting_or_unavailable_tenant_selection(): void
    {
        $token = $this->makeToken();
        $session = ['caronte.tenant_tokens' => [
            'tenant-1' => ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $token],
        ]];

        $this->withSession($session)->withHeaders(['X-Tenant-Id' => 'tenant-2', 'Accept' => 'application/json'])
            ->get('/_caronte/session-check?id_tenant=tenant-1')
            ->assertStatus(403);

        $this->withSession($session)->withHeader('Accept', 'application/json')
            ->get('/_caronte/session-check?id_tenant=tenant-2')
            ->assertStatus(403);
    }

    public function test_web_session_does_not_treat_business_payload_tenant_as_navigation_context(): void
    {
        $token = $this->makeToken();
        $portfolio = [
            'tenant-1' => ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $token],
        ];

        $this->withSession([
            'caronte.tenant_tokens' => $portfolio,
            'caronte.last_tenant_id' => 'tenant-1',
        ])->post('/_caronte/session-check', ['id_tenant' => 'business-record-tenant'])
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1');
    }

    public function test_refresh_replaces_only_the_selected_portfolio_token(): void
    {
        $expired = $this->makeToken(
            issuedAt: new DateTimeImmutable('-30 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('-5 minutes', new DateTimeZone('UTC')),
        );
        $fresh = $this->makeToken();
        $other = $this->makeToken([
            'uri_user' => 'user-123', 'name' => 'Root User', 'email' => 'root@example.com',
            'id_tenant' => 'tenant-2', 'roles' => [[
                'name' => 'root', 'app_id' => CaronteApplicationToken::appId(),
                'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
            ]], 'metadata' => [],
        ]);
        Http::fake(['https://caronte.test/api/auth/exchange' => Http::response([
            'status' => 200, 'message' => 'Token exchanged', 'data' => ['token' => $fresh],
        ])]);

        $response = $this->withSession(['caronte.tenant_tokens' => [
            'tenant-1' => ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $expired],
            'tenant-2' => ['id_tenant' => 'tenant-2', 'name' => 'Tenant 2', 'token' => $other],
        ]])->get('/_caronte/session-check?id_tenant=tenant-1', ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertSame($fresh, session('caronte.tenant_tokens.tenant-1.token'));
        $this->assertSame($other, session('caronte.tenant_tokens.tenant-2.token'));
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
            'id_tenant' => 'tenant-1',
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
            'id_tenant' => 'tenant-1',
            'roles' => [
                [
                    'name' => 'viewer',
                    'app_id' => 'other-app-id',
                    'uri_applicationRole' => sha1('other-app-id' . 'viewer'),
                ],
            ],
            'metadata' => [],
        ]);

        $response = $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->from('/dashboard')
            ->get('/_caronte/session-check')
            ->assertSessionHasErrors([
                'general' => 'User does not have access to this application.',
            ])
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));

        $response->assertRedirect();
        $this->assertSame('/_caronte/session-check', parse_url($this->decodedCallbackUrlFromRedirect($response), PHP_URL_PATH));
    }

    public function test_group_access_mode_accepts_group_user_without_a_role_for_the_host_application(): void
    {
        config()->set('caronte.access.mode', 'application_group');
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = $this->makeToken([
            'uri_user' => 'suite-user',
            'name' => 'Suite User',
            'email' => 'suite@example.com',
            'id_tenant' => 'tenant-1',
            'roles' => [[
                'name' => 'viewer',
                'app_id' => 'another-suite-app',
                'uri_applicationRole' => sha1('another-suite-appviewer'),
            ]],
            'metadata' => [],
        ], group: true);

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1');
    }

    public function test_group_access_mode_rejects_an_application_token_without_a_host_role(): void
    {
        config()->set('caronte.access.mode', 'application_group');
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = $this->makeToken([
            'uri_user' => 'application-user',
            'name' => 'Application User',
            'email' => 'application@example.com',
            'id_tenant' => 'tenant-1',
            'roles' => [],
            'metadata' => [],
        ]);

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertForbidden()
            ->assertJsonPath('message', 'User does not have access to this application.');
    }

    public function test_web_session_middleware_redirects_to_login_with_intended_callback(): void
    {
        $response = $this->get('/_caronte/session-check?tab=roles');

        $response->assertRedirect();

        $callbackUrl = $this->decodedCallbackUrlFromRedirect($response);

        $this->assertSame('/_caronte/session-check', parse_url($callbackUrl, PHP_URL_PATH));
        $this->assertSame('tab=roles', parse_url($callbackUrl, PHP_URL_QUERY));
    }

    public function test_inertia_session_middleware_uses_location_response_for_expired_sessions(): void
    {
        $response = $this->get('/_caronte/session-check', [
            'X-Inertia' => 'true',
        ])
            ->assertStatus(409)
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));

        $location = (string) $response->baseResponse->headers->get('X-Inertia-Location');
        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));
        $this->assertSame('/_caronte/session-check', parse_url($this->callbackUrlFromLoginUrl($location), PHP_URL_PATH));
    }

    public function test_inertia_forward_error_preserves_errors_and_input(): void
    {
        $response = $this->post('/_caronte/inertia-forward-error', [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'password' => 'secret',
        ], [
            'X-Inertia' => 'true',
        ])
            ->assertStatus(409)
            ->assertSessionHas('status', 422)
            ->assertSessionHas('message', 'Validation failed.')
            ->assertSessionHas('error', 'Validation failed.')
            ->assertSessionHasErrors([
                'email' => 'Invalid email.',
            ]);

        $location = (string) $response->baseResponse->headers->get('X-Inertia-Location');

        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));
        $this->assertSame('Jane Doe', session()->getOldInput('name'));
        $this->assertSame('not-an-email', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_oidc_login_preserves_intended_callback_through_successful_callback(): void
    {
        config()->set('caronte.auth_mode', 'oidc');
        config()->set('caronte.oidc.issuer', 'https://caronte.test');
        config()->set('caronte.oidc.client_id', 'test-app-id');
        config()->set('caronte.oidc.client_secret', 'oidc-secret');
        config()->set('caronte.oidc.redirect_uri', 'https://client.test/oidc/callback');

        $this->fakeOidcValidator();

        $targetUrl = 'http://localhost/reports/monthly?tab=roles';

        $this->get('/login?callback_url=' . urlencode(base64_encode($targetUrl)))
            ->assertRedirectContains('/oidc/login?callback_url=');

        $this->get('/oidc/login?callback_url=' . urlencode(base64_encode($targetUrl)))
            ->assertRedirectContains('https://caronte.test/oauth/authorize');

        $state = (string) session('caronte.oidc.state');
        $nonce = (string) session('caronte.oidc.nonce');
        $token = $this->makeOidcToken(nonce: $nonce);

        Http::fake([
            'https://caronte.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'id_token' => $token,
                'refresh_token' => 'new-refresh-token',
            ], 200),
        ]);

        $this->get('/oidc/callback?state=' . urlencode($state) . '&code=code-123')
            ->assertRedirect($targetUrl)
            ->assertSessionHas((string) config('caronte.session_key', 'caronte.user_token'), $token)
            ->assertSessionMissing('caronte.oidc.callback_url');
    }

    public function test_oidc_callback_rejects_missing_state_and_clears_login_session(): void
    {
        session([
            'caronte.oidc.state' => 'expected-state',
            'caronte.oidc.nonce' => 'expected-nonce',
            'caronte.oidc.code_verifier' => 'expected-verifier',
            'caronte.oidc.callback_url' => '/dashboard',
        ]);

        $this->get('/oidc/callback?code=code-123')
            ->assertRedirect('/login')
            ->assertSessionMissing('caronte.oidc.state')
            ->assertSessionMissing('caronte.oidc.nonce')
            ->assertSessionMissing('caronte.oidc.code_verifier')
            ->assertSessionMissing('caronte.oidc.callback_url');
    }

    public function test_oidc_callback_rejects_mismatched_nonce(): void
    {
        config()->set('caronte.oidc.issuer', 'https://caronte.test');
        config()->set('caronte.oidc.client_id', 'test-app-id');
        config()->set('caronte.oidc.client_secret', 'oidc-secret');
        config()->set('caronte.oidc.redirect_uri', 'http://localhost/oidc/callback');
        $this->fakeOidcValidator();

        $this->get('/oidc/login')->assertRedirectContains('https://caronte.test/oauth/authorize');
        $state = (string) session('caronte.oidc.state');

        Http::fake([
            'https://caronte.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'id_token' => $this->makeOidcToken(nonce: 'different-nonce'),
            ], 200),
        ]);

        $this->get('/oidc/callback?state=' . urlencode($state) . '&code=code-123')
            ->assertRedirect('/login')
            ->assertSessionMissing((string) config('caronte.session_key'));
    }

    public function test_single_tenant_session_middleware_binds_configured_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Mobig User',
            'email' => 'mobig@example.com',
            'id_tenant' => 'mobig',
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

    public function test_multi_tenant_session_middleware_binds_token_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'multi');

        $token = $this->makeToken();

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertOk()
            ->assertJsonPath('tenant_context', 'tenant-1');
    }

    public function test_multi_tenant_session_middleware_rejects_token_without_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'multi');

        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Global User',
            'email' => 'global@example.com',
            'id_tenant' => null,
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

    public function test_single_tenant_session_middleware_rejects_token_without_tenant(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-1',
            'name' => 'Global User',
            'email' => 'global@example.com',
            'id_tenant' => null,
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
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $token = $this->makeToken();

        $this->withSession([(string) config('caronte.session_key', 'caronte.user_token') => $token])
            ->getJson('/_caronte/session-check')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant mismatch.')
            ->assertSessionMissing((string) config('caronte.session_key', 'caronte.user_token'));
    }

    public function test_protected_api_access_middleware_accepts_tokens_with_required_scope(): void
    {
        $token = $this->makeProtectedApiAccessToken(['invoices.read']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertOk()
            ->assertHeaderMissing('Deprecation')
            ->assertJsonPath('id_tenant', 'tenant-1')
            ->assertJsonPath('scopes.0', 'invoices.read');
    }

    public function test_protected_api_access_middleware_rejects_missing_scope(): void
    {
        $token = $this->makeProtectedApiAccessToken(['invoices.write']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(403);
    }

    public function test_protected_api_access_middleware_rejects_expired_tokens(): void
    {
        $token = $this->makeProtectedApiAccessToken(
            issuedAt: new DateTimeImmutable('-2 hours', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('-1 hour', new DateTimeZone('UTC')),
        );

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(401);
    }

    public function test_protected_api_access_middleware_rejects_wrong_token_audience(): void
    {
        $token = $this->makeProtectedApiAccessToken(audience: 'application_auth');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(401);
    }

    public function test_protected_api_access_middleware_rejects_wrong_jwt_audience(): void
    {
        $token = $this->makeProtectedApiAccessToken(jwtAudience: 'other-app-id');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(401);
    }

    public function test_protected_api_access_middleware_rejects_invalid_signature(): void
    {
        $token = $this->makeProtectedApiAccessToken(['invoices.read']);

        config()->set('caronte.app_secret', 'different-secret-with-minimum-length-32');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/_caronte/application-access-check')
            ->assertStatus(401);
    }

    /**
     * @param  array<string, mixed>  $claimOverrides
     */
    private function makeApplicationAuthToken(array $claimOverrides = []): string
    {
        $issuedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $issuedAt->modify('+5 minutes');
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText((string) config('caronte.app_secret'))
        );

        $claims = array_merge([
            'token_audience' => 'application_auth',
            'app_id' => CaronteApplicationToken::appId(),
            'app_cn' => CaronteApplicationToken::cn(),
        ], $claimOverrides);

        return $config->builder(ChainedFormatter::default())
            ->issuedBy((string) config('caronte.issuer_id', ''))
            ->permittedFor(CaronteApplicationToken::appId())
            ->identifiedBy('application-auth-token-1')
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('token_audience', $claims['token_audience'])
            ->withClaim('app_id', $claims['app_id'])
            ->withClaim('app_cn', $claims['app_cn'])
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    /**
     * @param  list<string>  $omitted
     * @param  array<string, mixed>  $overrides
     */
    private function makeUserTokenOmitting(array $omitted, array $overrides = []): string
    {
        $issuedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $issuedAt->modify('+15 minutes');
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText((string) config('caronte.app_secret'))
        );
        $builder = $config->builder(ChainedFormatter::default());

        if (! in_array('iss', $omitted, true)) {
            $builder = $builder->issuedBy((string) config('caronte.issuer_id'));
        }
        if (! in_array('aud', $omitted, true)) {
            $builder = $builder->permittedFor(CaronteApplicationToken::appId());
        }
        if (! in_array('sub', $omitted, true)) {
            $builder = $builder->relatedTo('user-123');
        }
        if (! in_array('jti', $omitted, true)) {
            $builder = $builder->identifiedBy('strict-user-token');
        }
        if (! in_array('iat', $omitted, true)) {
            $builder = $builder->issuedAt($issuedAt);
        }
        if (! in_array('nbf', $omitted, true)) {
            $builder = $builder->canOnlyBeUsedAfter($issuedAt);
        }
        if (! in_array('exp', $omitted, true)) {
            $builder = $builder->expiresAt($expiresAt);
        }
        if (! in_array('token_audience', $omitted, true)) {
            $builder = $builder->withClaim('token_audience', $overrides['token_audience'] ?? 'application');
        }
        if (! in_array('id_tenant', $omitted, true)) {
            $builder = $builder->withClaim('id_tenant', null);
        }

        return $builder
            ->withClaim('app_id', CaronteApplicationToken::appId())
            ->withClaim('name', 'Strict User')
            ->withClaim('email', 'strict@example.com')
            ->withClaim('roles', [])
            ->withClaim('metadata', [])
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    private function fakeOidcValidator(): void
    {
        app()->instance(OidcTokenValidator::class, new class extends OidcTokenValidator
        {
            public function validate(string $rawToken, ?string $expectedNonce = null): Plain
            {
                $token = (new Parser(new JoseEncoder()))->parse($rawToken);

                if (! $token instanceof Plain) {
                    throw new \RuntimeException('Invalid OIDC token.');
                }

                if ($expectedNonce !== null) {
                    $nonce = $token->claims()->get('nonce', null);

                    if (! is_string($nonce) || ! hash_equals($expectedNonce, $nonce)) {
                        throw new \RuntimeException('Invalid OIDC token nonce.');
                    }
                }

                return $token;
            }
        });
    }

    private function makeOidcToken(
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $expiresAt = null,
        ?string $nonce = null,
    ): string {
        $issuedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt ??= $issuedAt->modify('+15 minutes');

        $config = \Lcobucci\JWT\Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::plainText('oidc-test-secret-with-minimum-length')
        );

        $builder = $config->builder(\Lcobucci\JWT\Encoding\ChainedFormatter::default())
            ->withHeader('kid', 'oidc-test-kid')
            ->identifiedBy('oidc-token-1')
            ->issuedBy('https://caronte.test')
            ->permittedFor('test-app-id')
            ->relatedTo('user-123')
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->withClaim('id_tenant', 'tenant-1')
            ->withClaim('name', 'Root User')
            ->withClaim('email', 'root@example.com')
            ->withClaim('roles', ['root'])
            ->withClaim('metadata', []);

        if ($nonce !== null) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        return $builder->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    private function decodedCallbackUrlFromRedirect(mixed $response): string
    {
        $location = (string) $response->baseResponse->headers->get('Location');

        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));

        return $this->callbackUrlFromLoginUrl($location);
    }

    private function callbackUrlFromLoginUrl(string $location): string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('callback_url', $query);

        $callbackUrl = base64_decode((string) $query['callback_url'], true);

        $this->assertIsString($callbackUrl);

        return $callbackUrl;
    }
}
