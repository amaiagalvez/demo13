<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_customers_login_page(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->get(route('customers.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_authenticated_users_can_create_update_and_delete_customers(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), ['name' => 'Ane Bezeroa'])
            ->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();
        $this->assertSame('Ane Bezeroa', $customer->name);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('customer-create')
            ->assertSee('customer-edit-' . $customer->id);

        $this->put(route('customers.update', $customer), ['name' => 'Jon Bezeroa'])
            ->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jon Bezeroa']);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);
    }

    public function test_customer_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('customers.index'))
            ->post(route('customers.store'), [
                '_customer_form' => 'create',
                'name' => '',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name'])
            ->assertSessionHasInput('_customer_form', 'create');
    }

    public function test_database_rejects_duplicate_customer_names(): void
    {
        Customer::query()->create(['name' => 'Ane Bezeroa']);

        $this->expectException(QueryException::class);

        Customer::query()->create(['name' => 'Ane Bezeroa']);
    }

    public function test_customer_name_can_be_reused_after_soft_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedCustomer = Customer::query()->create(['name' => 'Ane Bezeroa']);
        $deletedCustomer->delete();

        $this->post(route('customers.store'), ['name' => 'Ane Bezeroa'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('deleted_customer_conflict', [
                'id' => $deletedCustomer->id,
                'name' => 'Ane Bezeroa',
            ]);

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('customer-conflict-create-new')
            ->assertSee('customer-conflict-restore')
            ->assertSee(__('Customer name already in trash'));

        $this->assertDatabaseCount('customers', 1);

        $this->post(route('customers.store'), [
            'name' => 'Ane Bezeroa',
            'reuse_deleted_name' => '1',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Ane Bezeroa',
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted($deletedCustomer);
    }

    public function test_failed_update_keeps_the_selected_customer_context(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::query()->create(['name' => 'Ane Bezeroa']);

        $this->from(route('customers.index'))
            ->put(route('customers.update', $customer), [
                '_customer_form' => 'edit-' . $customer->id,
                '_customer_id' => $customer->id,
                'name' => '',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors(['name'])
            ->assertSessionHasInput('_customer_form', 'edit-' . $customer->id)
            ->assertSessionHasInput('_customer_id', (string) $customer->id);
    }

    public function test_only_validated_attributes_are_persisted(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), [
            'name' => 'Ane Bezeroa',
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $this->assertDatabaseMissing('customers', [
            'name' => 'Ane Bezeroa',
            'created_at' => '2000-01-01 00:00:00',
        ]);
    }

    public function test_customers_are_paginated(): void
    {
        $this->actingAs(User::factory()->create());

        Customer::query()->create(['name' => 'Aardvark customer']);

        foreach (range(2, 16) as $number) {
            Customer::query()->create(['name' => sprintf('Customer %02d', $number)]);
        }

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('customer-pagination')
            ->assertSee('Aardvark customer')
            ->assertDontSee('Customer 16');
    }

    public function test_customers_can_be_searched_by_visible_data(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::query()->create(['name' => 'Ane Bezeroa']);
        Customer::query()->create(['name' => 'Jon Bezeroa']);

        $this->get(route('customers.index', ['search' => 'Ane']))
            ->assertOk()
            ->assertSee('Ane Bezeroa')
            ->assertDontSee('Jon Bezeroa');
    }

    public function test_invalid_search_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('customers.index', ['search' => ['Ane']]))
            ->assertSessionHasErrors(['search']);
    }

    public function test_customer_search_is_preserved_when_paginating(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 16) as $number) {
            Customer::query()->create(['name' => "Searchable Customer {$number}"]);
        }

        $this->get(route('customers.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('search=Searchable', false);
    }
}
