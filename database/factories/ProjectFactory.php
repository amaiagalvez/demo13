<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use HasTrashedState;
    use HasInactiveState;

    /**
     * Define the model's default state. The end date may equal the start date per ProjectRequest.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        return [
            'name' => fake()->unique()->sentence(3),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+1 year')->format('Y-m-d'),
            'customer_id' => Customer::factory(),
        ];
    }

}
