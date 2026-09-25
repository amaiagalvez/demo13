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

  public function test_duplicate_name_error_is_visible_in_the_create_modal(): void
  {
    $user = User::factory()->create();
    Customer::query()->create(['name' => 'Existing Customer']);

    $this->browse(function (Browser $browser) use ($user): void {
      $browser->loginAs($user)
        ->visit('/customers')
        ->click('[data-test="customer-create-button"]')
        ->waitFor('dialog[open]')
        ->type('dialog[open] [data-test="customer-name"]', 'Existing Customer')
        ->waitForReload(fn(Browser $browser) => $browser->click(
          'dialog[open] [data-test="customer-submit"]'
        ))
        ->assertPresent('dialog[open]')
        ->assertSeeIn(
          'dialog[open]',
          __('validation.unique', ['attribute' => 'name']),
        );
    });

    $this->assertDatabaseCount('customers', 1);
  }

  public function test_customer_form_blocks_duplicate_submissions(): void
  {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
      $browser->loginAs($user)
        ->visit('/customers')
        ->click('[data-test="customer-create-button"]')
        ->waitFor('dialog[open]')
        ->type('dialog[open] [data-test="customer-name"]', 'Dusk Customer');

      $browser->script(<<<'JS'
                document.querySelector('dialog[open] form').addEventListener('submit', event => {
                    event.preventDefault();
                });
                JS);

      $browser->click('dialog[open] [data-test="customer-submit"]')
        ->assertScript(
          'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
          true,
        )
        ->assertScript(<<<'JS'
                    (() => {
                        const form = document.querySelector('dialog[open] form');
                        const event = new Event('submit', { bubbles: true, cancelable: true });

                        form.dispatchEvent(event);

                        return event.defaultPrevented;
                    })()
                    JS, true);
    });

    $this->assertDatabaseCount('customers', 0);
  }

  public function test_customer_can_be_created_updated_and_deleted_from_the_drawer(): void
  {
    $user = User::factory()->create();
    $untouchedCustomer = Customer::query()->create(['name' => 'Untouched Customer']);
    $customerId = null;

    $this->browse(function (Browser $browser) use ($user, &$customerId): void {
      $browser->loginAs($user)
        ->visit('/customers')
        ->assertScript('document.querySelectorAll("dialog").length', 3)
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
        ->waitFor('dialog[open]')
        ->click("dialog[open] [data-test='customer-delete-confirm']")
        ->waitForLocation('/customers')
        ->assertDontSee('Updated Dusk Customer');
    });

    $this->assertSoftDeleted('customers', ['id' => $customerId]);
    $this->assertDatabaseHas('customers', [
      'id' => $untouchedCustomer->id,
      'name' => 'Untouched Customer',
    ]);
  }

  public function test_customer_can_be_restored_or_permanently_deleted_from_trash(): void
  {
    $user = User::factory()->create();
    $restorableCustomer = Customer::query()->create(['name' => 'Restorable Customer']);
    $deletableCustomer = Customer::query()->create(['name' => 'Deletable Customer']);
    $restorableCustomer->delete();
    $deletableCustomer->delete();

    $this->browse(function (Browser $browser) use ($user, $restorableCustomer, $deletableCustomer): void {
      $browser->loginAs($user)
        ->visit('/customers/trash')
        ->assertSee('Restorable Customer')
        ->assertSee('Deletable Customer')
        ->waitForReload(fn(Browser $browser) => $browser->click(
          "[data-test='customer-restore-{$restorableCustomer->id}']"
        ))
        ->assertDontSee('Restorable Customer')
        ->click("[data-test='customer-force-delete-{$deletableCustomer->id}']")
        ->waitFor('dialog[open]')
        ->waitForReload(fn(Browser $browser) => $browser->click(
          "dialog[open] [data-test='customer-force-delete-confirm']"
        ))
        ->assertDontSee('Deletable Customer');
    });

    $this->assertNotSoftDeleted($restorableCustomer);
    $this->assertDatabaseMissing('customers', ['id' => $deletableCustomer->id]);
  }
}
