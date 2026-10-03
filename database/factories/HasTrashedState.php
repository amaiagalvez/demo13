<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Model;

trait HasTrashedState
{
    public function trashed(): static
    {
        return $this->afterCreating(static function (Model $model): void {
            $model->delete();
        });
    }
}
