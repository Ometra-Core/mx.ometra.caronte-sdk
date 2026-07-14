<?php

namespace Tests\Feature;

use Equidna\BeeHive\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Ometra\Caronte\Helpers\CaronteUserHelper;
use Ometra\Caronte\Exceptions\TenantMissingException;
use Ometra\Caronte\Models\CaronteUser;
use Ometra\Caronte\Tenancy\Resolvers\CaronteTenantResolver;
use Tests\TestCase;

class ResolvedOpenQuestionsTest extends TestCase
{
    public function test_caronte_tenant_resolver_reads_the_authenticated_user_tenant(): void
    {
        Route::middleware('web')->get('/_caronte/tenant-resolver', function () {
            return response()->json([
                'id_tenant' => (new CaronteTenantResolver())->resolveTenantId(),
            ]);
        });

        $this->withSession([
            config('caronte.session_key') => $this->makeToken(),
        ])->getJson('/_caronte/tenant-resolver')
            ->assertOk()
            ->assertJsonPath('id_tenant', 'tenant-1');
    }

    public function test_management_dashboard_can_render_as_inertia_response(): void
    {
        config()->set('caronte.ui.use_inertia', true);

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
                    ['uri_applicationRole' => 'role-root', 'name' => 'root', 'description' => 'Default super administrator role'],
                ],
            ], 200),
            'https://caronte.test/api/application-groups/current/roles' => Http::response([
                'status' => 200,
                'message' => 'Application group roles retrieved',
                'data' => ['applications' => []],
            ], 200),
            'https://caronte.test/api/application-groups/current/users*' => Http::response([
                'status' => 200,
                'message' => 'Application group users retrieved',
                'data' => ['users' => []],
            ], 200),
        ]);

        $this->withSession([
            config('caronte.session_key') => $this->makeToken(),
        ])->get('/caronte/management', [
            'X-Inertia' => 'true',
        ])
            ->assertOk()
            ->assertJsonPath('component', 'management/index')
            ->assertJsonPath('props.id_tenant', 'tenant-1')
            ->assertJsonPath('props.suite_access.enabled', true)
            ->assertJsonPath('props.users.data.0.email', 'jane@example.com');
    }

    public function test_caronte_user_helper_reads_local_user_cache_values(): void
    {
        Schema::dropIfExists('UsersMetadata');
        Schema::dropIfExists('Users');

        Schema::create('Users', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('id_tenant', 64)->index();
            $table->string('name', 150);
            $table->string('email', 150);
            $table->primary(['uri_user', 'id_tenant']);
        });

        Schema::create('UsersMetadata', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('id_tenant', 64);
            $table->string('scope');
            $table->string('key');
            $table->text('value')->nullable();
            $table->primary(['uri_user', 'id_tenant', 'scope', 'key']);
        });

        $tenantContext = new TenantContext();
        $tenantContext->set('tenant-1');
        app()->instance(TenantContext::class, $tenantContext);

        CaronteUser::create([
            'id_tenant' => 'tenant-1',
            'uri_user' => 'user-1',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        DB::table('UsersMetadata')->insert([
            'uri_user' => 'user-1',
            'id_tenant' => 'tenant-1',
            'scope' => 'app-1',
            'key' => 'theme',
            'value' => 'dark',
        ]);
        DB::table('Users')->insert([
            'id_tenant' => 'tenant-2',
            'uri_user' => 'user-1',
            'name' => 'Other Tenant User',
            'email' => 'other@example.com',
        ]);
        DB::table('UsersMetadata')->insert([
            'uri_user' => 'user-1',
            'id_tenant' => 'tenant-2',
            'scope' => 'app-1',
            'key' => 'theme',
            'value' => 'light',
        ]);

        $this->assertSame('Jane Doe', CaronteUserHelper::getUserName('user-1'));
        $this->assertSame('jane@example.com', CaronteUserHelper::getUserEmail('user-1'));
        $this->assertSame('dark', CaronteUserHelper::getUserMetadata('user-1', 'theme'));
        $this->assertSame('User not found', CaronteUserHelper::getUserName('missing'));
        $this->assertNull(CaronteUserHelper::getUserMetadata('user-1', 'missing'));

        $tenantContext->set('tenant-2');

        $this->assertSame('Other Tenant User', CaronteUserHelper::getUserName('user-1'));
        $this->assertSame('other@example.com', CaronteUserHelper::getUserEmail('user-1'));
        $this->assertSame('light', CaronteUserHelper::getUserMetadata('user-1', 'theme'));
    }

    public function test_caronte_user_helper_refuses_unscoped_queries(): void
    {
        app()->forgetInstance(TenantContext::class);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('No tenant context is available.');

        CaronteUserHelper::getUserMetadata('user-1', 'theme');
    }
}
