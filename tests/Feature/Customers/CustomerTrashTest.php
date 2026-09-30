<?php

namespace Tests\Feature\Customers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerTrashTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSessionHas('status', __('Customer restored successfully.'));

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
                __('Customer restored successfully. No new customer was created with the repeated name.'),
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
                __('Customer cannot be restored while another active customer uses this name.'),
            );

        $this->assertSoftDeleted($deletedCustomer);
        $this->assertModelExists($activeCustomer);
    }

    public function test_restore_returns_conflict_when_unique_name_is_taken_after_precheck(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::factory()->trashed()->create(['name' => 'Concurrent Restore Customer']);
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use (&$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), 'customers')
                || ! in_array('Concurrent Restore Customer', $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Customer::factory()->create(['name' => 'Concurrent Restore Customer']);
        });

        $this->patch(route('customers.trash.restore', $deletedCustomer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas(
                'error',
                __('Customer cannot be restored while another active customer uses this name.'),
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
            ->assertRedirect(route('customers.trash.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_a_project_cannot_be_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->trashed()->create();
        $project = Project::factory()->for($customer)->create();

        $this->delete(route('customers.trash.destroy', $customer->id))
            ->assertRedirect(route('customers.trash.index'))
            ->assertSessionHas('error', __('Customer cannot be permanently deleted while it has projects.'));

        $this->assertSoftDeleted($customer);
        $this->assertModelExists($project);
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
