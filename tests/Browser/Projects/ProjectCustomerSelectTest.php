<?php

namespace Tests\Browser\Projects;

use App\Models\User;
use Tests\DuskTestCase;
use App\Models\Customer;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;

class ProjectCustomerSelectTest extends DuskTestCase
{
    public function test_project_customer_select_can_search_and_create_customers(): void
    {
        $user = User::factory()->create();
        $executionId = Str::uuid()->toString();
        $existingCustomerName = 'Northwind '.$executionId;
        $newCustomerName = 'Dusk Select2 '.$executionId;
        Customer::factory()->create(['name' => $existingCustomerName]);

        $this->browse(function (Browser $browser) use ($user, $existingCustomerName, $newCustomerName): void {
            $browser->loginAs($user)
                ->visit('/projects')
                ->assertScript('typeof window.initializeProjectCustomerSelect', 'function')
                ->waitUntil('typeof window.jQuery?.fn?.select2 === "function"', 10)
                ->click('[data-test="project-create-button"]')
                ->waitFor('dialog[open]')
                ->assertScript(
                    'document.querySelector("#project-customer-id").classList.contains("select2-hidden-accessible")',
                    true,
                )
                ->click('#select2-project-customer-id-container')
                ->type('dialog[open] .select2-container--open .select2-search__field', 'Northwind')
                ->assertSeeIn('dialog[open] .select2-results', $existingCustomerName)
                ->click('dialog[open] .select2-results__option--selectable')
                ->click('#select2-project-customer-id-container')
                ->type(
                    'dialog[open] .select2-container--open .select2-search__field',
                    $newCustomerName,
                )
                ->assertSeeIn(
                    'dialog[open] .select2-results',
                    'Create customer: '.$newCustomerName,
                )
                ->click('dialog[open] .select2-results__option--selectable')
                ->waitUntil(
                    'document.querySelector("#project-customer-id option:checked")?.text === '.json_encode($newCustomerName),
                    10,
                )
                ->type('dialog[open] [data-test="project-name"]', 'Select2 project')
                ->type('dialog[open] [data-test="project-start-date"]', '2026-10-01')
                ->click('dialog[open] [data-test="project-submit"]')
                ->waitForLocation('/projects')
                ->assertSee('Select2 project')
                ->assertSee($newCustomerName);
        });

        $customer = Customer::query()->where('name', $newCustomerName)->firstOrFail();

        $this->assertDatabaseHas('projects', [
            'name' => 'Select2 project',
            'customer_id' => $customer->id,
        ]);
    }
}
