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
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('projects')
            ->whereNull('end_date')
            ->update(['end_date' => DB::raw('start_date')]);

        Schema::table('projects', function (Blueprint $table): void {
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
