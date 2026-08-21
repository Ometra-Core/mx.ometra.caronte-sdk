<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $metadataPrimaryColumns = ['uri_user', 'tenant_id', 'scope', 'key'];

    public function up(): void
    {
        $prefix = (string) config('caronte.table_prefix');

        $this->standardizeUsers($prefix . 'Users');
        $this->standardizeMetadata($prefix . 'UsersMetadata', $prefix . 'Users');
    }

    public function down(): void
    {
        $prefix = (string) config('caronte.table_prefix');

        foreach ([$prefix . 'Users', $prefix . 'UsersMetadata'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'id_tenant')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('id_tenant', 64)->nullable()->index();
            });

            DB::table($tableName)->update(['id_tenant' => DB::raw('tenant_id')]);
        }
    }

    private function standardizeUsers(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'id_tenant')) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'tenant_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('tenant_id', 64)->nullable()->index();
            });
        }

        $this->copyLegacyTenant($tableName);

        $missingTenantCount = $this->missingTenantQuery($tableName)->count();

        if ($missingTenantCount > 0) {
            throw new RuntimeException(sprintf(
                'Cannot remove legacy column %s.id_tenant: %d rows do not have a tenant_id.',
                $tableName,
                $missingTenantCount
            ));
        }

        $expectedPrimaryColumns = ['uri_user', 'tenant_id'];
        $primaryColumns = $this->primaryColumns($tableName);

        if ($primaryColumns !== [] && $primaryColumns !== $expectedPrimaryColumns) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropPrimary();
            });
        }

        $this->dropIndexesContainingColumn($tableName, 'id_tenant');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('id_tenant');
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('tenant_id', 64)->nullable(false)->change();
        });

        if ($this->primaryColumns($tableName) !== $expectedPrimaryColumns) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->primary(['uri_user', 'tenant_id']);
            });
        }
    }

    private function standardizeMetadata(string $tableName, string $usersTable): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'tenant_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('tenant_id', 64)->nullable();
            });
        }

        if (Schema::hasColumn($tableName, 'id_tenant')) {
            $this->copyLegacyTenant($tableName);
        }

        if (Schema::hasTable($usersTable) && Schema::hasColumn($usersTable, 'tenant_id')) {
            $uriUsers = $this->missingTenantQuery($tableName)
                ->select('uri_user')
                ->distinct()
                ->pluck('uri_user');

            foreach ($uriUsers as $uriUser) {
                $tenantIds = DB::table($usersTable)
                    ->where('uri_user', $uriUser)
                    ->whereNotNull('tenant_id')
                    ->where('tenant_id', '!=', '')
                    ->distinct()
                    ->pluck('tenant_id');

                if ($tenantIds->count() === 1) {
                    $this->missingTenantQuery($tableName)
                        ->where('uri_user', $uriUser)
                        ->update(['tenant_id' => (string) $tenantIds->first()]);
                }
            }
        }

        $discarded = $this->missingTenantQuery($tableName)->delete();

        if ($discarded > 0) {
            Log::warning('Caronte discarded user metadata without an unambiguous tenant during the v6 migration.', [
                'table' => $tableName,
                'discarded_rows' => $discarded,
            ]);
        }

        $primaryColumns = $this->primaryColumns($tableName);

        if ($primaryColumns !== [] && $primaryColumns !== $this->metadataPrimaryColumns) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropPrimary();
            });
        }

        if (Schema::hasColumn($tableName, 'id_tenant')) {
            $this->dropIndexesContainingColumn($tableName, 'id_tenant');

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('id_tenant');
            });
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('tenant_id', 64)->nullable(false)->change();
        });

        if ($this->primaryColumns($tableName) !== $this->metadataPrimaryColumns) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->primary(['uri_user', 'tenant_id', 'scope', 'key']);
            });
        }
    }

    private function copyLegacyTenant(string $tableName): void
    {
        $this->missingTenantQuery($tableName)
            ->whereNotNull('id_tenant')
            ->where('id_tenant', '!=', '')
            ->update(['tenant_id' => DB::raw('id_tenant')]);
    }

    private function missingTenantQuery(string $tableName)
    {
        return DB::table($tableName)->where(function ($query): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', '');
        });
    }

    /** @return list<string> */
    private function primaryColumns(string $tableName): array
    {
        $schemaBuilder = Schema::getConnection()->getSchemaBuilder();

        if (! method_exists($schemaBuilder, 'getIndexes')) {
            return [];
        }

        foreach ($schemaBuilder->getIndexes($tableName) as $index) {
            if (
                ($index['primary'] ?? false) === true
                || strtolower((string) ($index['name'] ?? '')) === 'primary'
            ) {
                return array_values($index['columns'] ?? []);
            }
        }

        return [];
    }

    /**
     * Drops non-primary indexes that depend on a column before removing it.
     *
     * SQLite does not automatically remove these indexes while rebuilding a table.
     */
    private function dropIndexesContainingColumn(string $tableName, string $column): void
    {
        $schemaBuilder = Schema::getConnection()->getSchemaBuilder();

        if (! method_exists($schemaBuilder, 'getIndexes')) {
            return;
        }

        foreach ($schemaBuilder->getIndexes($tableName) as $index) {
            if (
                ($index['primary'] ?? false) === true
                || ! in_array($column, $index['columns'] ?? [], true)
            ) {
                continue;
            }

            $indexName = (string) ($index['name'] ?? '');
            if ($indexName === '') {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }
};
