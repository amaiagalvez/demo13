<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Model;

trait HasStates
{
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }

    public function trashed(): static
    {
        return $this->afterCreating(static function (Model $model): void {
            $model->delete();
        });
    }
}
