<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_create_update_and_delete_epics(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();

        $this->post(route('epics.store'), [
            'name' => '  Checkout flow  ',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => $project->id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('Epic created successfully.'));

        $epic = Epic::query()->firstOrFail();
        $this->assertSame('Checkout flow', $epic->name);
        $this->assertTrue($epic->project->is($project));

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('epic-create-button')
            ->assertSee('epic-edit-'.$epic->id)
            ->assertSee('Checkout flow')
            ->assertSee($project->name)
            ->assertSee($project->customer->name);

        $this->put(route('epics.update', $epic), [
            'name' => 'Payment flow',
            'start_date' => '',
            'end_date' => '',
            'project_id' => $otherProject->id,
        ])->assertRedirect(route('epics.index'));

        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'name' => 'Payment flow',
            'start_date' => null,
            'end_date' => null,
            'project_id' => $otherProject->id,
        ]);

        $this->delete(route('epics.destroy', $epic))
            ->assertRedirect(route('epics.index'));

        $this->assertSoftDeleted($epic);
    }

    public function test_a_project_can_have_multiple_epics(): void
    {
        $project = Project::factory()->create();

        Epic::factory()->count(2)->for($project)->create();

        $this->assertCount(2, $project->epics);
    }

    public function test_database_rejects_duplicate_epic_names_in_the_same_project(): void
    {
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Shared epic']);

        $this->expectException(QueryException::class);

        Epic::factory()->for($project)->create(['name' => 'Shared epic']);
    }

    public function test_database_allows_the_same_epic_name_in_different_projects_and_after_soft_delete(): void
    {
        $project = Project::factory()->create();
        $deletedEpic = Epic::factory()->for($project)->trashed()->create(['name' => 'Shared epic']);
        $activeEpic = Epic::factory()->for($project)->create(['name' => 'Shared epic']);
        $otherProjectEpic = Epic::factory()->create(['name' => 'Shared epic']);

        $this->assertSoftDeleted($deletedEpic);
        $this->assertModelExists($activeEpic);
        $this->assertModelExists($otherProjectEpic);
    }

    public function test_epics_are_ordered_by_start_date_end_date_project_and_customer(): void
    {
        $this->actingAs(User::factory()->create());
        $alphaCustomer = Customer::factory()->create(['name' => 'Alpha customer']);
        $zuluCustomer = Customer::factory()->create(['name' => 'Zulu customer']);
        $alphaProject = Project::factory()->for($zuluCustomer)->create(['name' => 'Alpha project']);
        $zuluProjectAlphaCustomer = Project::factory()->for($alphaCustomer)->create(['name' => 'Zulu project A']);
        $zuluProjectZuluCustomer = Project::factory()->for($zuluCustomer)->create(['name' => 'Zulu project Z']);

        $dates = ['start_date' => '2026-10-01', 'end_date' => '2026-10-03'];
        Epic::factory()->withoutDates()->for($alphaProject)->create(['name' => 'Epic without dates']);
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic later start', 'start_date' => '2026-10-02', 'end_date' => '2026-10-03']);
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic later end', 'start_date' => '2026-10-01', 'end_date' => '2026-10-04']);
        Epic::factory()->for($zuluProjectZuluCustomer)->create(['name' => 'Epic zulu customer', ...$dates]);
        Epic::factory()->for($zuluProjectAlphaCustomer)->create(['name' => 'Epic alpha customer', ...$dates]);
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic alpha project', ...$dates]);

        $this->get(route('epics.index'))
            ->assertSeeInOrder([
                'Epic alpha project',
                'Epic alpha customer',
                'Epic zulu customer',
                'Epic later end',
                'Epic later start',
            ]);

        $this->get(route('epics.index', ['page' => 2]))
            ->assertSee('Epic without dates');
    }

    public function test_epics_can_be_searched_by_project_and_customer_name(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Northwind Studio']);
        Epic::factory()->for(Project::factory()->for($customer)->state(['name' => 'Website']))->create(['name' => 'Matching epic']);
        Epic::factory()->create(['name' => 'Other epic']);

        $this->get(route('epics.index', ['search' => 'Northwind']))
            ->assertOk()
            ->assertSee('Matching epic')
            ->assertDontSee('Other epic');

        $this->get(route('epics.index', ['search' => 'Website']))
            ->assertOk()
            ->assertSee('Matching epic')
            ->assertDontSee('Other epic');
    }

    public function test_epic_list_shows_the_number_of_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        EpicComment::factory()->count(3)->for($epic)->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSeeInOrder(['epic-comments-count-'.$epic->id, '3']);
    }

    public function test_guests_are_redirected_to_login_from_the_epics_list(): void
    {
        $this->get(route('epics.index'))->assertRedirect(route('login'));
    }
}
