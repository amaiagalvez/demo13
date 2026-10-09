<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\User;
use App\Models\EpicComment;
use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpicComment>
 */
class EpicCommentFactory extends Factory
{
    use HasStates;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->paragraph(),
            'user_id' => static::getRecord(User::class),
            'epic_id' => static::getRecord(Epic::class),
        ];
    }
}
