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
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('caronte.table_prefix') . 'Users';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->string('uri_user', 40);
                $table->string('id_tenant', 64)->index();
                $table->string('name', 150);
                $table->string('email', 150);
                $table->primary(['uri_user', 'id_tenant']);
                $table->engine = 'InnoDB';
            });
        } else {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'uri_user')) {
                    $table->string('uri_user', 40);
                }
                if (!Schema::hasColumn($tableName, 'id_tenant')) {
                    $table->string('id_tenant', 64)->nullable()->index();
                }
                if (!Schema::hasColumn($tableName, 'name')) {
                    $table->string('name', 150);
                }
                if (!Schema::hasColumn($tableName, 'email')) {
                    $table->string('email', 150);
                }
                $table->engine = 'InnoDB';
            });

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('caronte.table_prefix') . 'Users';
        Schema::dropIfExists($tableName);
    }
};
