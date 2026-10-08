<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Epic>
 */
class EpicFactory extends Factory
{
    use HasStates;

    /**
     * Define the model's default state. The end date must be later than the start date per EpicRequest.
     * Dates must also fall within the project's date range.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::inRandomOrder()->first() ?? Project::factory()->create();

        // Epic dates must be within project dates
        $projectStart = CarbonImmutable::parse($project->start_date);
        $projectEnd = $project->end_date ? CarbonImmutable::parse($project->end_date) : CarbonImmutable::now()->addYear();

        // Ensure epic start is after project start and before project end
        $earliestStart = $projectStart;
        $latestStart = $projectEnd->subDay(); // At least 1 day before project end for end_date to fit

        $startDate = fake()->dateTimeBetween($earliestStart, $latestStart)->format('Y-m-d');

        // Epic end must be after epic start and before project end
        $endDate = fake()->dateTimeBetween($startDate.' +1 day', $projectEnd)->format('Y-m-d');

        return [
            'name' => fake()->unique()->sentence(3),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'project_id' => $project->id,
        ];
    }

    /** Indicate that the epic has no start or end date. */
    public function withoutDates(): static
    {
        return $this->state([
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
