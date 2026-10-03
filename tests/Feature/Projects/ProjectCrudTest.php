<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use App\Queries\ListQueryBase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RacesNameInsert;
use Illuminate\Database\QueryException;
use App\Transformers\ProjectListTransformer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_customers_is_true_when_an_active_customer_exists(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertViewHas('hasCustomers', true);
    }

    public function test_has_customers_is_false_when_only_inactive_customers_exist(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->inactive()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertViewHas('hasCustomers', false);
    }

    public function test_has_customers_is_false_when_only_deleted_customers_exist(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->trashed()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertViewHas('hasCustomers', false);
    }

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
        ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Record created successfully.'));

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
            ->assertSee('title="Website renewal"', false)
            ->assertSee($customer->name);

        $this->put(route('projects.update', $project), [
            'name' => 'Updated website',
            'start_date' => '2026-10-15',
            'end_date' => '2027-01-15',
            'customer_id' => $otherCustomer->id,
        ])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Record updated successfully.'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated website',
            'start_date' => '2026-10-15',
            'end_date' => '2027-01-15',
            'customer_id' => $otherCustomer->id,
        ]);

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Record moved to trash.'));

        $this->assertSoftDeleted($project);
    }

    public function test_project_store_persists_only_validated_attributes(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->post(route('projects.store'), [
            'name' => 'Validated project',
            'start_date' => '2026-10-01',
            'customer_id' => $customer->id,
            'id' => 999999,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => 'Validated project',
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseMissing('projects', [
            'name' => 'Validated project',
            'id' => 999999,
        ]);
        $this->assertDatabaseMissing('projects', [
            'name' => 'Validated project',
            'created_at' => '2000-01-01 00:00:00',
        ]);
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
        RacesNameInsert::afterUniquenessSelect('projects', $name, static function () use ($name): void {
            Project::factory()->create(['name' => $name]);
        });

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
        RacesNameInsert::afterUniquenessSelect('projects', $name, static function () use ($name): void {
            Project::factory()->create(['name' => $name]);
        });

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

    public function test_projects_can_be_searched_by_their_name(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->create(['name' => 'Searchable project']);
        Project::factory()->for($customer)->create(['name' => 'Unrelated project']);

        $this->get(route('projects.index', ['search' => 'Searchable project']))
            ->assertOk()
            ->assertSee('Searchable project')
            ->assertDontSee('Unrelated project');
    }

    public function test_project_name_with_non_latin_characters_can_be_stored_and_searched(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $name = 'Ñandú プロジェクト';

        $this->post(route('projects.store'), [
            'name' => $name,
            'start_date' => '2026-10-01',
            'customer_id' => $customer->id,
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', ['name' => $name, 'customer_id' => $customer->id]);

        $this->get(route('projects.index', ['search' => $name]))
            ->assertOk()
            ->assertSee($name);
    }

    public function test_project_search_is_kept_in_pagination_links(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        foreach (range(1, 6) as $index) {
            Project::factory()->for($customer)->create(['name' => "Repeat project {$index}"]);
        }

        $this->get(route('projects.index', ['search' => 'Repeat']))
            ->assertOk()
            ->assertSee('search=Repeat', false);
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

    public function test_project_list_shows_the_number_of_active_epics_and_their_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();
        Epic::factory()->for($project)->create();
        $commentedEpic = Epic::factory()->for($project)->create();
        EpicComment::factory()->count(3)->for($commentedEpic)->create();
        EpicComment::factory()->count(4)->for(Epic::factory()->for($project)->inactive())->create();
        EpicComment::factory()->count(5)->for(Epic::factory()->for($project)->trashed())->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSeeInOrder(['project-epics-count-'.$project->id, '2'])
            ->assertSeeInOrder(['project-comments-count-'.$project->id, '3']);
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

    public function test_project_list_shows_pagination_when_more_than_one_page_exists(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        for ($index = 1; $index <= ListQueryBase::PER_PAGE + 1; $index++) {
            Project::factory()->for($customer)->create();
        }

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-pagination', false);
    }

    public function test_project_list_header_offers_the_state_tabs_with_the_record_count_of_each_one(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        Project::factory()->count(2)->for($customer)->create();
        Project::factory()->for($customer)->inactive()->create();
        Project::factory()->count(3)->for($customer)->trashed()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-test="project-breadcrumbs"', false)
            ->assertSeeInOrder([
                'href="'.route('dashboard').'"',
                __('Dashboard'),
                __('Projects'),
            ], false)
            ->assertSee('data-test="project-heading"', false)
            ->assertSeeInOrder([
                'data-test="project-active-link"',
                'aria-current="page"',
                'data-test="project-inactive-link"',
                'data-test="project-trash-link"',
            ], false)
            ->assertSeeInOrder(['data-test="project-active-link"', '>2</span>'], false)
            ->assertSeeInOrder(['data-test="project-inactive-link"', '>1</span>'], false)
            ->assertSeeInOrder(['data-test="project-trash-link"', '>3</span>'], false);

        $this->get(route('projects.inactive.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Projects'), __('Inactive')], false);

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Projects'), __('Trash')], false);
    }

    public function test_project_row_edits_from_the_name_and_links_the_epic_count_to_its_epics(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create(['name' => 'Website redesign']);
        Epic::factory()->for($project)->create();
        $lonelyProject = Project::factory()->for($customer)->create(['name' => 'Lonely project']);

        $response = $this->get(route('projects.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="project-name-'.$project->id.'"',
                'editProject(JSON.parse(',
            ], false)
            ->assertSee('href="'.route('epics.index', ['search' => 'Website redesign']).'"', false)
            ->assertSee(__('View :count epics', ['count' => 1]))
            ->assertDontSee('href="'.route('epics.index', ['search' => 'Lonely project']).'"', false)
            ->assertSee('data-test="project-epics-count-'.$lonelyProject->id.'"></span>', false)
            ->assertSee('data-test="project-comments-count-'.$lonelyProject->id.'"></span>', false)
            ->assertSee(__('Cannot be deleted while it has related records.'))
            ->assertDontSee('data-test="project-delete-'.$project->id.'"', false)
            ->assertSee('data-test="project-deactivate-'.$project->id.'"', false)
            ->assertSee('data-test="project-delete-'.$lonelyProject->id.'"', false);
    }

    public function test_project_with_epics_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->for($project)->trashed()->create();

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));

        $this->assertNotSoftDeleted($project);
    }

    public function test_project_is_not_deleted_when_an_epic_appears_during_the_delete_transaction(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($project, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'projects')
                || ! str_contains(strtolower($query->sql), 'for update')
                || ! in_array($project->id, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Epic::factory()->for($project)->create(['name' => 'Concurrent epic']);
        });

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));

        $this->assertTrue($competitorCreated);
        $this->assertNotSoftDeleted($project);
        $this->assertDatabaseHas('epics', [
            'project_id' => $project->id,
            'deleted_at' => null,
        ]);
    }

    public function test_project_model_cannot_be_deleted_while_it_has_epics(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->assertFalse($project->delete());

        $this->assertNotSoftDeleted($project);
        $this->assertModelExists($epic);
    }

    public function test_project_list_transformer_uses_a_fallback_when_customer_is_missing(): void
    {
        $project = Project::factory()->create();
        $project->setRelation('customer', null);

        $list = app(ProjectListTransformer::class)->active(
            new LengthAwarePaginator([$project], 1, 15),
            '',
        );

        $this->assertSame('—', data_get($list, 'rows.0.customer'));
        $this->assertSame('—', data_get($list, 'rows.0.actions.0.project.customer_name'));
    }

    public function test_empty_project_list_uses_the_shared_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-test="list-empty-state"', false)
            ->assertSee(__('No projects yet.'));
    }

    public function test_guests_are_redirected_to_login_from_the_projects_list(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_update_or_delete_projects(): void
    {
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create(['name' => 'Existing project']);

        $this->post(route('projects.store'), [
            'name' => 'Guest project',
            'start_date' => '2045-07-09',
            'customer_id' => $customer->id,
        ])->assertRedirect(route('login'));

        $this->put(route('projects.update', $project), [
            'name' => 'Updated project',
            'start_date' => '2045-07-09',
            'customer_id' => $customer->id,
        ])->assertRedirect(route('login'));

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Existing project']);
        $this->assertNotSoftDeleted($project);
    }
}
