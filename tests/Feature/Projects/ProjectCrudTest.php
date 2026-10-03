<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_create_update_and_delete_projects(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->post(route('projects.store'), [
            'name' => '  Website renewal  ',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'customer_id' => $customer->id,
        ])->assertRedirect(route('projects.index'));

        $project = Project::query()->firstOrFail();
        $this->assertSame('Website renewal', $project->name);

        $projectCustomer = $project->customer;

        if (! $projectCustomer instanceof Customer) {
            self::fail('The created project must belong to a customer.');
        }

        $this->assertTrue($projectCustomer->is($customer));

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-create-button')
            ->assertSee('aria-labelledby="project-form-heading"', false)
            ->assertSee('id="project-form-heading"', false)
            ->assertSee('aria-labelledby="project-confirm-heading"', false)
            ->assertSee('id="project-confirm-heading"', false)
            ->assertSee('project-edit-'.$project->id)
            ->assertSee('Website renewal')
            ->assertSee($customer->name);

        $this->put(route('projects.update', $project), [
            'name' => 'Updated website',
            'start_date' => '2026-10-15',
            'end_date' => '2027-01-15',
            'customer_id' => $otherCustomer->id,
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated website',
            'start_date' => '2026-10-15',
            'end_date' => '2027-01-15',
            'customer_id' => $otherCustomer->id,
        ]);

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
    }

    public function test_a_customer_can_have_multiple_projects(): void
    {
        $customer = Customer::factory()->create();

        $projects = Project::factory()->count(2)->for($customer)->create();

        $this->assertCount(2, $customer->projects);
        $this->assertTrue($projects->every(function (Project $project) use ($customer): bool {
            $projectCustomer = $project->customer;

            if ($projectCustomer === null) {
                self::fail('Every created project must belong to a customer.');
            }

            return $projectCustomer->is($customer);
        }));
    }

    public function test_database_rejects_duplicate_project_names(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create(['name' => 'Unique project']);

        $this->expectException(QueryException::class);

        Project::factory()->for($customer)->create(['name' => 'Unique project']);
    }

    public function test_database_allows_project_name_reuse_after_soft_delete(): void
    {
        $customer = Customer::factory()->create();
        $deletedProject = Project::factory()->for($customer)->trashed()->create([
            'name' => 'Reusable project name',
        ]);

        $activeProject = Project::factory()->for($customer)->create([
            'name' => 'Reusable project name',
        ]);

        $this->assertSoftDeleted($deletedProject);
        $this->assertModelExists($activeProject);
    }

    public function test_store_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $name = 'Concurrent project';
        $this->insertProjectAfterNameUniquenessCheck($name);

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => $name,
                'start_date' => '2026-10-01',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['name' => $name, 'deleted_at' => null]);
    }

    public function test_update_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Original project']);
        $name = 'Concurrent project update';
        $this->insertProjectAfterNameUniquenessCheck($name);

        $this->from(route('projects.index'))
            ->put(route('projects.update', $project), [
                'name' => $name,
                'start_date' => '2026-10-01',
                'customer_id' => $project->customer_id,
            ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Original project',
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => $name,
            'deleted_at' => null,
        ]);
    }

    public function test_projects_can_be_searched_by_customer_name(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingCustomer = Customer::factory()->create(['name' => 'Northwind Studio']);
        $otherCustomer = Customer::factory()->create(['name' => 'Contoso']);
        Project::factory()->for($matchingCustomer)->create(['name' => 'Website redesign']);
        Project::factory()->for($otherCustomer)->create(['name' => 'Mobile application']);

        $this->get(route('projects.index', ['search' => 'Northwind']))
            ->assertOk()
            ->assertSee('Website redesign')
            ->assertSee('Northwind Studio')
            ->assertDontSee('Mobile application');
    }

    public function test_projects_cannot_be_searched_by_their_unshown_creation_date(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create([
            'name' => 'Website redesign',
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-01',
            'created_at' => '2001-02-03 12:00:00',
        ]);

        $this->get(route('projects.index', ['search' => '2001-02-03']))
            ->assertOk()
            ->assertSee(__('No projects match your search.'))
            ->assertDontSee('Website redesign');
    }

    public function test_projects_are_ordered_by_start_date_end_date_and_customer_name(): void
    {
        $this->actingAs(User::factory()->create());
        $alphaCustomer = Customer::factory()->create(['name' => 'Alpha customer']);
        $zuluCustomer = Customer::factory()->create(['name' => 'Zulu customer']);

        Project::factory()->for($alphaCustomer)->create([
            'name' => 'Z project, alpha customer',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
        ]);
        Project::factory()->for($zuluCustomer)->create([
            'name' => 'A project, zulu customer',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
        ]);
        Project::factory()->for($alphaCustomer)->create([
            'name' => 'B project, later end',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-04',
        ]);
        Project::factory()->for($alphaCustomer)->create([
            'name' => 'C project, later start',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-02',
        ]);

        $this->get(route('projects.index'))
            ->assertSeeInOrder([
                'Z project, alpha customer',
                'A project, zulu customer',
                'B project, later end',
                'C project, later start',
            ]);
    }

    public function test_projects_with_matching_dates_and_customer_are_ordered_by_name(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Same customer']);

        Project::factory()->for($customer)->create([
            'name' => 'Zulu project',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);
        Project::factory()->for($customer)->create([
            'name' => 'Alpha project',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);

        $this->get(route('projects.index'))
            ->assertSeeInOrder([
                'Alpha project',
                'Zulu project',
            ]);
    }

    public function test_project_with_epics_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->for($project)->trashed()->create();

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Project cannot be deleted while it has epics.'));

        $this->assertNotSoftDeleted($project);
    }

    public function test_guests_are_redirected_to_login_from_the_projects_list(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    private function insertProjectAfterNameUniquenessCheck(string $name): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'projects')
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Project::factory()->create(['name' => $name]);
        });
    }
}
