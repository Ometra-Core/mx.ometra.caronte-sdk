<?php

/**
 * @author Erick Escobar
 * @license MIT
 * @version 1.4.0
 *
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expected composite primary key for the users metadata table.
     *
     * @var list<string>
     */
    private array $expectedPrimaryColumns = ['uri_user', 'id_tenant', 'scope', 'key'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('caronte.table_prefix') . 'UsersMetadata';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->string('uri_user', 40);
                $table->string('id_tenant', 64);
                $table->string('scope', 128);
                $table->string('key', 45);
                $table->string('value', 45);
                $table->primary(['uri_user', 'id_tenant', 'scope', 'key']);
                $table->engine = 'InnoDB';
            });
        } else {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Ensure columns exist and have correct types/lengths
                if (!Schema::hasColumn($tableName, 'uri_user')) {
                    $table->string('uri_user', 40);
                } else {
                    $table->string('uri_user', 40)->change();
                }
                if (!Schema::hasColumn($tableName, 'id_tenant')) {
                    $table->string('id_tenant', 64)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'scope')) {
                    $table->string('scope', 128);
                } else {
                    $table->string('scope', 128)->change();
                }
                if (!Schema::hasColumn($tableName, 'key')) {
                    $table->string('key', 45);
                } else {
                    $table->string('key', 45)->change();
                }
                if (!Schema::hasColumn($tableName, 'value')) {
                    $table->string('value', 45);
                } else {
                    $table->string('value', 45)->change();
                }
                $table->engine = 'InnoDB';
            });

            if (Schema::hasColumn($tableName, 'id_tenant')) {
                DB::table($tableName)
                    ->whereNull('id_tenant')
                    ->update(['id_tenant' => (string) config('caronte.tenancy.id_tenant', 'default')]);
            }
            
            if (Schema::hasColumn($tableName, 'id_tenant') && Schema::hasColumn($tableName, 'tenant_id')) {
                DB::table($tableName)
                    ->whereNull('id_tenant')
                    ->update(['id_tenant' => DB::raw('tenant_id')]);
            }



            $currentPrimaryColumns = $this->getPrimaryColumns($tableName);

            if ($currentPrimaryColumns !== $this->expectedPrimaryColumns) {
                Schema::table($tableName, function (Blueprint $table) use ($currentPrimaryColumns) {
                    if ($currentPrimaryColumns !== []) {
                        $table->dropPrimary();
                    }

                    $table->primary($this->expectedPrimaryColumns);
                });
            }
        }
    }

    /**
     * Retrieve primary key columns for the table without requiring Doctrine DBAL.
     *
     * @return list<string>
     */
    private function getPrimaryColumns(string $tableName): array
    {
        $schemaBuilder = Schema::getConnection()->getSchemaBuilder();

        if (method_exists($schemaBuilder, 'getIndexes')) {
            /** @var array<int, array{name?: string, columns?: array<int, string>}> $indexes */
            $indexes = $schemaBuilder->getIndexes($tableName);

            foreach ($indexes as $index) {
                if (strtolower((string) ($index['name'] ?? '')) === 'primary') {
                    return array_values($index['columns'] ?? []);
                }
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->expectedPrimaryColumns;
        }

        $escapedTable = str_replace('`', '``', $tableName);
        $rows = Schema::getConnection()->select(
            "SHOW INDEX FROM `{$escapedTable}` WHERE Key_name = ?",
            ['PRIMARY']
        );

        if ($rows === []) {
            return [];
        }

        usort(
            $rows,
            static fn($a, $b): int => (int) ($a->Seq_in_index ?? $a->seq_in_index ?? 0)
                <=> (int) ($b->Seq_in_index ?? $b->seq_in_index ?? 0)
        );

        return array_values(array_map(
            static fn($row): string => (string) ($row->Column_name ?? $row->column_name ?? ''),
            $rows
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('caronte.table_prefix') . 'UsersMetadata';
        Schema::dropIfExists($tableName);
    }
};
