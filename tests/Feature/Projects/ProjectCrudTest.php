<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Database\QueryException;
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
        $this->assertTrue($project->customer->is($customer));

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-create-button')
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
        $this->assertTrue($projects->every(fn (Project $project): bool => $project->customer->is($customer)));
    }

    public function test_database_rejects_duplicate_project_names(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create(['name' => 'Unique project']);

        $this->expectException(QueryException::class);

        Project::factory()->for($customer)->create(['name' => 'Unique project']);
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

    public function test_guests_are_redirected_to_login_from_the_projects_list(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }
}
