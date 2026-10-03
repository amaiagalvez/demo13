<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Transformers\EpicListTransformer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $epicProject = $epic->project;

        if ($epicProject === null) {
            self::fail('The created epic must belong to a project.');
        }

        $this->assertTrue($epicProject->is($project));

        $projectCustomer = $project->customer;

        if (! $projectCustomer instanceof Customer) {
            self::fail('The selected project must belong to a customer.');
        }

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('epic-create-button')
            ->assertSee('aria-labelledby="epic-form-heading"', false)
            ->assertSee('id="epic-form-heading"', false)
            ->assertSee('aria-labelledby="epic-confirm-heading"', false)
            ->assertSee('id="epic-confirm-heading"', false)
            ->assertSee('epic-edit-'.$epic->id)
            ->assertSee('Checkout flow')
            ->assertSee('title="Checkout flow"', false)
            ->assertSee($project->name)
            ->assertSee($projectCustomer->name);

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

    public function test_epic_list_transformer_uses_fallbacks_when_parent_relations_are_missing(): void
    {
        $epic = Epic::factory()->create();
        $epic->setRelation('project', null);

        $list = app(EpicListTransformer::class)->active(
            new LengthAwarePaginator([$epic], 1, 15),
            '',
        );

        $this->assertSame('—', $list['rows'][0]['project']);
        $this->assertSame('—', $list['rows'][0]['customer']);
        $this->assertSame('—', $list['rows'][0]['actions'][0]['epic']['project_label']);
    }

    public function test_empty_epic_list_uses_the_shared_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('epics.index'))
            ->assertOk()
            ->assertSee('data-test="list-empty-state"', false)
            ->assertSee(__('No epics yet.'));
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

    public function test_store_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $name = 'Concurrent epic';
        $this->insertEpicAfterNameUniquenessCheck($project, $name);

        $this->from(route('epics.index'))
            ->post(route('epics.store'), [
                'name' => $name,
                'project_id' => $project->id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseCount('epics', 1);
    }

    public function test_update_converts_a_concurrent_duplicate_insert_to_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create(['name' => 'Original epic']);
        $name = 'Concurrent epic update';
        $epicProject = $epic->project;

        if (! $epicProject instanceof Project) {
            self::fail('The epic must belong to a project.');
        }

        $this->insertEpicAfterNameUniquenessCheck($epicProject, $name);

        $this->from(route('epics.index'))
            ->put(route('epics.update', $epic), [
                'name' => $name,
                'project_id' => $epic->project_id,
            ])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasErrors([
                'name' => __('validation.unique', ['attribute' => __('Name')]),
            ]);

        $this->assertDatabaseHas('epics', ['id' => $epic->id, 'name' => 'Original epic']);
        $this->assertDatabaseHas('epics', ['name' => $name, 'deleted_at' => null]);
    }

    private function insertEpicAfterNameUniquenessCheck(Project $project, string $name): void
    {
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($project, $name, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'epics')
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Epic::factory()->for($project)->create(['name' => $name]);
        });
    }
}
