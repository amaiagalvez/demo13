<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class UniqueConstraintViolation
{
    public static function causedBy(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? $exception->getCode()) === '23000'
            && in_array((int) ($errorInfo[1] ?? 0), [19, 1062], true);
    }

    /**
     * Report a unique index collision that passed validation (e.g. a concurrent write)
     * as a validation error on the given field; rethrow any other database error.
     *
     * @throws ValidationException
     * @throws QueryException
     */
    public static function rethrowAsValidationError(QueryException $exception, string $field = 'name'): never
    {
        if (! self::causedBy($exception)) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            $field => __('validation.unique', ['attribute' => __(ucfirst($field))]),
        ]);
    }
}
