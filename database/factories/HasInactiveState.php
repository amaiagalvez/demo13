<?php

namespace Database\Factories;

/**
 * Activation state shared by every model whose table carries the `active` flag.
 */
trait HasInactiveState
{
    /** Indicate that the model is inactive. */
    public function inactive(): static
    {
        return $this->state([
            'active' => false,
        ]);
    }
}
