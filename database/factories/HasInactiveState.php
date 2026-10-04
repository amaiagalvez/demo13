<?php

namespace Database\Factories;

trait HasInactiveState
{
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }
}
