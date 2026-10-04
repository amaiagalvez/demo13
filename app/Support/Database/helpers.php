<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

function isSqliteOrPgsql(): bool
{
    return in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true);
}

/**
 * Add the columns shared by the application's soft-deleting tables.
 */
function addCommonColumns(Blueprint $table): void
{
    $table->longText('notes')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['deleted_at', 'id']);
}

/**
 * Make a table's name unique among the rows that are not soft-deleted.
 *
 * SQLite and PostgreSQL get a partial index. MySQL and MariaDB have no partial indexes, so a
 * generated column carries the name only while the row is active: a unique index ignores the
 * several NULLs it leaves behind on the trashed rows that repeat an active name.
 *
 * @param  list<string>  $scope  Extra columns the name is unique within, besides [name] itself
 */
function addUniqueActiveNameIndex(string $table, array $scope = []): void
{
    $index = implode('_', [$table, ...$scope, 'active_name', 'unique']);

    if (isSqliteOrPgsql()) {
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE deleted_at IS NULL',
            $index,
            $table,
            implode(', ', [...$scope, 'name']),
        ));

        return;
    }

    Schema::table($table, function (Blueprint $blueprint) use ($index, $scope): void {
        $blueprint->string('active_name')->nullable()->storedAs('IF(deleted_at IS NULL, name, NULL)');
        $blueprint->unique([...$scope, 'active_name'], $index);
    });
}
