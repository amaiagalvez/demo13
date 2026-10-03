<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Model;

trait HasTrashedState
{
    /** Indicate that the model should be soft deleted after creation. */
    public function trashed(): static
    {
        return $this->afterCreating(static function (Model $model): void {
            $model->delete();
        });
    }
}
