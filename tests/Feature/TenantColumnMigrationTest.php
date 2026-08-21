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
        Schema::dropIfExists('MigrationTestUsersMetadata');
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

    public function testItMigratesResolvableMetadataAndDeletesAmbiguousOrOrphanedRows(): void
    {
        Schema::create('MigrationTestUsers', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('tenant_id', 64);
            $table->primary(['uri_user', 'tenant_id']);
        });

        DB::table('MigrationTestUsers')->insert([
            ['uri_user' => 'unique-user', 'tenant_id' => 'tenant-b'],
            ['uri_user' => 'ambiguous-user', 'tenant_id' => 'tenant-c'],
            ['uri_user' => 'ambiguous-user', 'tenant_id' => 'tenant-d'],
        ]);

        Schema::create('MigrationTestUsersMetadata', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('id_tenant', 64)->nullable()->index();
            $table->string('tenant_id', 64)->nullable();
            $table->string('scope', 128);
            $table->string('key', 45);
            $table->string('value', 45)->nullable();
            $table->primary(['uri_user', 'id_tenant', 'scope', 'key']);
        });

        DB::table('MigrationTestUsersMetadata')->insert([
            ['uri_user' => 'legacy-user', 'id_tenant' => 'tenant-a', 'tenant_id' => null, 'scope' => 'app', 'key' => 'legacy', 'value' => 'yes'],
            ['uri_user' => 'unique-user', 'id_tenant' => null, 'tenant_id' => null, 'scope' => 'app', 'key' => 'resolved', 'value' => 'yes'],
            ['uri_user' => 'ambiguous-user', 'id_tenant' => null, 'tenant_id' => null, 'scope' => 'app', 'key' => 'ambiguous', 'value' => 'no'],
            ['uri_user' => 'orphan-user', 'id_tenant' => null, 'tenant_id' => null, 'scope' => 'app', 'key' => 'orphan', 'value' => 'no'],
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('MigrationTestUsersMetadata', 'id_tenant'));
        $this->assertSame(2, DB::table('MigrationTestUsersMetadata')->count());
        $this->assertDatabaseHas('MigrationTestUsersMetadata', [
            'uri_user' => 'legacy-user',
            'tenant_id' => 'tenant-a',
        ]);
        $this->assertDatabaseHas('MigrationTestUsersMetadata', [
            'uri_user' => 'unique-user',
            'tenant_id' => 'tenant-b',
        ]);
        $this->assertDatabaseMissing('MigrationTestUsersMetadata', ['uri_user' => 'ambiguous-user']);
        $this->assertDatabaseMissing('MigrationTestUsersMetadata', ['uri_user' => 'orphan-user']);
    }

    private function createLegacyUsersTable(): void
    {
        Schema::create('MigrationTestUsers', function (Blueprint $table): void {
            $table->string('uri_user', 40);
            $table->string('id_tenant', 64)->nullable()->index();
            $table->string('tenant_id', 64)->nullable();
            $table->primary(['uri_user', 'id_tenant']);
        });
    }

    private function migration(): object
    {
        return require dirname(__DIR__, 2) . '/database/migrations/zz_standardize_tenant_id_column.php';
    }
}
