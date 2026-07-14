<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rename('tenant_id', 'id_tenant');
    }

    public function down(): void
    {
        $this->rename('id_tenant', 'tenant_id');
    }

    private function rename(string $from, string $to): void
    {
        $prefix = (string) config('caronte.table_prefix');

        foreach ([$prefix . 'Users', $prefix . 'UsersMetadata'] as $tableName) {
            if (
                Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, $from)
                && ! Schema::hasColumn($tableName, $to)
            ) {
                Schema::table($tableName, function (Blueprint $table) use ($from, $to): void {
                    $table->renameColumn($from, $to);
                });
            }
        }
    }
};
