<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Tests\Support\RacesNameInsert;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_customer_trash_shows_its_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.trash.index'))
            ->assertOk()
            ->assertSee(__('Trash is empty.'));
    }

    public function test_trash_preserves_index_columns_and_appends_the_deletion_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $customer = Customer::factory()->create(['name' => 'Customer with dates']);
        $activeResponse = $this->get(route('customers.index'));
        $customer->delete();

        $response = $this->get(route('customers.trash.index'));

        $activeResponse->assertDontSee(__('Deleted at'));
        $response->assertSeeInOrder(['<thead', __('Name'), __('Projects'), __('Epics'), __('Comments'), __('Deleted at'), __('Actions')], false)
            ->assertSeeInOrder(['Customer with dates', '2026-10-02']);
    }

    public function test_trash_only_lists_deleted_customers(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Active Customer']);
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Deleted Customer']);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Active Customer')
            ->assertDontSee('Deleted Customer');

        $this->get(route('customers.trash.index'))
            ->assertOk()
            ->assertSee('Deleted Customer')
            ->assertDontSee('Active Customer');
    }

    public function test_deleted_customer_can_be_restored(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();

        $this->patch(route('customers.trash.restore', $customer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas('status', __('Record restored successfully.'));

        $this->assertNotSoftDeleted($customer);
    }

    public function test_confirmed_restore_does_not_create_an_additional_customer(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();

        $this->patch(route('customers.trash.restore', $customer->id), [
            'resolve_name_conflict' => '1',
        ])
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas(
                'status',
                __('Record restored successfully. No new record was created with the repeated name.'),
            );

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleted_customer_cannot_be_restored_when_name_is_active(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Ane Bezeroa']);
        $activeCustomer = Customer::factory()->create(['name' => 'Ane Bezeroa']);

        $this->patch(route('customers.trash.restore', $deletedCustomer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas(
                'error',
                __('Cannot be restored because another record outside the trash uses this name.'),
            );

        $this->assertSoftDeleted($deletedCustomer);
        $this->assertModelExists($activeCustomer);
    }

    public function test_deleted_customer_cannot_be_restored_when_an_inactive_customer_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Ane Bezeroa']);
        $inactiveCustomer = Customer::factory()->inactive()->create(['name' => 'Ane Bezeroa']);

        $this->patch(route('customers.trash.restore', $deletedCustomer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas(
                'error',
                __('Cannot be restored because another record outside the trash uses this name.'),
            );

        $this->assertSoftDeleted($deletedCustomer);
        $this->assertModelExists($inactiveCustomer);
        $this->assertFalse($inactiveCustomer->active);
    }

    public function test_restore_returns_conflict_when_unique_name_is_taken_after_precheck(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Concurrent Restore Customer']);
        RacesNameInsert::afterUniquenessSelect(
            'customers',
            'Concurrent Restore Customer',
            static function (): void {
                Customer::factory()->create(['name' => 'Concurrent Restore Customer']);
            },
        );

        $this->patch(route('customers.trash.restore', $deletedCustomer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas(
                'error',
                __('Cannot be restored because another record outside the trash uses this name.'),
            );

        $this->assertSoftDeleted($deletedCustomer);
        $this->assertDatabaseHas('customers', [
            'name' => 'Concurrent Restore Customer',
            'deleted_at' => null,
        ]);
    }

    public function test_deleted_customer_can_be_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();

        $this->delete(route('customers.trash.destroy', $customer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas('status', __('Record permanently deleted.'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_a_project_cannot_be_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();
        $project = Project::factory()->for($customer)->create();

        $this->delete(route('customers.trash.destroy', $customer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas('error', __('Cannot be permanently deleted while it has related records.'));

        $this->assertSoftDeleted($customer);
        $this->assertModelExists($project);
    }

    public function test_customer_model_cannot_be_force_deleted_while_it_has_trashed_projects(): void
    {
        $customer = Customer::factory()->trashed()->create();
        $project = Project::factory()->for($customer)->trashed()->create();

        $this->assertFalse($customer->forceDelete());

        $this->assertSoftDeleted($customer);
        $this->assertSoftDeleted($project);
    }

    public function test_active_customer_cannot_be_restored_or_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->patch(route('customers.trash.restore', $customer->id))->assertNotFound();
        $this->delete(route('customers.trash.destroy', $customer->id))->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_deleted_customers_can_be_searched_by_visible_data(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->trashed()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->trashed()->create(['name' => 'Jon Bezeroa']);

        $this->get(route('customers.trash.index', ['search' => 'Ane']))
            ->assertOk()
            ->assertSee('Ane Bezeroa')
            ->assertDontSee('Jon Bezeroa');
    }
}
