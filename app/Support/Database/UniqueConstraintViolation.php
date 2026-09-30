<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;

final class UniqueConstraintViolation
{
    public static function causedBy(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? $exception->getCode()) === '23000'
            && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);
    }
}
