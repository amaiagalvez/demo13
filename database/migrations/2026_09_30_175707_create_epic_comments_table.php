<?php

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
        Schema::create('epic_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epic_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->longText('body');

            addCommonColumns($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('epic_comments');
    }
};
