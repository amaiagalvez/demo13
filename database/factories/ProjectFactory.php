<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Customer;
use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'name' => fake()->unique()->sentence(3),
            ...$this->getDateRange(-1, 1),
            'customer_id' => static::getRecord(Customer::class),
        ];
    }
}
