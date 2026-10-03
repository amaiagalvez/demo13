<?php

use Illuminate\Support\Facades\DB;

function isSqliteOrPgsql(): bool
{
    return in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true);
}
