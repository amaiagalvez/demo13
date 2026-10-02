<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('active')->default(true);
        });

        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX projects_active_name_unique ON projects (name) WHERE deleted_at IS NULL',
            );

            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
            $table->unique('active_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
