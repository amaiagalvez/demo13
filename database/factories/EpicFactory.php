<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Epic>
 */
class EpicFactory extends Factory
{
    use HasTrashedState;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        return [
            'name' => fake()->unique()->sentence(3),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate . ' +1 day', '+1 year')->format('Y-m-d'),
            'project_id' => Project::factory(),
        ];
    }

    public function withoutDates(): static
    {
        return $this->state([
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'active' => false,
        ]);
    }
}
