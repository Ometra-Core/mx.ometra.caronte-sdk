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

    public function test_login_sends_application_and_group_tokens_when_group_is_configured(): void
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
                && $this->hasValidApplicationTokenHeader($request)
                && $this->hasValidGroupTokenHeader($request);
        });
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
                        ['tenant_id' => 'tenant-a', 'name' => 'Tenant A', 'global' => false],
                        ['tenant_id' => 'tenant-b', 'name' => 'Tenant B', 'global' => false],
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
        $response->assertSessionHas('data.tenants.0.tenant_id', 'tenant-a');
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
            'tenant_id' => 'tenant-b',
        ])->assertRedirect('/');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $request['email'] === 'shared@example.com'
                && $request['password'] === 'Password123!'
                && ($request['tenant_id'] ?? null) === 'tenant-b';
        });
    }

    public function test_single_tenant_login_sends_configured_tenant_to_caronte(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $token = $this->makeToken(['tenant_id' => 'mobig'] + [
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
                && ($request['tenant_id'] ?? null) === 'mobig';
        });
    }

    public function test_single_tenant_login_rejects_explicit_tenant_mismatch(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $this->postJson('/login', [
            'email' => 'root@example.com',
            'password' => 'Password123!',
            'tenant_id' => 'other-tenant',
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
                ($request['tenant_id'] ?? null) === 'tenant-b'
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
                        ['tenant_id' => 'tenant-a', 'name' => 'Tenant A', 'global' => false],
                        ['tenant_id' => 'tenant-b', 'name' => 'Tenant B', 'global' => false],
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
            'tenant_id' => 'tenant-b',
        ])->assertRedirect('/');

        $this->assertSame($token, session(config('caronte.session_key')));
        $this->assertFalse(session()->has('caronte.pending_login'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/auth/login'
                && $request['email'] === 'shared@example.com'
                && ($request['tenant_id'] ?? null) === 'tenant-b'
                && ($request['tenant_selection_token'] ?? null) === 'selection-token'
                && ! array_key_exists('password', $request->data());
        });
    }

    public function test_local_user_sync_persists_token_tenant(): void
    {
        Schema::dropIfExists('Users');
        Schema::create('Users', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('tenant_id', 64)->index();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->primary(['uri_user', 'tenant_id']);
        });

        Caronte::updateUserData((object) [
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
            'tenant_id' => 'tenant-1',
            'metadata' => [],
        ]);

        $this->assertSame(
            'tenant-1',
            CaronteUser::withoutGlobalScopes()
                ->where('uri_user', 'user-123')
                ->where('tenant_id', 'tenant-1')
                ->value('tenant_id')
        );
    }

    public function test_user_payload_prefers_explicit_jwt_claims(): void
    {
        $token = $this->makeToken([
            'uri_user' => 'user-claims',
            'name' => 'Legacy Name',
            'email' => 'legacy@example.com',
            'tenant_id' => 'legacy-tenant',
            'roles' => [],
            'metadata' => [],
        ]);

        $parsed = CaronteUserToken::validateToken($token);
        $user = CaronteUserToken::userPayload($parsed);

        $this->assertSame('user-claims', $user->uri_user);
        $this->assertSame('Legacy Name', $user->name);
        $this->assertSame('legacy@example.com', $user->email);
        $this->assertSame('legacy-tenant', $user->tenant_id);
    }

    public function test_user_payload_supports_tokens_without_legacy_user_claim(): void
    {
        $token = $this->makeTokenWithoutLegacyUserClaim([
            'uri_user' => 'user-flat',
            'name' => 'Flat Claim User',
            'email' => 'flat@example.com',
            'tenant_id' => 'tenant-flat',
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
        $this->assertSame('tenant-flat', $user->tenant_id);
        $this->assertSame('root', $user->roles[0]->name);
        $this->assertSame('theme', $user->metadata[0]->key);
    }

    public function test_user_token_accepts_iat_and_nbf_within_configured_clock_skew(): void
    {
        config()->set('caronte.token_clock_skew_seconds', 120);

        $token = $this->makeToken(
            issuedAt: new DateTimeImmutable('+90 seconds', new DateTimeZone('UTC')),
        );

        $parsed = CaronteUserToken::validateToken($token);

        $this->assertSame(CaronteApplicationToken::appId(), $parsed->claims()->get('app_id'));
    }

    public function test_user_token_rejects_iat_and_nbf_beyond_configured_clock_skew(): void
    {
        config()->set('caronte.token_clock_skew_seconds', 30);

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

        $tenantSelectionHtml = view('caronte::auth.login', [
            'routes' => $routes,
            'callback_url' => null,
            'tenant_options' => [
                ['tenant_id' => 'tenant-a', 'name' => 'Tenant A'],
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
        config()->set('caronte.tenancy.tenant_id', 'mobig');

        $token = $this->makeToken([
            'uri_user' => 'user-123',
            'name' => 'Root User',
            'email' => 'root@example.com',
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
