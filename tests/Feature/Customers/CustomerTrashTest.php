<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTrashTest extends TestCase
{
  use RefreshDatabase;

  public function test_guests_cannot_access_the_customer_trash(): void
  {
    $this->get(route('customers.trash.index'))->assertRedirect(route('login'));
  }

  public function test_trash_only_lists_deleted_customers(): void
  {
    $this->actingAs(User::factory()->create());
    Customer::query()->create(['name' => 'Active Customer']);
    $deletedCustomer = Customer::query()->create(['name' => 'Deleted Customer']);
    $deletedCustomer->delete();

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
    $customer = Customer::query()->create(['name' => 'Ane Bezeroa']);
    $customer->delete();

    $this->patch(route('customers.trash.restore', $customer->id))
      ->assertRedirect(route('customers.trash.index'));

    $this->assertNotSoftDeleted($customer);
  }

  public function test_deleted_customer_can_be_permanently_deleted(): void
  {
    $this->actingAs(User::factory()->create());
    $customer = Customer::query()->create(['name' => 'Ane Bezeroa']);
    $customer->delete();

    $this->delete(route('customers.trash.destroy', $customer->id))
      ->assertRedirect(route('customers.trash.index'));

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
  }

  public function test_active_customer_cannot_be_restored_or_permanently_deleted(): void
  {
    $this->actingAs(User::factory()->create());
    $customer = Customer::query()->create(['name' => 'Ane Bezeroa']);

    $this->patch(route('customers.trash.restore', $customer->id))->assertNotFound();
    $this->delete(route('customers.trash.destroy', $customer->id))->assertNotFound();

    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
  }

  public function test_deleted_customers_can_be_searched_by_visible_data(): void
  {
    $this->actingAs(User::factory()->create());
    $matchingCustomer = Customer::query()->create(['name' => 'Ane Bezeroa']);
    $otherCustomer = Customer::query()->create(['name' => 'Jon Bezeroa']);
    $matchingCustomer->delete();
    $otherCustomer->delete();

    $this->get(route('customers.trash.index', ['search' => 'Ane']))
      ->assertOk()
      ->assertSee('Ane Bezeroa')
      ->assertDontSee('Jon Bezeroa');
  }
}
