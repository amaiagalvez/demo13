<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameDuplicateCustomers();

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    private function renameDuplicateCustomers(): void
    {
        $duplicateNames = DB::table('customers')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $duplicateIds = DB::table('customers')
                ->where('name', $name)
                ->orderBy('id')
                ->skip(1)
                ->pluck('id');

            foreach ($duplicateIds as $id) {
                DB::table('customers')
                    ->where('id', $id)
                    ->update(['name' => $this->uniqueName((string) $name, (int) $id)]);
            }
        }
    }

    private function uniqueName(string $name, int $id): string
    {
        $attempt = 1;

        do {
            $suffix = $attempt === 1 ? " ({$id})" : " ({$id}-{$attempt})";
            $candidate = Str::limit($name, 255 - Str::length($suffix), '') . $suffix;
            $attempt++;
        } while (DB::table('customers')->where('name', $candidate)->exists());

        return $candidate;
    }
};
