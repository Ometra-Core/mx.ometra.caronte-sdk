<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('caronte.table_prefix') . 'Users';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'id_tenant')) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'tenant_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('tenant_id', 64)->nullable()->index();
            });
        }

        DB::table($tableName)
            ->where(function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '');
            })
            ->whereNotNull('id_tenant')
            ->where('id_tenant', '!=', '')
            ->update(['tenant_id' => DB::raw('id_tenant')]);

        $missingTenantCount = DB::table($tableName)
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', '')
            ->count();

        if ($missingTenantCount > 0) {
            throw new \RuntimeException(sprintf(
                'Cannot remove legacy column %s.id_tenant: %d rows do not have a tenant_id.',
                $tableName,
                $missingTenantCount
            ));
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('id_tenant');
        });
    }

    public function down(): void
    {
        $tableName = config('caronte.table_prefix') . 'Users';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'id_tenant')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('id_tenant', 64)->nullable()->index();
        });

        DB::table($tableName)->update(['id_tenant' => DB::raw('tenant_id')]);
    }
};
