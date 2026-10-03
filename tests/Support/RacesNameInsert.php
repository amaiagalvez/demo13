<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;

final class RacesNameInsert
{
    public static function afterUniquenessSelect(string $table, string $name, Closure $factory): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use (
            $table,
            $name,
            $factory,
            &$competitorCreated,
        ): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), strtolower($table))
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            $factory();
        });
    }
}
