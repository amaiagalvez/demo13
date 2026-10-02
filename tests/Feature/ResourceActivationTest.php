<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceActivationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function resources(): array
    {
        return [
            'customers' => [Customer::class, 'customers'],
            'projects' => [Project::class, 'projects'],
            'epics' => [Epic::class, 'epics'],
        ];
    }

    /**
     * @return array<string, array{class-string<Model>, string, string}>
     */
    public static function resourcesWithTestPrefix(): array
    {
        return [
            'customers' => [Customer::class, 'customers', 'customer'],
            'projects' => [Project::class, 'projects', 'project'],
            'epics' => [Epic::class, 'epics', 'epic'],
        ];
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_lists_partition_records_by_activation_and_trash(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $model::factory()->create(['name' => 'Active record']);
        $model::factory()->inactive()->create(['name' => 'Inactive record']);
        $model::factory()->trashed()->create(['name' => 'Trashed active record']);
        $model::factory()->inactive()->trashed()->create(['name' => 'Trashed inactive record']);

        $this->get(route($resource.'.index'))
            ->assertOk()
            ->assertSee('Active record')
            ->assertDontSee('Inactive record')
            ->assertDontSee('Trashed active record');

        $this->get(route($resource.'.inactive.index'))
            ->assertOk()
            ->assertSee('Inactive record')
            ->assertDontSee('Active record')
            ->assertDontSee('Trashed inactive record');

        $this->get(route($resource.'.trash.index'))
            ->assertOk()
            ->assertSee('Trashed active record')
            ->assertSee('Trashed inactive record')
            ->assertDontSee('>Active record<', false)
            ->assertDontSee('>Inactive record<', false);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resourcesWithTestPrefix')]
    public function test_inactive_list_appends_the_update_date_and_only_offers_reactivation(
        string $model,
        string $resource,
        string $prefix,
    ): void {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:30:00');
        $record = $model::factory()->inactive()->create(['name' => 'Dormant record']);

        $this->get(route($resource.'.inactive.index'))
            ->assertOk()
            ->assertSeeInOrder(['<thead', __('Name'), __('Updated at')], false)
            ->assertSeeInOrder(['Dormant record', '2026-10-02 12:30'])
            ->assertSee('data-test="'.$prefix.'-reactivate-'.$record->id.'"', false)
            ->assertDontSee('data-test="'.$prefix.'-edit-'.$record->id.'"', false)
            ->assertDontSee('data-test="'.$prefix.'-delete-'.$record->id.'"', false)
            ->assertDontSee('data-test="'.$prefix.'-deactivate-'.$record->id.'"', false);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_record_can_be_deactivated_and_reactivated(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-01 08:00:00');
        $record = $model::factory()->create();
        $this->travelTo('2026-10-02 10:00:00');

        $this->patch(route($resource.'.deactivate', $record))
            ->assertRedirect(route($resource.'.index'))
            ->assertSessionHas('status', __('Record deactivated successfully.'));

        $record->refresh();
        $this->assertFalse($record->active);
        $this->assertSame('2026-10-02 10:00:00', $record->updated_at->toDateTimeString());

        $this->patch(route($resource.'.inactive.reactivate', $record))
            ->assertRedirect(route($resource.'.inactive.index'))
            ->assertSessionHas('status', __('Record reactivated successfully.'));

        $this->assertTrue($record->refresh()->active);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_repeated_activation_actions_are_idempotent(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $inactive = $model::factory()->inactive()->create();
        $active = $model::factory()->create();

        $this->patch(route($resource.'.deactivate', $inactive))
            ->assertSessionHas('status', __('Record deactivated successfully.'));
        $this->patch(route($resource.'.inactive.reactivate', $active))
            ->assertSessionHas('status', __('Record reactivated successfully.'));

        $this->assertFalse($inactive->refresh()->active);
        $this->assertTrue($active->refresh()->active);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_trashed_records_cannot_change_activation(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $active = $model::factory()->trashed()->create();
        $inactive = $model::factory()->inactive()->trashed()->create();

        $this->patch(route($resource.'.deactivate', $active->id))->assertNotFound();
        $this->patch(route($resource.'.inactive.reactivate', $inactive->id))->assertNotFound();

        $this->assertTrue($active->refresh()->active);
        $this->assertFalse($inactive->refresh()->active);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_restoring_an_inactive_record_keeps_it_inactive(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $record = $model::factory()->inactive()->trashed()->create(['name' => 'Restored dormant record']);

        $this->patch(route($resource.'.trash.restore', $record->id))->assertRedirect();

        $record->refresh();
        $this->assertNotSoftDeleted($record);
        $this->assertFalse($record->active);
        $this->get(route($resource.'.inactive.index'))->assertSee('Restored dormant record');
        $this->get(route($resource.'.index'))->assertDontSee('Restored dormant record');
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_guests_cannot_access_activation_endpoints(string $model, string $resource): void
    {
        $record = $model::factory()->create();
        $inactive = $model::factory()->inactive()->create();

        $this->get(route($resource.'.inactive.index'))->assertRedirect(route('login'));
        $this->patch(route($resource.'.deactivate', $record))->assertRedirect(route('login'));
        $this->patch(route($resource.'.inactive.reactivate', $inactive))->assertRedirect(route('login'));

        $this->assertTrue($record->refresh()->active);
        $this->assertFalse($inactive->refresh()->active);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_unverified_users_cannot_change_activation(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->unverified()->create());
        $record = $model::factory()->create();

        $this->patch(route($resource.'.deactivate', $record))->assertRedirect(route('verification.notice'));

        $this->assertTrue($record->refresh()->active);
    }

    public function test_policy_allows_verified_users_to_change_activation(): void
    {
        $user = User::factory()->create();

        foreach ([Customer::factory()->create(), Project::factory()->create(), Epic::factory()->create()] as $record) {
            $this->assertTrue($user->can('deactivate', $record));
            $this->assertTrue($user->can('reactivate', $record));
        }
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function childStates(): array
    {
        return [
            'active child' => [[]],
            'inactive child' => [['inactive']],
            'trashed child' => [['trashed']],
            'trashed inactive child' => [['inactive', 'trashed']],
        ];
    }

    /**
     * @param  list<string>  $states
     */
    #[DataProvider('childStates')]
    public function test_customer_with_any_project_offers_deactivation_instead_of_deletion(array $states): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $projectFactory = Project::factory()->for($customer);

        foreach ($states as $state) {
            $projectFactory = $projectFactory->{$state}();
        }

        $projectFactory->create();

        $this->get(route('customers.index'))
            ->assertSee('data-test="customer-deactivate-'.$customer->id.'"', false)
            ->assertDontSee('data-test="customer-delete-'.$customer->id.'"', false);
    }

    /**
     * @param  list<string>  $states
     */
    #[DataProvider('childStates')]
    public function test_project_with_any_epic_offers_deactivation_instead_of_deletion(array $states): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epicFactory = Epic::factory()->for($project);

        foreach ($states as $state) {
            $epicFactory = $epicFactory->{$state}();
        }

        $epicFactory->create();

        $this->get(route('projects.index'))
            ->assertSee('data-test="project-deactivate-'.$project->id.'"', false)
            ->assertDontSee('data-test="project-delete-'.$project->id.'"', false);
    }

    public function test_epics_always_offer_deletion(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();

        $this->get(route('epics.index'))
            ->assertSee('data-test="epic-delete-'.$epic->id.'"', false)
            ->assertDontSee('data-test="epic-deactivate-'.$epic->id.'"', false);
    }

    public function test_inactive_customers_are_searchable_by_name_and_update_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-03-01 10:00:00');
        $updatedCustomer = Customer::factory()->inactive()->create(['name' => 'Alpha Dormant']);
        Customer::factory()->inactive()->create(['name' => 'Beta Dormant']);
        Customer::factory()->create(['name' => 'Alpha Active']);
        $this->travelTo('2026-07-15 10:00:00');
        $updatedCustomer->touch();

        $this->get(route('customers.inactive.index', ['search' => 'Alpha']))
            ->assertSee('Alpha Dormant')
            ->assertDontSee('Beta Dormant')
            ->assertDontSee('Alpha Active');

        $this->get(route('customers.inactive.index', ['search' => '2026-07-15']))
            ->assertSee('Alpha Dormant')
            ->assertDontSee('Beta Dormant');
    }

    public function test_inactive_projects_are_searchable_by_customer_name_and_update_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-03-01 10:00:00');
        $customer = Customer::factory()->create(['name' => 'Searchable Customer']);
        $dates = ['start_date' => '2026-01-01', 'end_date' => '2026-02-01'];
        Project::factory()->for($customer)->inactive()->create(['name' => 'Matching project', ...$dates]);
        $otherProject = Project::factory()->inactive()->create(['name' => 'Other project', ...$dates]);
        Project::factory()->for($customer)->create(['name' => 'Active project']);
        $this->travelTo('2026-07-15 10:00:00');
        $otherProject->touch();

        $this->get(route('projects.inactive.index', ['search' => 'Searchable Customer']))
            ->assertSee('Matching project')
            ->assertDontSee('Other project')
            ->assertDontSee('Active project');

        $this->get(route('projects.inactive.index', ['search' => '2026-07-15']))
            ->assertSee('Other project')
            ->assertDontSee('Matching project');
    }

    public function test_inactive_epics_are_searchable_by_project_and_customer_names(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Epic Customer']);
        $project = Project::factory()->for($customer)->create(['name' => 'Epic Project']);
        Epic::factory()->for($project)->inactive()->create(['name' => 'Matching epic']);
        Epic::factory()->inactive()->create(['name' => 'Other epic']);
        Epic::factory()->for($project)->create(['name' => 'Active epic']);

        foreach (['Epic Customer', 'Epic Project'] as $search) {
            $this->get(route('epics.inactive.index', ['search' => $search]))
                ->assertSee('Matching epic')
                ->assertDontSee('Other epic')
                ->assertDontSee('Active epic');
        }
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_inactive_list_fragment_only_returns_results(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $model::factory()->inactive()->create(['name' => 'Fragment record']);

        $this->get(route($resource.'.inactive.index'), ['X-List-Fragment' => 'true'])
            ->assertOk()
            ->assertSee('data-list-results', false)
            ->assertSee('Fragment record')
            ->assertDontSee('<!DOCTYPE html>', false);
    }

    /**
     * @param  class-string<Customer|Project|Epic>  $model
     */
    #[DataProvider('resources')]
    public function test_inactive_list_paginates_only_inactive_records(string $model, string $resource): void
    {
        $this->actingAs(User::factory()->create());
        $model::factory()->count(ListQueryBase::PER_PAGE + 1)->inactive()->create();
        $model::factory()->count(2)->create();
        $model::factory()->inactive()->trashed()->create();

        $this->get(route($resource.'.inactive.index'))
            ->assertViewHas($resource, fn (LengthAwarePaginator $records): bool => $records->total() === ListQueryBase::PER_PAGE + 1
                && $records->count() === ListQueryBase::PER_PAGE
                && $records->getCollection()->every(fn (Model $record): bool => $record->active === false));

        $this->get(route($resource.'.inactive.index', ['page' => 2]))
            ->assertViewHas($resource, fn (LengthAwarePaginator $records): bool => $records->count() === 1
                && $records->first()->active === false);
    }

    public function test_childless_parents_keep_the_delete_action(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $project = Project::factory()->create();

        $this->get(route('customers.index'))
            ->assertSee('data-test="customer-delete-'.$customer->id.'"', false)
            ->assertDontSee('data-test="customer-deactivate-'.$customer->id.'"', false);

        $this->get(route('projects.index'))
            ->assertSee('data-test="project-delete-'.$project->id.'"', false)
            ->assertDontSee('data-test="project-deactivate-'.$project->id.'"', false);
    }

    public function test_deactivating_a_parent_does_not_cascade_to_children(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        $project = $epic->project;
        $customer = $project->customer;

        $this->patch(route('customers.deactivate', $customer));
        $this->patch(route('projects.deactivate', $project));

        $this->assertFalse($customer->refresh()->active);
        $this->assertFalse($project->refresh()->active);
        $this->assertTrue($epic->refresh()->active);
    }

    public function test_inactive_customer_keeps_its_name_reserved(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->inactive()->create(['name' => 'Reserved name']);

        $this->post(route('customers.store'), ['name' => 'Reserved name'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 1);
    }
}
