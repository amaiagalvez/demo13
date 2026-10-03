<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Database\Seeder;
use Database\Factories\EpicFactory;
use Database\Factories\ProjectFactory;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Load fixture to measure the customer list at scale. It is deliberately kept out of
 * DatabaseSeeder: it writes tens of thousands of rows, so it is only executed on demand,
 * against a disposable database, with:
 *
 *     php artisan db:seed --class="Database\Seeders\CustomerPerformanceSeeder"
 *
 * The customer count is the only knob, and the ratios below stay constant at every scale:
 * 3 projects per customer, 4 epics per project and 3 comments per epic.
 */
class CustomerPerformanceSeeder extends Seeder
{
    /** Share of the records that land on each state, in twentieths. */
    private const STATE_WEIGHTS = ['active' => 12, 'inactive' => 5, 'trashed' => 3];

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

    public function __construct(private readonly int $customers = 300)
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
        $this->seed(UserSeeder::class);

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
                    $epic = $this->withState('epics', Epic::factory())->createOne([
                        'name' => sprintf('Perf Epic %05d', $epics + $epicIndex),
                        'project_id' => $project->id,
                    ]);
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
            'inactive' => $factory->inactive(),
            'trashed' => $factory->trashed(),
            default => $factory,
        };
    }
}
