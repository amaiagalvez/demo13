<?php

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
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_name_unique');
        });

        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX customers_active_name_unique ON customers (name) WHERE deleted_at IS NULL',
            );

            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
            $table->unique('active_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX customers_active_name_unique');
        } else {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropUnique('customers_active_name_unique');
                $table->dropColumn('active_name');
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique('name');
        });
    }
};
