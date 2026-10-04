<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Queries\ListQueryBase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_form_does_not_preload_every_customer(): void
    {
        $this->actingAs(User::factory()->create());

        $total = ListQueryBase::PER_PAGE + 1;

        for ($index = 1; $index <= $total; $index++) {
            Customer::factory()->create([
                'name' => sprintf('Option-only customer %02d', $index),
            ]);
        }

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Option-only customer 21');
    }

    public function test_project_list_offers_form_action_when_no_active_customers_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee(__('No active customers are available. Open the customer form to create one.'))
            ->assertSee(__('Open customer form'))
            ->assertSee('href="'.route('customers.index', ['create' => 1]).'"', false)
            ->assertSee('project-no-customer-form-button');
    }

    public function test_customer_options_are_searchable_and_limited_to_the_shared_page_size(): void
    {
        $this->actingAs(User::factory()->create());

        $total = ListQueryBase::PER_PAGE + 1;

        for ($index = 1; $index <= $total; $index++) {
            Customer::factory()->create([
                'name' => sprintf('Search result %02d', $index),
            ]);
        }

        $response = $this->getJson(route('customers.options', ['q' => 'Search result']));

        $response->assertOk()
            ->assertJsonCount(ListQueryBase::PER_PAGE, 'results')
            ->assertJsonPath('results.0.text', 'Search result 01')
            ->assertJsonMissing(['text' => sprintf('Search result %02d', $total)]);
    }

    public function test_customer_options_only_include_active_customers(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Active select customer']);
        Customer::factory()->inactive()->create(['name' => 'Inactive select customer']);
        Customer::factory()->trashed()->create(['name' => 'Deleted select customer']);

        $this->getJson(route('customers.options', ['q' => 'select customer']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'Active select customer');
    }

    public function test_customer_options_reject_search_terms_over_one_hundred_characters(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('customers.options', ['q' => str_repeat('a', 101)]))
            ->assertJsonValidationErrors('q');
    }

    public function test_guests_are_redirected_when_requesting_customer_options(): void
    {
        $this->get(route('customers.options'))
            ->assertRedirect(route('login'));
    }
}
