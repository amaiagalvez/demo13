<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('active')->default(true);
            $table->index(['deleted_at', 'id']);
        });

        if (isSqliteOrPgsql()) {
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

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
