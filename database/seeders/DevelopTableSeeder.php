<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Database\Seeder;
use Customers13\Models\Customer;
use Projects13\Models\EpicComment;
use Projects13\Database\Factories\EpicFactory;
use Projects13\Database\Factories\ProjectFactory;
use Customers13\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DevelopTableSeeder extends Seeder
{
    private const STATE_WEIGHTS = ['active' => 12, 'archived' => 5, 'trashed' => 3];

    private const PROJECTS_PER_CUSTOMER = 3;

    private const EPICS_PER_PROJECT = 4;

    private const COMMENTS_PER_EPIC = 3;

    private const AUTHORS = 5;

    /**
     * The weights of STATE_WEIGHTS expanded into a single list, so the states can be handed out
     * in a fixed rotation and every scale keeps the same 60/25/15 split.
     *
     * @var list<string>
     */
    private readonly array $states;

    /** @var array<string, int> How many states each level of the tree has already been given. */
    private array $cursors = ['customers' => 0, 'projects' => 0, 'epics' => 0];

    public function __construct(private readonly int $customers = 100)
    {
        $states = [];

        foreach (self::STATE_WEIGHTS as $state => $weight) {
            array_push($states, ...array_fill(0, $weight, $state));
        }

        $this->states = $states;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);

        $authors = User::factory()->count(self::AUTHORS)->create();
        $projects = $epics = $comments = 0;

        for ($number = 1; $number <= $this->customers; $number++) {
            $customer = $this->withState('customers', Customer::factory())->createOne([
                'name' => sprintf('Perf Customer %05d', $number),
            ]);

            for ($index = 1; $index <= self::PROJECTS_PER_CUSTOMER; $index++) {
                $project = $this->withState('projects', Project::factory())->createOne([
                    'name' => sprintf('Perf Project %05d', $projects + $index),
                    'customer_id' => $customer->id,
                ]);
                $projects++;

                for ($epicIndex = 1; $epicIndex <= self::EPICS_PER_PROJECT; $epicIndex++) {
                    /** @var Project $project */
                    $epic = $this->createEpicWithinProject($project, $epics + $epicIndex);
                    $epics++;

                    for ($commentIndex = 1; $commentIndex <= self::COMMENTS_PER_EPIC; $commentIndex++) {
                        EpicComment::factory()->createOne([
                            'epic_id' => $epic->id,
                            'user_id' => $authors->random()->id,
                            'body' => sprintf('Perf comment %05d.', $comments + $commentIndex),
                        ]);
                        $comments++;
                    }
                }
            }
        }
    }

    /**
     * Create an epic with dates constrained to the project's date range.
     */
    private function createEpicWithinProject(Project $project, int $epicNumber): Epic
    {
        $projectStart = CarbonImmutable::parse($project->start_date);
        $projectEnd = $project->end_date ? CarbonImmutable::parse($project->end_date) : CarbonImmutable::now()->addYear();

        // Epic can have no dates
        if (fake()->boolean(20)) {
            /** @var Epic $epic */
            $epic = $this->withState('epics', Epic::factory())->createOne([
                'name' => sprintf('Perf Epic %05d', $epicNumber),
                'project_id' => $project->id,
                'start_date' => null,
                'end_date' => null,
            ]);

            return $epic;
        }

        // Ensure there's at least 1 day between project start and project end for epic dates
        $earliestStart = $projectStart;
        $latestStart = $projectEnd->subDay();

        // If project is only 1 day or less, use project start for both
        if ($latestStart->lessThanOrEqualTo($earliestStart)) {
            $startDate = $projectStart->format('Y-m-d');
            $endDate = $projectEnd->format('Y-m-d');
        } else {
            $startDate = fake()->dateTimeBetween($earliestStart, $latestStart)->format('Y-m-d');
            $endDate = fake()->dateTimeBetween($startDate.' +1 day', $projectEnd)->format('Y-m-d');
        }

        /** @var Epic $epic */
        $epic = $this->withState('epics', Epic::factory())->createOne([
            'name' => sprintf('Perf Epic %05d', $epicNumber),
            'project_id' => $project->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $epic;
    }

    /**
     * Move the factory of a level to the next state of the rotation. Active is the default
     * definition, so only the other two states need one of the states the factories already offer.
     *
     * @param  CustomerFactory|ProjectFactory|EpicFactory  $factory
     * @return CustomerFactory|ProjectFactory|EpicFactory
     */
    private function withState(string $level, Factory $factory): Factory
    {
        $state = $this->states[$this->cursors[$level]++ % count($this->states)];

        return match ($state) {
            'archived' => $factory->archived(),
            'trashed' => $factory->trashed(),
            default => $factory,
        };
    }
}
