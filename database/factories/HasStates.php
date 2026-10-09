<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

trait HasStates
{
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function trashed(): static
    {
        return $this->afterCreating(static function (Model $model): void {
            $model->delete();
        });
    }

    /**
     * Get an existing random record or create a new one.
     *
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @return TModel
     */
    public static function getRecord(string $modelClass): Model
    {
        return $modelClass::inRandomOrder()->first() ?? $modelClass::factory()->create();
    }

    /**
     * Generate a random date range.
     *
     * @param int $startYearsBack Years back for start date (e.g., -1)
     * @param int $endYearsForward Years forward from start for end date (e.g., +1)
     * @param bool $allowNull Whether to allow null dates
     * @return array{start_date: string|null, end_date: string|null}
     */
    protected function getDateRange(int $startYearsBack = -1, int $endYearsForward = 1, bool $allowNull = false): array
    {
        if ($allowNull && fake()->boolean(20)) {
            return ['start_date' => null, 'end_date' => null];
        }

        $startDate = fake()->dateTimeBetween("{$startYearsBack} year", 'now')->format('Y-m-d');
        $endDate = fake()->dateTimeBetween($startDate, "+{$endYearsForward} year")->format('Y-m-d');

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }
}
