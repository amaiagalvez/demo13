<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_form_does_not_preload_every_customer(): void
    {
        $this->actingAs(User::factory()->create());

        for ($index = 1; $index <= 21; $index++) {
            Customer::factory()->create([
                'name' => sprintf('Option-only customer %02d', $index),
            ]);
        }

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Option-only customer 21');
    }

    public function test_customer_options_are_searchable_and_limited_to_twenty(): void
    {
        $this->actingAs(User::factory()->create());

        for ($index = 1; $index <= 21; $index++) {
            Customer::factory()->create([
                'name' => sprintf('Search result %02d', $index),
            ]);
        }

        $response = $this->getJson(route('customers.options', ['q' => 'Search result']));

        $response->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('results.0.text', 'Search result 01')
            ->assertJsonMissing(['text' => 'Search result 21']);
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
