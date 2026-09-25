<?php

namespace Tests\Browser\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CustomerCrudTest extends DuskTestCase
{
  use DatabaseMigrations;

  public function test_customer_can_be_created_updated_and_deleted_from_the_drawer(): void
  {
    $user = User::factory()->create();
    $customerId = null;

    $this->browse(function (Browser $browser) use ($user, &$customerId): void {
      $browser->loginAs($user)
        ->visit('/customers')
        ->click('[data-test="customer-create-button"]')
        ->waitFor('dialog[open]')
        ->assertSee(__('New customer'))
        ->type('dialog[open] [data-test="customer-name"]', 'Dusk Customer')
        ->click('dialog[open] [data-test="customer-submit"]')
        ->waitForLocation('/customers')
        ->assertSee('Dusk Customer');

      $customer = Customer::query()->where('name', 'Dusk Customer')->firstOrFail();
      $customerId = $customer->id;

      $browser->click("[data-test='customer-edit-{$customer->id}']")
        ->waitFor('dialog[open]')
        ->clear('dialog[open] [data-test="customer-name"]')
        ->type('dialog[open] [data-test="customer-name"]', 'Updated Dusk Customer')
        ->click('dialog[open] [data-test="customer-submit"]')
        ->waitForLocation('/customers')
        ->assertSee('Updated Dusk Customer');

      $browser->click("[data-test='customer-delete-{$customer->id}']")
        ->waitForLocation('/customers')
        ->assertDontSee('Updated Dusk Customer');
    });

    $this->assertDatabaseMissing('customers', ['id' => $customerId]);
  }
}
