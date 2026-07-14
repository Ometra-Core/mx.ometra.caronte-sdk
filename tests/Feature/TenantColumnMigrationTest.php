<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenantColumnMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('caronte.table_prefix', 'MigrationTest');
        Schema::dropIfExists('MigrationTestUsers');
    }

    public function testItBackfillsTenantIdAndRemovesLegacyColumn(): void
    {
        $this->createLegacyUsersTable();
        DB::table('MigrationTestUsers')->insert([
            'uri_user' => 'user-1',
            'id_tenant' => 'tenant-a',
            'tenant_id' => null,
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('MigrationTestUsers', 'id_tenant'));
        $this->assertTrue(Schema::hasColumn('MigrationTestUsers', 'tenant_id'));
        $this->assertSame('tenant-a', DB::table('MigrationTestUsers')->value('tenant_id'));
    }

    public function testItKeepsLegacyColumnWhenRowsCannotBeBackfilled(): void
    {
        $this->createLegacyUsersTable();
        DB::table('MigrationTestUsers')->insert([
            'uri_user' => 'user-1',
            'id_tenant' => null,
            'tenant_id' => null,
        ]);

        try {
            $this->migration()->up();
            $this->fail('The migration should reject rows without tenant context.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('do not have a tenant_id', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('MigrationTestUsers', 'id_tenant'));
        }
    }

    private function createLegacyUsersTable(): void
    {
        Schema::create('MigrationTestUsers', function (Blueprint $table): void {
            $table->string('uri_user', 40)->primary();
            $table->string('id_tenant', 64)->nullable();
            $table->string('tenant_id', 64)->nullable();
        });
    }

    private function migration(): object
    {
        return require dirname(__DIR__, 2) . '/database/migrations/zz_standardize_tenant_id_column.php';
    }
}
