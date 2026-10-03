<?php

namespace Tests\Browser\Projects;

use App\Models\User;
use Tests\DuskTestCase;
use App\Models\Customer;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProjectCustomerSelectTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_project_customer_select_can_search_and_create_customers(): void
    {
        $user = User::factory()->create();
        $executionId = Str::uuid()->toString();
        $existingCustomerName = 'Northwind '.$executionId;
        $newCustomerName = 'Dusk Select2 '.$executionId;
        $projectName = 'Select2 project '.$executionId;
        Customer::factory()->create(['name' => $existingCustomerName]);

        $this->browse(function (Browser $browser) use (
            $user,
            $existingCustomerName,
            $newCustomerName,
            $projectName,
        ): void {
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
                ->waitUntil(
                    'document.querySelector("dialog[open] .select2-results")?.textContent.includes('.json_encode($existingCustomerName).')',
                    10,
                )
                ->assertSeeIn('dialog[open] .select2-results', $existingCustomerName)
                ->click('dialog[open] .select2-results__option--selectable')
                ->waitUntil('document.querySelector(\'[data-test="project-submit"]\').disabled === false', 10)
                ->assertEnabled('[data-test="project-submit"]')
                ->click('#select2-project-customer-id-container')
                ->type(
                    'dialog[open] .select2-container--open .select2-search__field',
                    $newCustomerName,
                )
                ->waitUntil(
                    'document.querySelector("dialog[open] .select2-results")?.textContent.includes('.json_encode(__('Create customer').': '.$newCustomerName).')',
                    10,
                )
                ->assertSeeIn(
                    'dialog[open] .select2-results',
                    __('Create customer').': '.$newCustomerName,
                )
                ->click('dialog[open] .select2-results__option--selectable')
                ->waitUntil(
                    'document.querySelector("#project-customer-id option:checked")?.text === '.json_encode($newCustomerName),
                    10,
                )
                ->type('dialog[open] [data-test="project-name"]', $projectName);

            $browser->script('const input = document.querySelector(\'dialog[open] [data-test="project-start-date"]\'); input.value = "2026-10-01"; input.dispatchEvent(new Event("input", { bubbles: true })); input.dispatchEvent(new Event("change", { bubbles: true }));');

            $browser
                ->click('dialog[open] [data-test="project-submit"]')
                ->waitFor('[data-test="project-status"]')
                ->visit('/projects?search='.urlencode($projectName))
                ->waitUntil(
                    '(() => { const element = document.querySelector(\'tbody time[data-local-datetime="date"]\'); return element && element.textContent.trim() === window.formatLocalDateTime(element.dateTime, "date"); })()',
                    10,
                )
                ->assertScript(
                    'document.querySelector(\'tbody time[data-local-datetime="date"]\').dateTime',
                    '2026-10-01',
                )
                ->assertScript(
                    '!document.querySelector(\'tbody time[data-local-datetime="date"]\').textContent.trim().includes(":")',
                    true,
                );
        });

        $customer = Customer::query()->where('name', $newCustomerName)->firstOrFail();

        $this->assertDatabaseHas('projects', [
            'name' => $projectName,
            'customer_id' => $customer->id,
        ]);
    }
}
