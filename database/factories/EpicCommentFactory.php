<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpicComment>
 */
class EpicCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'epic_id' => Epic::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    public function withoutAuthor(): static
    {
        return $this->state([
            'user_id' => null,
        ]);
    }
}
