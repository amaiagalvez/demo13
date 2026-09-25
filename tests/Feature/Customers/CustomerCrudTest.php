<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
  use RefreshDatabase;

  public function test_guests_are_redirected_to_the_customers_login_page(): void
  {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
  }

  public function test_authenticated_users_can_create_update_and_delete_customers(): void
  {
    $this->actingAs(User::factory()->create());

    $this->get(route('customers.create'))
      ->assertRedirect(route('customers.index'));

    $this->post(route('customers.store'), ['name' => 'Ane Bezeroa'])
      ->assertRedirect(route('customers.index'));

    $customer = Customer::query()->firstOrFail();
    $this->assertSame('Ane Bezeroa', $customer->name);

    $this->get(route('customers.index'))
      ->assertOk()
      ->assertSee('customer-create')
      ->assertSee('customer-edit-' . $customer->id);

    $this->get(route('customers.edit', $customer))
      ->assertRedirect(route('customers.index'));

    $this->put(route('customers.update', $customer), ['name' => 'Jon Bezeroa'])
      ->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jon Bezeroa']);

    $this->delete(route('customers.destroy', $customer))
      ->assertRedirect(route('customers.index'));
    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
  }

  public function test_customer_name_is_required(): void
  {
    $this->actingAs(User::factory()->create());

    $this->from(route('customers.create'))
      ->post(route('customers.store'), ['name' => ''])
      ->assertRedirect(route('customers.create'))
      ->assertSessionHasErrors(['name']);
  }
}
