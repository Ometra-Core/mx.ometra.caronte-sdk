<?php

namespace Tests\Feature;

use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Ometra\Caronte\Api\GroupApi;
use Ometra\Caronte\Support\CaronteApplicationToken;
use Tests\DisabledManagementTestCase;
use Tests\TestCase;

class CommandBehaviorTest extends TestCase
{
    public function test_only_singular_group_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('caronte:group:show', $commands);
        $this->assertArrayHasKey('caronte:group:users:roles:sync', $commands);
        $this->assertArrayNotHasKey('caronte:groups:applications:list', $commands);
        $this->assertArrayNotHasKey('caronte:groups:roles:list', $commands);
        $this->assertArrayNotHasKey('caronte:groups:users:list', $commands);
        $this->assertArrayNotHasKey('caronte:groups:users:roles:sync', $commands);
    }

    public function test_roles_sync_dry_run_does_not_push_remote_changes(): void
    {
        Http::fake([
            'https://caronte.test/api/applications/roles' => Http::response([
                'status' => 200,
                'message' => 'Roles retrieved',
                'data' => [],
            ], 200),
        ]);

        $this->artisan('caronte:roles:sync', ['--dry-run' => true])
            ->expectsOutput('Dry run completed. No remote changes were sent.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn($request): bool => $request->method() === 'GET');
    }

    public function test_roles_sync_command_normalizes_configured_roles_and_calls_sync_endpoint(): void
    {
        Http::fake([
            'https://caronte.test/api/applications/roles' => Http::response([
                'status' => 200,
                'message' => 'Roles synchronized',
                'data' => [],
            ], 200),
        ]);

        $this->artisan('caronte:roles:sync')
            ->expectsOutput('Roles synchronized')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/applications/roles'
                && $request->method() === 'PUT'
                && $this->hasValidApplicationTokenHeader($request)
                && in_array('root', array_column($request['roles'], 'name'), true)
                && in_array('admin', array_column($request['roles'], 'name'), true);
        });
    }

    public function test_roles_sync_command_sends_group_token_when_group_is_configured(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        Http::fake([
            'https://caronte.test/api/applications/roles' => Http::response([
                'status' => 200,
                'message' => 'Roles synchronized',
                'data' => [],
            ], 200),
        ]);

        $this->artisan('caronte:roles:sync')
            ->expectsOutput('Roles synchronized')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/applications/roles'
                && $this->hasValidGroupTokenHeader($request)
                && ! $request->hasHeader('X-Application-Token');
        });
    }

    public function test_protected_api_scopes_sync_command_normalizes_configured_scopes_and_calls_sync_endpoint(): void
    {
        config()->set('caronte.protected_api.scopes', [
            'invoices.read' => 'Read invoices',
            ['scope' => 'invoices.write', 'description' => 'Write invoices'],
        ]);

        Http::fake([
            'https://caronte.test/api/applications/scopes' => Http::response([
                'status' => 200,
                'message' => 'Protected API scopes synchronized',
                'data' => ['scopes' => []],
            ], 200),
        ]);

        $this->artisan('caronte:protected-api:scopes:sync')
            ->expectsOutput('Protected API scopes synchronized')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/applications/scopes'
                && $request->method() === 'PUT'
                && $this->hasValidApplicationTokenHeader($request)
                && in_array('invoices.read', array_column($request['scopes'], 'scope'), true)
                && in_array('invoices.write', array_column($request['scopes'], 'scope'), true);
        });
    }

    public function test_user_list_command_requires_tenant_and_uses_new_endpoint_contract(): void
    {
        Http::fake([
            'https://caronte.test/api/users*' => Http::response([
                'status' => 200,
                'message' => 'Users retrieved',
                'data' => [
                    ['uri_user' => 'user-1', 'id_tenant' => 'tenant-1', 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
                ],
            ], 200),
        ]);

        $this->artisan('caronte:users:list', ['--tenant' => 'tenant-1'])
            ->expectsTable(['URI', 'Tenant', 'Name', 'Email'], [['user-1', 'tenant-1', 'Jane Doe', 'jane@example.com']])
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://caronte.test/api/users')
                && $this->hasValidApplicationTokenHeader($request)
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && $request['app_users'] === 'false';
        });
    }

    public function test_user_list_command_can_limit_search_to_application_users(): void
    {
        Http::fake([
            'https://caronte.test/api/users*' => Http::response([
                'status' => 200,
                'message' => 'Users retrieved',
                'data' => [],
            ], 200),
        ]);

        $this->artisan('caronte:users:list', [
            '--tenant' => 'tenant-1',
            '--search' => 'jane@example.com',
            '--app-users' => true,
        ])
            ->expectsOutput('No users were returned by Caronte.')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://caronte.test/api/users')
                && $request['search'] === 'jane@example.com'
                && $request['app_users'] === 'true';
        });
    }

    public function test_tenant_list_command_calls_tenant_endpoint(): void
    {
        Http::fake([
            'https://caronte.test/api/tenants*' => Http::response([
                'status' => 200,
                'message' => 'Tenants retrieved',
                'data' => [
                    'tenants' => [
                        [
                            'id_tenant' => 'tenant-1',
                            'external_id' => 'external-1',
                            'name' => 'Tenant One',
                            'status' => 'active',
                            'users_count' => 3,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('caronte:tenants:list', ['--search' => 'tenant'])
            ->expectsTable(
                ['Tenant', 'External ID', 'Name', 'Status', 'Users'],
                [['tenant-1', 'external-1', 'Tenant One', 'active', '3']]
            )
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://caronte.test/api/tenants')
                && $this->hasValidApplicationTokenHeader($request)
                && $request['search'] === 'tenant';
        });
    }

    public function test_group_show_command_uses_group_token_and_tenant_context(): void
    {
        config()->set('caronte.application_group_id', 'core-suite');
        config()->set('caronte.application_group_secret', 'group-secret-with-minimum-length-32');

        Http::fake([
            'https://caronte.test/api/group' => Http::response([
                'status' => 200,
                'message' => 'Application group retrieved',
                'data' => [
                    'group' => ['group_id' => 'core-suite', 'name' => 'Core Suite'],
                    'applications' => [
                        [
                            'app_id' => 'billing-app',
                            'cn' => 'billing',
                            'name' => 'Billing',
                            'roles' => [
                                [
                                    'uri_applicationRole' => 'role-billing-viewer',
                                    'name' => 'billing.viewer',
                                    'manageable' => true,
                                ],
                            ],
                            'scopes' => [
                                ['scope' => 'billing.read'],
                            ],
                        ],
                    ],
                    'users' => [[
                        'uri_user' => 'user-1',
                        'id_tenant' => 'tenant-1',
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                        'role_assignments' => [['app_id' => 'billing-app', 'roles' => [['name' => 'billing.viewer']]]],
                    ]],
                ],
            ], 200),
        ]);

        $this->artisan('caronte:group:show', ['--tenant' => 'tenant-1'])
            ->expectsTable(
                ['Application', 'Canonical name', 'Assignable roles', 'API scopes'],
                [['Billing', 'billing', 'billing.viewer', 'billing.read']]
            )
            ->expectsTable(
                ['User', 'Email', 'Tenant', 'Role assignments'],
                [['Jane Doe', 'jane@example.com', 'tenant-1', 1]]
            )
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/group'
                && $this->hasValidGroupTokenHeader($request)
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && ! $request->hasHeader('X-Application-Token');
        });
    }

    public function test_group_user_roles_sync_command_uses_group_role_catalog_and_blocks_root_choices(): void
    {
        Http::fake([
            'https://caronte.test/api/group' => Http::response([
                'status' => 200,
                'message' => 'Application group roles retrieved',
                'data' => [
                    'applications' => [
                        [
                            'app_id' => 'billing-app',
                            'name' => 'Billing',
                            'roles' => [
                                [
                                    'uri_applicationRole' => 'role-root',
                                    'name' => 'root',
                                    'manageable' => false,
                                ],
                                [
                                    'uri_applicationRole' => 'role-billing-viewer',
                                    'name' => 'billing.viewer',
                                    'manageable' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://caronte.test/api/group/users/user-1/applications/billing-app/roles' => Http::response([
                'status' => 200,
                'message' => 'Application group user roles synchronized',
                'data' => ['roles' => []],
            ], 200),
        ]);

        $this->artisan('caronte:group:users:roles:sync', [
            'uri_user' => 'user-1',
            '--tenant' => 'tenant-1',
            '--app' => 'billing-app',
            '--role' => ['billing.viewer'],
        ])
            ->expectsOutput('Application group user roles synchronized')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/group/users/user-1/applications/billing-app/roles'
                && $request->method() === 'PUT'
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && $request['roles'] === ['role-billing-viewer'];
        });
    }

    public function test_group_api_sync_can_send_optional_actor_token(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->set('tenant-1');
        app()->instance(TenantContext::class, $tenantContext);

        Http::fake([
            'https://caronte.test/api/group/users/user-1/applications/billing-app/roles' => Http::response([
                'status' => 200,
                'message' => 'Application group user roles synchronized',
                'data' => ['roles' => []],
            ], 200),
        ]);

        GroupApi::syncGroupUserRoles(
            uriUser: 'user-1',
            appId: 'billing-app',
            roleUris: ['role-billing-viewer'],
            actorToken: 'actor-token'
        );

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('X-User-Token', 'actor-token')
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && $request['roles'] === ['role-billing-viewer'];
        });
    }

    public function test_user_create_command_creates_the_user_and_syncs_configured_roles(): void
    {
        Http::fake([
            'https://caronte.test/api/users' => Http::response([
                'status' => 200,
                'message' => 'User created',
                'data' => [
                    'user' => [
                        'uri_user' => 'user-9',
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                    ],
                ],
            ], 200),
            'https://caronte.test/api/users/user-9/roles' => Http::response([
                'status' => 200,
                'message' => 'Roles synchronized',
                'data' => [],
            ], 200),
        ]);

        $this->artisan('caronte:users:create', [
            '--tenant' => 'tenant-1',
            '--name' => 'Jane Doe',
            '--email' => 'jane@example.com',
            '--password' => 'Password123!',
            '--role' => ['admin'],
        ])
            ->expectsQuestion('Confirm password', 'Password123!')
            ->expectsOutput('User created')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/users'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && $request['email'] === 'jane@example.com';
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://caronte.test/api/users/user-9/roles'
                && $request->method() === 'PUT'
                && $request->hasHeader('X-Tenant-Id', 'tenant-1')
                && $request['roles'] === [sha1(CaronteApplicationToken::appId() . 'admin')];
        });
    }

    public function test_user_roles_sync_previews_replacement_and_requires_confirmation(): void
    {
        $adminUri = sha1(CaronteApplicationToken::appId().'admin');
        $rootUri = sha1(CaronteApplicationToken::appId().'root');

        Http::fake(fn ($request) => $request->method() === 'GET'
            ? Http::response([
                'status' => 200,
                'message' => 'User roles retrieved',
                'data' => [[
                    'uri_applicationRole' => $rootUri,
                    'name' => 'root',
                ]],
            ])
            : Http::response([
                'status' => 200,
                'message' => 'Roles synchronized',
                'data' => [],
            ]));

        $this->artisan('caronte:users:roles:sync', [
            'uri_user' => 'user-1',
            '--tenant' => 'tenant-1',
            '--role' => ['admin'],
        ])
            ->expectsConfirmation('Apply this final role set?', 'yes')
            ->expectsOutput('Roles synchronized')
            ->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://caronte.test/api/users/user-1/roles');
        Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://caronte.test/api/users/user-1/roles'
            && $request['roles'] === [$adminUri]);
    }

    public function test_user_roles_sync_does_not_write_when_confirmation_is_rejected(): void
    {
        Http::fake([
            'https://caronte.test/api/users/user-1/roles' => Http::response([
                'status' => 200,
                'message' => 'User roles retrieved',
                'data' => [],
            ]),
        ]);

        $this->artisan('caronte:users:roles:sync', [
            'uri_user' => 'user-1',
            '--tenant' => 'tenant-1',
            '--clear' => true,
        ])
            ->expectsConfirmation('Apply this final role set?', 'no')
            ->expectsOutput('Role synchronization cancelled.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET');
    }
}
