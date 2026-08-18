<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Equidna\Toolkit\Exceptions\UnprocessableEntityException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\MessageBag;
use Ometra\Caronte\Api\CaronteApiClient;
use Ometra\Caronte\Api\AuthApi;
use Ometra\Caronte\Api\ProvisioningApi;
use Ometra\Caronte\Caronte;
use Ometra\Caronte\CaronteUserToken;
use Ometra\Caronte\Contracts\SendsPasswordRecovery;
use Ometra\Caronte\Contracts\SendsTwoFactorChallenge;
use Ometra\Caronte\Mail\PasswordRecoveryMail;
use Ometra\Caronte\Mail\TwoFactorChallengeMail;
use Ometra\Caronte\Models\CaronteUser;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    public function test_login_uses_current_caronte_headers_and_persists_token_in_session(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/');
        $this->assertSame($token, session(config('caronte.session_key')));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $this->hasValidApplicationTokenHeader($request)
                && $request['email'] === 'root@example.com'
                && $request['password'] === 'Password123!';
        });
    }

    public function test_api_login_returns_token_without_persisting_session(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->postJson('/api/caronte/auth/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.token', $token);

        $this->assertFalse(session()->has((string) config('caronte.session_key')));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $this->hasValidApplicationTokenHeader($request)
                && $request['email'] === 'root@example.com'
                && $request['password'] === 'Password123!';
        });
    }

    public function test_api_login_returns_json_error_for_invalid_credentials(): void
    {
        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 401,
                'message' => 'Invalid credentials.',
                'errors' => ['Invalid credentials.'],
            ], 401),
        ]);

        $this->postJson('/api/caronte/auth/login', [
            'email' => 'root@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_api_login_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/caronte/auth/login', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_api_login_returns_tenant_selection_payload(): void
    {
        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 409,
                'message' => 'Tenant selection required.',
                'errors' => [
                    'code' => 'tenant_selection_required',
                    'tenants' => [
                        ['id_tenant' => 'tenant-a', 'name' => 'Tenant A', 'global' => false],
                        ['id_tenant' => 'tenant-b', 'name' => 'Tenant B', 'global' => false],
                    ],
                    'tenant_selection_token' => 'selection-token',
                ],
            ], 409),
        ]);

        $this->postJson('/api/caronte/auth/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.tenants.0.id_tenant', 'tenant-a')
            ->assertJsonPath('data.tenant_selection_token', 'selection-token');

        $this->assertFalse(session()->has('caronte.pending_login'));
    }

    public function test_api_login_accepts_tenant_selection_token_without_reposting_password(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->postJson('/api/caronte/auth/login', [
            'email' => 'shared@example.com',
            'id_tenant' => 'tenant-b',
            'tenant_selection_token' => 'selection-token',
        ])
            ->assertOk()
            ->assertJsonPath('data.token', $token);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $request['email'] === 'shared@example.com'
                && ($request['id_tenant'] ?? null) === 'tenant-b'
                && ($request['tenant_selection_token'] ?? null) === 'selection-token'
                && ! array_key_exists('password', $request->data());
        });
    }

    public function test_api_me_returns_authenticated_user_payload(): void
    {
        $token = $this->makeToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/caronte/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.uri_user', 'user-123')
            ->assertJsonPath('data.user.email', 'root@example.com')
            ->assertJsonPath('data.id_tenant', 'tenant-1')
            ->assertJsonPath('data.roles.0.name', 'root');
    }

    public function test_api_me_requires_bearer_token(): void
    {
        $this->getJson('/api/caronte/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Token not found');
    }

    public function test_api_me_rejects_user_without_access_to_host_application(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-foreign',
            'name' => 'Foreign User',
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

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/caronte/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('message', 'User does not have access to this application.');
    }

    public function test_api_logout_revokes_bearer_token(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/logout' => Http::response([
                'status' => 200,
                'message' => 'Logout successful',
                'data' => [],
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/caronte/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful');

        Http::assertSent(function ($request) use ($token): bool {
            return $request->url() === 'https://caronte.test/api/auth/logout'
                && $request->method() === 'POST'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $token);
        });
    }

    public function test_api_logout_uses_refreshed_token_when_bearer_was_exchanged(): void
    {
        $expiredToken = $this->makeToken(
            issuedAt: new DateTimeImmutable('-30 minutes', new DateTimeZone('UTC')),
            expiresAt: new DateTimeImmutable('-5 minutes', new DateTimeZone('UTC'))
        );
        $freshToken = $this->makeToken([
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'id_tenant' => 'tenant-1',
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [
                ['scope' => CaronteApplicationToken::appId(), 'key' => 'theme', 'value' => 'dark'],
            ],
        ]);

        Http::fake([
            'https://caronte.test/api/auth/exchange' => Http::response([
                'status' => 200,
                'message' => 'Token exchanged',
                'data' => ['token' => $freshToken],
            ], 200),
            'https://caronte.test/api/auth/logout' => Http::response([
                'status' => 200,
                'message' => 'Logout successful',
                'data' => [],
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $expiredToken)
            ->postJson('/api/caronte/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful');

        Http::assertSentCount(2);

        Http::assertSent(function ($request) use ($expiredToken): bool {
            return $request->url() === 'https://caronte.test/api/auth/exchange'
                && $request->method() === 'POST'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $expiredToken);
        });

        Http::assertSent(function ($request) use ($freshToken): bool {
            return $request->url() === 'https://caronte.test/api/auth/logout'
                && $request->method() === 'POST'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $freshToken);
        });
    }

    public function test_login_redirects_back_to_intended_protected_route(): void
    {
        Route::middleware(['web', 'caronte.session'])
            ->get('/reports/monthly', fn() => response('ok'));

        $redirectToLogin = $this->get('/reports/monthly?tab=roles');

        $redirectToLogin->assertRedirect();

        $location = (string) $redirectToLogin->baseResponse->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('/login', parse_url($location, PHP_URL_PATH));
        $this->assertArrayHasKey('callback_url', $query);

        $callbackUrl = base64_decode((string) $query['callback_url'], true);

        $this->assertIsString($callbackUrl);
        $this->assertSame('/reports/monthly', parse_url($callbackUrl, PHP_URL_PATH));
        $this->assertSame('tab=roles', parse_url($callbackUrl, PHP_URL_QUERY));

        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->post('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
            'callback_url' => $query['callback_url'],
        ])->assertRedirect($callbackUrl);

        $this->assertSame($token, session(config('caronte.session_key')));
    }

    public function test_login_sends_only_group_token_when_group_is_configured(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->post('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $this->hasValidGroupTokenHeader($request)
                && ! $request->hasHeader('X-Application-Token');
        });
    }

    public function test_web_login_requests_and_stores_all_tenant_tokens(): void
    {
        $tenantA = $this->makeToken();
        $tenantB = $this->makeToken([
            'uri_user' => 'user-123', 'name' => 'Root User', 'email' => 'root@example.com',
            'id_tenant' => 'tenant-2', 'tenant_name' => 'Tenant 2', 'roles' => [[
                'name' => 'root', 'app_id' => CaronteApplicationToken::appId(),
                'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
            ]], 'metadata' => [],
        ]);

        Http::fake(['https://caronte.test/api/auth/login' => Http::response([
            'status' => 200,
            'message' => 'Token generated',
            'data' => [
                'token' => $tenantA,
                'tokens' => [
                    ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $tenantA],
                    ['id_tenant' => 'tenant-2', 'name' => 'Tenant 2', 'token' => $tenantB],
                ],
            ],
        ])]);

        $this->post('/login', ['email' => 'root@example.com', 'password' => 'Password123!'])
            ->assertRedirect('/');

        $this->assertSame($tenantA, session('caronte.tenant_tokens.tenant-1.token'));
        $this->assertSame($tenantB, session('caronte.tenant_tokens.tenant-2.token'));
        $this->assertSame('tenant-1', session('caronte.last_tenant_id'));
        Http::assertSent(fn($request): bool => $request->url() === 'https://caronte.test/api/auth/login'
            && $request['include_tenant_tokens'] === true);
    }

    public function test_web_logout_revokes_globally_and_clears_the_portfolio(): void
    {
        $token = $this->makeToken();
        Http::fake(['https://caronte.test/api/auth/logoutAll' => Http::response([
            'status' => 200, 'message' => 'Logged out', 'data' => [],
        ])]);

        $this->withSession([
            config('caronte.session_key') => $token,
            'caronte.tenant_tokens' => [
                'tenant-1' => ['id_tenant' => 'tenant-1', 'name' => 'Tenant 1', 'token' => $token],
            ],
            'caronte.last_tenant_id' => 'tenant-1',
        ])->post('/logout')->assertRedirect('/login');

        $this->assertNull(session(config('caronte.session_key')));
        $this->assertNull(session('caronte.tenant_tokens'));
        $this->assertNull(session('caronte.last_tenant_id'));
        Http::assertSent(fn($request): bool => $request->url() === 'https://caronte.test/api/auth/logoutAll');
    }

    public function test_login_redirects_with_tenant_options_when_selection_is_required(): void
    {
        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 409,
                'message' => 'Tenant selection required.',
                'errors' => [
                    'code' => 'tenant_selection_required',
                    'tenants' => [
                        ['id_tenant' => 'tenant-a', 'name' => 'Tenant A', 'global' => false],
                        ['id_tenant' => 'tenant-b', 'name' => 'Tenant B', 'global' => false],
                    ],
                    'tenant_selection_token' => 'selection-token',
                ],
            ], 409),
        ]);

        $response = $this->post('/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('data.tenants.0.id_tenant', 'tenant-a');
        $response->assertSessionHas('info', 'Select a tenant to continue.');
        $response->assertSessionHasNoErrors();
        $this->assertSame(
            'selection-token',
            session('caronte.pending_login.tenant_selection_token')
        );
    }

    public function test_login_sends_selected_tenant_to_caronte(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->post('/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
            'id_tenant' => 'tenant-b',
        ])->assertRedirect('/');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $request['email'] === 'shared@example.com'
                && $request['password'] === 'Password123!'
                && ($request['id_tenant'] ?? null) === 'tenant-b';
        });
    }

    public function test_single_tenant_login_sends_configured_tenant_to_caronte(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $token = $this->makeToken(['id_tenant' => 'mobig'] + [
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [],
        ]);

        Http::fake([
            'https://caronte.test/api/auth/login' => Http::response([
                'status' => 200,
                'message' => 'Token generated',
                'data' => ['token' => $token],
            ], 200),
        ]);

        $this->post('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && ($request['id_tenant'] ?? null) === 'mobig';
        });
    }

    public function test_single_tenant_login_rejects_explicit_tenant_mismatch(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $this->postJson('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
            'id_tenant' => 'other-tenant',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant mismatch.');

        Http::assertNothingSent();
    }

    public function test_login_uses_pending_credentials_when_tenant_is_selected(): void
    {
        $token = $this->makeToken();

        Http::fake(function ($request) use ($token) {
            if ($request->url() !== 'https://caronte.test/api/auth/login') {
                return Http::response([], 404);
            }

            if (
                ($request['id_tenant'] ?? null) === 'tenant-b'
                && ($request['tenant_selection_token'] ?? null) === 'selection-token'
                && ! array_key_exists('password', $request->data())
            ) {
                return Http::response([
                    'status' => 200,
                    'message' => 'Token generated',
                    'data' => ['token' => $token],
                ], 200);
            }

            return Http::response([
                'status' => 409,
                'message' => 'Tenant selection required.',
                'errors' => [
                    'code' => 'tenant_selection_required',
                    'tenants' => [
                        ['id_tenant' => 'tenant-a', 'name' => 'Tenant A', 'global' => false],
                        ['id_tenant' => 'tenant-b', 'name' => 'Tenant B', 'global' => false],
                    ],
                    'tenant_selection_token' => 'selection-token',
                ],
            ], 409);
        });

        $this->post('/login', [
            'email' => 'shared@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'shared@example.com',
            'id_tenant' => 'tenant-b',
        ])->assertRedirect('/');

        $this->assertSame($token, session(config('caronte.session_key')));
        $this->assertFalse(session()->has('caronte.pending_login'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $request['email'] === 'shared@example.com'
                && ($request['id_tenant'] ?? null) === 'tenant-b'
                && ($request['tenant_selection_token'] ?? null) === 'selection-token'
                && ! array_key_exists('password', $request->data());
        });
    }

    public function test_local_user_sync_persists_token_tenant(): void
    {
        Schema::dropIfExists('Users');
        Schema::create('Users', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('id_tenant', 64)->index();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->primary(['uri_user', 'id_tenant']);
        });

        Caronte::updateUserData((object) [
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'id_tenant' => 'tenant-1',
            'metadata' => [],
        ]);

        $this->assertSame(
            'tenant-1',
            CaronteUser::withoutGlobalScopes()
                ->where('uri_user', 'user-123')
                ->where('id_tenant', 'tenant-1')
                ->value('id_tenant')
        );
    }

    public function test_user_payload_prefers_explicit_jwt_claims(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-claims',
            'name' => 'Claim User',
            'email' => 'claims@example.com',
            'id_tenant' => 'claims-tenant',
            'tenant_name' => 'Claims Tenant',
            'roles' => [],
            'metadata' => [],
        ]);

        $parsed = CaronteUserToken::validateToken($token);
        $user = CaronteUserToken::userPayload($parsed);

        $this->assertSame('user-claims', $user->uri_user);
        $this->assertSame('Claim User', $user->name);
        $this->assertSame('claims@example.com', $user->email);
        $this->assertSame('claims-tenant', $user->id_tenant);
        $this->assertSame('Claims Tenant', $user->tenant_name);
    }

    public function test_user_payload_supports_flat_claims(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-flat',
            'name' => 'Flat Claim User',
            'email' => 'flat@example.com',
            'id_tenant' => 'tenant-flat',
            'roles' => [
                [
                    'name' => 'root',
                    'app_id' => CaronteApplicationToken::appId(),
                    'uri_applicationRole' => sha1(CaronteApplicationToken::appId() . 'root'),
                ],
            ],
            'metadata' => [
                [
                    'scope' => CaronteApplicationToken::appId(),
                    'key' => 'theme',
                    'value' => 'dark',
                ],
            ],
        ]);

        $parsed = CaronteUserToken::validateToken($token);
        $user = CaronteUserToken::userPayload($parsed);

        $this->assertSame('user-flat', $user->uri_user);
        $this->assertSame('Flat Claim User', $user->name);
        $this->assertSame('flat@example.com', $user->email);
        $this->assertSame('tenant-flat', $user->id_tenant);
        $this->assertSame('root', $user->roles[0]->name);
        $this->assertSame('theme', $user->metadata[0]->key);
    }

    public function test_user_token_accepts_iat_and_nbf_within_configured_clock_skew(): void
    {
        config()->set('caronte.token.clock_skew_seconds', 120);

        $token = $this->makeToken(
            issuedAt: new DateTimeImmutable('+90 seconds', new DateTimeZone('UTC')),
        );

        $parsed = CaronteUserToken::validateToken($token);

        $this->assertSame(CaronteApplicationToken::appId(), $parsed->claims()->get('app_id'));
    }

    public function test_user_token_rejects_iat_and_nbf_beyond_configured_clock_skew(): void
    {
        config()->set('caronte.token.clock_skew_seconds', 30);

        $token = $this->makeToken(
            issuedAt: new DateTimeImmutable('+45 seconds', new DateTimeZone('UTC')),
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Token is not yet valid.');

        CaronteUserToken::validateToken($token);
    }

    public function test_host_notification_delivery_uses_issue_endpoints_and_package_mailables(): void
    {
        config()->set('caronte.notification_delivery', 'host');

        Http::fake([
            'https://caronte.test/api/auth/two-factor/issue' => Http::response([
                'status' => 200,
                'message' => '2FA challenge issued',
                'data' => [
                    'email' => 'root@example.com',
                    'action_url' => 'https://client.test/two-factor/example-token',
                    'expires_at' => '2026-04-25T10:00:00Z',
                ],
            ], 200),
            'https://caronte.test/api/auth/password/recover/issue' => Http::response([
                'status' => 200,
                'message' => 'Recovery issued',
                'data' => [
                    'email' => 'root@example.com',
                    'action_url' => 'https://client.test/password/recover/example-token',
                    'expires_at' => '2026-04-25T10:00:00Z',
                ],
            ], 200),
        ]);

        Mail::fake();

        $this->post('/two-factor', ['email' => 'root@example.com'])->assertRedirect('/login');
        $this->post('/password/recover', ['email' => 'root@example.com'])->assertRedirect('/login');

        Mail::assertSent(TwoFactorChallengeMail::class);
        Mail::assertSent(PasswordRecoveryMail::class);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/two-factor/issue'
                && $request['email'] === 'root@example.com'
                && isset($request['callback_url'])
                && ! array_key_exists('app_url', $request->data());
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/password/recover/issue'
                && $request['email'] === 'root@example.com'
                && ! array_key_exists('app_url', $request->data());
        });
    }

    public function test_package_auth_views_render_without_explicit_branding(): void
    {
        $routes = [
            'login' => '/login',
            'passwordRecoverForm' => '/password/recover',
            'passwordRecoverRequest' => '/password/recover',
            'passwordRecoverSubmit' => '/password/recover/token',
            'twoFactorRequest' => '/two-factor',
        ];

        $this->assertStringContainsString('Sign in', view('caronte::auth.login', [
            'routes' => $routes,
            'callback_url' => null,
        ])->render());
        $this->assertStringContainsString('caronte-auth__logo', view('caronte::auth.login', [
            'routes' => $routes,
            'callback_url' => null,
        ])->render());
        $this->assertStringContainsString('caronte-auth-background', view('caronte::layouts.base')->render());

        $tenantSelectionHtml = view('caronte::auth.login', [
            'routes' => $routes,
            'callback_url' => null,
            'tenant_options' => [
                ['id_tenant' => 'tenant-a', 'name' => 'Tenant A'],
            ],
            'pending_login' => [
                'email' => 'shared@example.com',
            ],
        ])->render();

        $this->assertStringContainsString('readonly', $tenantSelectionHtml);
        $this->assertStringNotContainsString('name="password"', $tenantSelectionHtml);

        $this->assertStringContainsString('Password recovery', view('caronte::auth.password-recover-request', [
            'routes' => $routes,
        ])->render());
    }

    public function test_package_mailables_render_with_string_expiration(): void
    {
        $expiresAt = '2026-04-25T10:00:00Z';

        $this->assertStringContainsString(
            $expiresAt,
            (new PasswordRecoveryMail('https://client.test/password/recover/example-token', $expiresAt))->render()
        );

        $this->assertStringContainsString(
            $expiresAt,
            (new TwoFactorChallengeMail('https://client.test/two-factor/example-token', $expiresAt))->render()
        );
    }

    public function test_notification_senders_are_resolved_from_configuration(): void
    {
        config()->set('caronte.notifications.two_factor_sender', TestTwoFactorChallengeSender::class);
        config()->set('caronte.notifications.password_recovery_sender', TestPasswordRecoverySender::class);

        TestTwoFactorChallengeSender::$sent = [];
        TestPasswordRecoverySender::$sent = [];

        app(SendsTwoFactorChallenge::class)->send(
            email: 'root@example.com',
            actionUrl: 'https://client.test/two-factor/example-token',
            expiresAt: '2026-04-25T10:00:00Z'
        );

        app(SendsPasswordRecovery::class)->send(
            email: 'root@example.com',
            actionUrl: 'https://client.test/password/recover/example-token',
            expiresAt: '2026-04-25T10:00:00Z'
        );

        $this->assertSame([
            'email' => 'root@example.com',
            'actionUrl' => 'https://client.test/two-factor/example-token',
            'expiresAt' => '2026-04-25T10:00:00Z',
        ], TestTwoFactorChallengeSender::$sent);

        $this->assertSame([
            'email' => 'root@example.com',
            'actionUrl' => 'https://client.test/password/recover/example-token',
            'expiresAt' => '2026-04-25T10:00:00Z',
        ], TestPasswordRecoverySender::$sent);
    }

    public function test_user_request_injects_application_and_current_user_tokens(): void
    {
        $token = $this->makeToken();

        Route::middleware('web')->get('/_caronte/user-request-check', function () {
            app(CaronteApiClient::class)->userRequest(
                method: 'get',
                endpoint: 'api/auth/current-user'
            );

            return response('ok');
        });

        Http::fake([
            'https://caronte.test/api/auth/current-user' => Http::response([
                'status' => 200,
                'message' => 'Current user retrieved',
                'data' => [],
            ], 200),
        ]);

        $this->withSession([
            config('caronte.session_key') => $token,
        ])->get('/_caronte/user-request-check')->assertOk();

        Http::assertSent(function ($request) use ($token): bool {
            return $request->url() === 'https://caronte.test/api/auth/current-user'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $token);
        });
    }

    public function test_user_request_injects_single_tenant_context_header(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.id_tenant', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
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

        Route::middleware(['web', 'caronte.session'])->get('/_caronte/single-user-request-check', function () {
            app(CaronteApiClient::class)->userRequest(
                method: 'get',
                endpoint: 'api/auth/current-user'
            );

            return response('ok');
        });

        Http::fake([
            'https://caronte.test/api/auth/current-user' => Http::response([
                'status' => 200,
                'message' => 'Current user retrieved',
                'data' => [],
            ], 200),
        ]);

        $this->withSession([
            config('caronte.session_key') => $token,
        ])->get('/_caronte/single-user-request-check')->assertOk();

        Http::assertSent(function ($request) use ($token): bool {
            return $request->url() === 'https://caronte.test/api/auth/current-user'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $token)
                && $request->hasHeader('X-Tenant-Id', 'mobig');
        });
    }

    public function test_logout_api_sends_application_and_user_tokens(): void
    {
        $token = $this->makeToken();

        Http::fake([
            'https://caronte.test/api/auth/logout' => Http::response([
                'status' => 200,
                'message' => 'Logout successful',
                'data' => [],
            ], 200),
        ]);

        AuthApi::logout($token);

        Http::assertSent(function ($request) use ($token): bool {
            return $request->url() === 'https://caronte.test/api/auth/logout'
                && $request->method() === 'POST'
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-User-Token', $token);
        });
    }

    public function test_provisioning_api_wraps_tenant_provisioning_endpoint(): void
    {
        Http::fake([
            'https://caronte.test/api/provisioning/tenants' => Http::response([
                'status' => 201,
                'message' => 'Tenant provisioned',
                'data' => [],
            ], 201),
        ]);

        ProvisioningApi::provisionTenant([
            'external_id' => 'external-account-1',
            'tenant' => [
                'name' => 'External Account',
                'description' => 'Provisioned account',
            ],
            'admin' => [
                'email' => 'owner@example.com',
                'name' => 'Tenant Owner',
                'password' => 'Password123!',
            ],
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/provisioning/tenants'
                && $request->method() === 'POST'
                && $this->hasValidApplicationTokenHeader($request)
                && $request['external_id'] === 'external-account-1'
                && $request['tenant']['name'] === 'External Account'
                && $request['admin']['email'] === 'owner@example.com';
        });
    }

    public function test_web_json_requests_read_user_token_from_session(): void
    {
        $token = $this->makeToken();

        Route::middleware(['web', 'caronte.session'])
            ->get('/_caronte/web-json-session-check', fn() => response()->json(['ok' => true]));

        $this->withSession([
            config('caronte.session_key') => $token,
        ])->get('/_caronte/web-json-session-check', [
            'Accept' => 'application/json',
        ])->assertOk();
    }

    public function test_group_user_token_validates_with_group_secret_and_group_id(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        $token = $this->makeToken(group: true);
        $parsed = CaronteUserToken::validateToken($token);

        $this->assertSame('application_group', $parsed->claims()->get('token_audience'));
        $this->assertSame('core-suite', $parsed->claims()->get('group_id'));
        $this->assertSame(sha1('source-app'), $parsed->claims()->get('app_id'));
        $this->assertSame(sha1('source-app'), $parsed->claims()->get('source_app_id'));
    }

    public function test_flash_partial_deduplicates_error_messages(): void
    {
        session()->flash('error', 'Token not found');
        session()->flash('message', 'Token not found');

        $html = view('caronte::partials.messages', [
            'errors' => (new ViewErrorBag())->put('default', new MessageBag([
                'general' => 'Token not found',
            ])),
        ])->render();

        $this->assertSame(1, substr_count($html, 'alert alert-danger'));
        $this->assertSame(0, substr_count($html, 'alert alert-info'));
    }
}

class TestTwoFactorChallengeSender implements SendsTwoFactorChallenge
{
    /** @var array{email?: string, actionUrl?: string, expiresAt?: string|null} */
    public static array $sent = [];

    public function send(string $email, string $actionUrl, ?string $expiresAt = null): void
    {
        self::$sent = compact('email', 'actionUrl', 'expiresAt');
    }
}

class TestPasswordRecoverySender implements SendsPasswordRecovery
{
    /** @var array{email?: string, actionUrl?: string, expiresAt?: string|null} */
    public static array $sent = [];

    public function send(string $email, string $actionUrl, ?string $expiresAt = null): void
    {
        self::$sent = compact('email', 'actionUrl', 'expiresAt');
    }
}
