<?php

namespace Tests\Browser\Customers;

use App\Models\User;
use Tests\DuskTestCase;
use App\Models\Customer;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class CustomerCrudTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_customer_list_can_be_searched_and_cleared(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->type('[data-test="customer-search"]', 'Ane')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    '[data-test="customer-search-submit"]'
                ))
                ->assertQueryStringHas('search', 'Ane')
                ->assertSee('Ane Bezeroa')
                ->assertDontSee('Jon Bezeroa')
                ->click('[data-test="customer-search-clear"]')
                ->waitForLocation('/customers')
                ->assertSee('Ane Bezeroa')
                ->assertSee('Jon Bezeroa');
        });
    }

    public function test_cancel_clears_the_create_and_edit_forms(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Original Customer']);

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->type('dialog[open] [data-test="customer-name"]', 'Unsaved Customer')
                ->click('dialog[open] [data-flux-modal-close] button')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->acceptDialog()
                ->waitUntilMissing('dialog[open]')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertInputValue('dialog[open] [data-test="customer-name"]', '')
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->waitUntilMissing('dialog[open]')
                ->click("[data-test='customer-edit-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->clear('dialog[open] [data-test="customer-name"]')
                ->type('dialog[open] [data-test="customer-name"]', 'Unsaved Change')
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->acceptDialog()
                ->waitUntilMissing('dialog[open]')
                ->click("[data-test='customer-edit-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->assertInputValue(
                    'dialog[open] [data-test="customer-name"]',
                    'Original Customer',
                );
        });

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Original Customer',
        ]);
    }

    public function test_customer_save_requires_changes_and_cancel_warns_about_unsaved_changes(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Original Customer']);

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
                    true,
                )
                ->type('dialog[open] [data-test="customer-name"]', 'Changed Customer')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
                    false,
                )
                ->clear('dialog[open] [data-test="customer-name"]')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
                    true,
                )
                ->type('dialog[open] [data-test="customer-name"]', 'Changed Customer')
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->dismissDialog()
                ->assertPresent('dialog[open]')
                ->assertInputValue('dialog[open] [data-test="customer-name"]', 'Changed Customer')
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->acceptDialog()
                ->waitUntilMissing('dialog[open]')
                ->click("[data-test='customer-edit-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
                    true,
                )
                ->clear('dialog[open] [data-test="customer-name"]')
                ->type('dialog[open] [data-test="customer-name"]', 'Updated Customer')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-submit"]\').disabled',
                    false,
                );
        });
    }

    public function test_duplicate_name_error_is_visible_in_the_create_modal(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['name' => 'Existing Customer']);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->type('dialog[open] [data-test="customer-name"]', 'Existing Customer')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    'dialog[open] [data-test="customer-submit"]'
                ))
                ->assertPresent('dialog[open]')
                ->assertSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => 'name']),
                )
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->waitUntilMissing('dialog[open]')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertInputValue('dialog[open] [data-test="customer-name"]', '')
                ->assertDontSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => 'name']),
                )
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-name"]\').hasAttribute(\'data-invalid\')',
                    false,
                );
        });

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_edit_validation_error_does_not_leak_into_the_create_form(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['name' => 'Existing Customer']);
        $customer = Customer::factory()->create(['name' => 'Editable Customer']);

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click("[data-test='customer-edit-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->clear('dialog[open] [data-test="customer-name"]')
                ->type('dialog[open] [data-test="customer-name"]', 'Existing Customer')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    'dialog[open] [data-test="customer-submit"]'
                ))
                ->assertSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => 'name']),
                )
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->waitUntilMissing('dialog[open]')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertDontSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => 'name']),
                )
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-name"]\').hasAttribute(\'data-invalid\')',
                    false,
                );
        });
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
        $untouchedCustomer = Customer::factory()->create(['name' => 'Untouched Customer']);
        $customerId = null;

        $this->browse(function (Browser $browser) use ($user, &$customerId): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->assertScript('document.querySelectorAll("dialog").length', 2)
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertSee(__('New customer'))
                ->type('dialog[open] [data-test="customer-name"]', 'Dusk Customer')
                ->click('dialog[open] [data-test="customer-submit"]')
                ->waitForLocation('/customers')
                ->assertSee('Dusk Customer')
                ->assertVisible('[data-test="customer-status"]')
                ->waitUntil(
                    'document.querySelector(\'[data-test="customer-status"]\').style.display === \'none\'',
                    12,
                );

            $customer = Customer::query()->where('name', 'Dusk Customer')->firstOrFail();
            $customerId = $customer->id;

            $browser->assertAttribute(
                "[data-test='customer-edit-{$customer->id}']",
                'aria-label',
                __('Edit'),
            )
                ->assertAttribute(
                    "[data-test='customer-delete-{$customer->id}']",
                    'aria-label',
                    __('Delete'),
                )
                ->click("[data-test='customer-edit-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->clear('dialog[open] [data-test="customer-name"]')
                ->type('dialog[open] [data-test="customer-name"]', 'Updated Dusk Customer')
                ->click('dialog[open] [data-test="customer-submit"]')
                ->waitForLocation('/customers')
                ->assertSee('Updated Dusk Customer');

            $browser->click("[data-test='customer-delete-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->click("dialog[open] [data-test='customer-confirm-submit']")
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
        $restorableCustomer = Customer::factory()->trashed()->create(['name' => 'Restorable Customer']);
        $deletableCustomer = Customer::factory()->trashed()->create(['name' => 'Deletable Customer']);

        $this->browse(function (Browser $browser) use ($user, $restorableCustomer, $deletableCustomer): void {
            $browser->loginAs($user)
                ->visit('/customers/trash')
                ->assertSee('Restorable Customer')
                ->assertSee('Deletable Customer')
                ->click("[data-test='customer-restore-{$restorableCustomer->id}']")
                ->waitFor('dialog[open]')
                ->assertSeeIn('dialog[open]', __('Restore customer?'))
                ->click('dialog[open] [data-flux-modal-close] button')
                ->waitUntilMissing('dialog[open]')
                ->assertSee('Restorable Customer')
                ->click("[data-test='customer-restore-{$restorableCustomer->id}']")
                ->waitFor('dialog[open]')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    "dialog[open] [data-test='customer-confirm-submit']"
                ))
                ->assertDontSee('Restorable Customer')
                ->click("[data-test='customer-force-delete-{$deletableCustomer->id}']")
                ->waitFor('dialog[open]')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    "dialog[open] [data-test='customer-confirm-submit']"
                ))
                ->assertDontSee('Deletable Customer');
        });

        $this->assertNotSoftDeleted($restorableCustomer);
        $this->assertDatabaseMissing('customers', ['id' => $deletableCustomer->id]);
    }
}
