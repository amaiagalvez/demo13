<?php

namespace Tests\Browser\Customers;

use App\Models\User;
use App\Models\Project;
use Tests\DuskTestCase;
use App\Models\Customer;
use Laravel\Dusk\Browser;
use App\Support\Validation\MaxLength;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class CustomerCrudTest extends DuskTestCase
{
    private const BROWSER_TIMEZONE = 'Europe/Madrid';

    use DatabaseMigrations;

    public function test_empty_customer_list_offers_a_create_action(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/customers?search=missing')
                ->assertSee(__('No customers match your search.'))
                ->assertMissing('[data-test="customer-empty-create"]')
                ->visit('/customers')
                ->assertSee(__('No customers yet.'))
                ->click('[data-test="customer-empty-create"]')
                ->waitFor('dialog[open] [data-test="customer-name"]')
                ->assertSeeIn('dialog[open]', __('New customer'));
        });
    }

    public function test_customer_table_truncates_long_names_and_keeps_actions_sticky(): void
    {
        $user = User::factory()->create();
        $longName = str_repeat('C', 255);
        $customer = Customer::factory()->create(['name' => $longName]);
        Customer::factory()->count(15)->create();

        $this->browse(function (Browser $browser) use ($user, $customer, $longName): void {
            $browser->loginAs($user)
                ->resize(1920, 1080)
                ->visit(route('customers.index', [
                    'search' => substr($longName, 0, MaxLength::string()),
                ]))
                ->waitFor('[data-test="customer-name-'.$customer->id.'"]');

            $browser->assertScript(<<<'JS'
                (() => {
                    const content = document.querySelector('.max-w-none');

                    if (!(content instanceof HTMLElement)) {
                        return false;
                    }

                    const bounds = content.getBoundingClientRect();

                    return bounds.left < window.innerWidth * 0.2
                        && window.innerWidth - bounds.right < window.innerWidth * 0.1;
                })()
                JS, true)
                ->resize(320, 420)
                ->assertAttribute('[data-test="customer-name-'.$customer->id.'"]', 'title', $longName)
                ->assertScript(
                    'getComputedStyle(document.querySelector(\'[data-test="customer-name-'.$customer->id.'"]\')).textOverflow',
                    'ellipsis',
                )
                ->assertScript(
                    'getComputedStyle(document.querySelector(\'.resource-list-table tbody tr td:last-child\')).position',
                    'sticky',
                )
                ->assertScript(
                    'getComputedStyle(document.querySelector(\'.resource-list-table tbody tr td:last-child\')).insetInlineEnd',
                    '0px',
                )
                ->assertScript(
                    'getComputedStyle(document.querySelector(\'.resource-list-table tbody tr td:last-child\')).textAlign',
                    'end',
                )
                ->assertScript(
                    '(() => { const container = document.querySelector(\'.resource-list-table ui-table-scroll-area\'); container.querySelector(\'table\').style.minWidth = \'800px\'; container.scrollLeft = 200; const actions = container.querySelector(\'tbody tr td:last-child\'); return container.scrollLeft > 0 && Math.abs(actions.getBoundingClientRect().right - container.getBoundingClientRect().right) < 3; })()',
                    true,
                );
        });
    }

    public function test_customer_trash_timestamp_displays_local_time(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Timezone deleted customer']);
        $customer->delete();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user);
            (new ChromeDevToolsDriver($browser->driver))->execute(
                'Emulation.setTimezoneOverride',
                ['timezoneId' => self::BROWSER_TIMEZONE],
            );

            $browser->visit(route('customers.trash.index'))
                ->waitFor('tbody time[data-local-datetime="datetime"]')
                ->waitUntil(
                    '(() => { const element = document.querySelector(\'tbody time[data-local-datetime="datetime"]\'); return element.textContent.trim() === window.formatLocalDateTime(element.dateTime); })()',
                    10,
                )
                ->assertScript(
                    'document.querySelector(\'tbody time[data-local-datetime="datetime"]\').textContent.trim().includes(":")',
                    true,
                );
        });
    }

    public function test_customer_with_projects_can_be_deactivated_and_reactivated_after_confirmation(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Customer to deactivate']);
        Project::factory()->for($customer)->create();

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->assertMissing("[data-test='customer-delete-{$customer->id}']")
                ->click("[data-test='customer-deactivate-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->assertSeeIn('dialog[open]', __('Deactivate record?'))
                ->click('dialog[open] [data-flux-modal-close] button')
                ->waitUntilMissing('dialog[open]')
                ->assertSee($customer->name);

            $freshCustomer = $customer->fresh();
            $this->assertNotNull($freshCustomer);
            $this->assertTrue($freshCustomer->active);

            $browser->click("[data-test='customer-deactivate-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    'dialog[open] [data-test="customer-confirm-submit"]'
                ))
                ->assertDontSee($customer->name)
                ->click('[data-test="customer-inactive-link"]')
                ->waitForLocation('/customers/inactive')
                ->assertSee($customer->name)
                ->assertSeeIn('thead', __('Name'))
                ->assertMissing("[data-test='customer-edit-{$customer->id}']")
                ->click("[data-test='customer-reactivate-{$customer->id}']")
                ->waitFor('dialog[open]')
                ->assertSeeIn('dialog[open]', __('Reactivate record?'));

            $freshCustomer = $customer->fresh();
            $this->assertNotNull($freshCustomer);
            $this->assertFalse($freshCustomer->active);
            $this->assertNotSoftDeleted($customer);

            $browser->waitForReload(fn (Browser $browser) => $browser->click(
                'dialog[open] [data-test="customer-confirm-submit"]'
            ))
                ->assertDontSee($customer->name)
                ->visit('/customers')
                ->assertSee($customer->name);
        });

        $freshCustomer = $customer->fresh();
        $this->assertNotNull($freshCustomer);
        $this->assertTrue($freshCustomer->active);
        $this->assertNotSoftDeleted($customer);
    }

    public function test_customer_list_can_be_searched_and_cleared(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['name' => 'Ane Bezeroa']);
        Customer::factory()->create(['name' => 'Jon Bezeroa']);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)->visit('/customers');
            $browser->script("window.listSearchPageState = 'preserved';");

            $browser
                ->assertScript(
                    'typeof window.Alpine.$data(document.querySelector("[data-list-results]")).searchInput',
                    'function',
                )
                ->assertScript('typeof window.Alpine.morph', 'function')
                ->type('[data-test="customer-search"]', 'Ane Bezeroa')
                ->waitUntil('window.location.search.includes("search=Ane")', 10)
                ->assertQueryStringHas('search', 'Ane Bezeroa')
                ->assertScript('window.listSearchPageState', 'preserved')
                ->assertSee('Ane Bezeroa')
                ->assertDontSee('Jon Bezeroa')
                // The create action stays outside the results fragment during the morph.
                ->assertVisible('[data-test="customer-create-button"]')
                ->assertAttribute('[data-test="customer-search-clear"]', 'title', __('Clear'))
                ->assertScript(
                    'document.querySelector(\'[data-test="customer-search-clear"]\').innerText.trim()',
                    '',
                )
                ->assertScript(
                    '(() => { const input = document.querySelector(\'[data-test="customer-search"]\'); const clear = document.querySelector(\'[data-test="customer-search-clear"]\'); const inputBounds = input.getBoundingClientRect(); const clearBounds = clear.getBoundingClientRect(); return clearBounds.left >= inputBounds.left && clearBounds.right <= inputBounds.right && clearBounds.top >= inputBounds.top && clearBounds.bottom <= inputBounds.bottom; })()',
                    true,
                )
                ->click('[data-test="customer-search-clear"]')
                ->waitUntil('document.body.innerText.includes("Jon Bezeroa")', 10)
                ->assertQueryStringMissing('search')
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
                ->waitForTextIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => __('validation.attributes')['name']]),
                )
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->waitUntilMissing('dialog[open]')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertInputValue('dialog[open] [data-test="customer-name"]', '')
                ->assertDontSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => __('validation.attributes')['name']]),
                )
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-name"]\').hasAttribute(\'data-invalid\')',
                    false,
                );
        });

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_browser_blocks_a_customer_name_that_breaks_the_native_constraints(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertScript(
                    'document.querySelector(\'dialog[open] [data-test="customer-name"]\').form.noValidate',
                    false,
                )
                ->type('dialog[open] [data-test="customer-name"]', 'Ane')
                ->click('dialog[open] [data-test="customer-submit"]')
                ->waitUntil(
                    'document.querySelector(\'dialog[open] [data-test="customer-name"]\').validity.tooShort',
                    5,
                )
                ->assertPresent('dialog[open]')
                ->assertInputValue('dialog[open] [data-test="customer-name"]', 'Ane')
                ->assertDontSeeIn(
                    'dialog[open]',
                    __('validation.min.string', [
                        'attribute' => __('validation.attributes')['name'],
                        'min' => 4,
                    ]),
                );
        });

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_whitespace_only_customer_name_still_reaches_server_validation(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->type('dialog[open] [data-test="customer-name"]', '    ')
                ->waitForReload(fn (Browser $browser) => $browser->click(
                    'dialog[open] [data-test="customer-submit"]'
                ))
                ->waitForTextIn(
                    'dialog[open]',
                    __('validation.required', ['attribute' => __('validation.attributes')['name']]),
                );
        });

        $this->assertDatabaseCount('customers', 0);
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
                ->waitForTextIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => __('validation.attributes')['name']]),
                )
                ->click('dialog[open] [data-test="customer-cancel"]')
                ->waitUntilMissing('dialog[open]')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open]')
                ->assertDontSeeIn(
                    'dialog[open]',
                    __('validation.unique', ['attribute' => __('validation.attributes')['name']]),
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

    public function test_customer_row_name_opens_the_edit_form(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Editable from the row']);

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-name-'.$customer->id.'"]')
                ->waitFor('dialog[open] [data-test="customer-name"]')
                ->assertSeeIn('dialog[open]', __('Edit customer'))
                ->assertInputValue('dialog[open] [data-test="customer-name"]', $customer->name);
        });
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
                ->assertVisible('[data-test="customer-status"]');

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

    public function test_customer_can_be_restored_from_the_name_conflict_modal(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->trashed()->create(['name' => 'Conflict customer']);

        $this->browse(function (Browser $browser) use ($user, $customer): void {
            $browser->loginAs($user)
                ->visit('/customers')
                ->click('[data-test="customer-create-button"]')
                ->waitFor('dialog[open] [data-test="customer-name"]')
                ->type('dialog[open] [data-test="customer-name"]', $customer->name)
                ->click('dialog[open] [data-test="customer-submit"]')
                ->waitFor('dialog[open] [data-test="customer-conflict-restore"]')
                ->assertSeeIn('dialog[open]', __('Record Name already in trash'))
                ->click('dialog[open] [data-test="customer-conflict-restore"]')
                ->waitForLocation('/customers/trash')
                ->assertSee(__('Record restored successfully.'))
                ->assertDontSee($customer->name);
        });

        $this->assertNotSoftDeleted($customer);
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
                ->click('[data-test="customer-active-link"]')
                ->waitForLocation('/customers')
                ->visit('/customers/trash')
                ->click("[data-test='customer-restore-{$restorableCustomer->id}']")
                ->waitFor('dialog[open]')
                ->assertSeeIn('dialog[open]', __('Restore record?'))
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
