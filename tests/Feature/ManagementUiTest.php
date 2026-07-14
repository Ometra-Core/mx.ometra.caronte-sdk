<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagementUiTest extends TestCase
{
    public function test_management_dashboard_renders_with_session_token_and_remote_data(): void
    {
        Http::fake([
            'https://caronte.test/api/users*' => Http::response([
                'status' => 200,
                'message' => 'Users retrieved',
                'data' => [
                    ['uri_user' => 'user-1', 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
                ],
            ], 200),
            'https://caronte.test/api/applications/roles' => Http::response([
                'status' => 200,
                'message' => 'Roles retrieved',
                'data' => [
                    ['uri_applicationRole' => sha1(\Ometra\Caronte\Support\CaronteApplicationToken::appId() . 'root'), 'name' => 'root', 'description' => 'Default super administrator role'],
                    ['uri_applicationRole' => sha1(\Ometra\Caronte\Support\CaronteApplicationToken::appId() . 'admin'), 'name' => 'admin', 'description' => 'Administrative access'],
                ],
            ], 200),
            'https://caronte.test/api/application-groups/current/roles' => Http::response([
                'status' => 200,
                'message' => 'Application group roles retrieved',
                'data' => [
                    'applications' => [
                        [
                            'app_id' => 'billing-app',
                            'name' => 'Billing',
                            'roles' => [
                                ['uri_applicationRole' => 'role-root', 'app_id' => 'billing-app', 'name' => 'root', 'manageable' => false],
                                ['uri_applicationRole' => 'role-viewer', 'app_id' => 'billing-app', 'name' => 'viewer', 'manageable' => true],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://caronte.test/api/application-groups/current/users*' => Http::response([
                'status' => 200,
                'message' => 'Application group users retrieved',
                'data' => [
                    'users' => [
                        ['uri_user' => 'user-1', 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'roles' => []],
                    ],
                ],
            ], 200),
        ]);

        $this->withSession([
            config('caronte.session_key') => $this->makeToken(),
        ])->get('/caronte/management')
            ->assertOk()
            ->assertSee('User management')
            ->assertSee('Suite access')
            ->assertSee('Create user')
            ->assertSee('Jane Doe');
    }

}
