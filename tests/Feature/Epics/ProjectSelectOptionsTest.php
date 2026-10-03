<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_epic_form_does_not_preload_every_project(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        for ($index = 1; $index <= 21; $index++) {
            Project::factory()->for($customer)->create([
                'name' => sprintf('Option-only project %02d', $index),
            ]);
        }

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertDontSee('Option-only project 21');
    }

    public function test_project_options_search_by_project_or_customer_and_limit_results_to_twenty(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'Shared search customer']);

        for ($index = 1; $index <= 21; $index++) {
            Project::factory()->for($customer)->create([
                'name' => sprintf('Project result %02d', $index),
            ]);
        }

        $response = $this->getJson(route('projects.options', ['q' => 'Shared search customer']));

        $response->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('results.0.text', 'Project result 01 (Shared search customer)')
            ->assertJsonMissing(['text' => 'Project result 21 (Shared search customer)']);

        $this->getJson(route('projects.options', ['q' => 'Project result 21']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'Project result 21 (Shared search customer)');
    }

    public function test_project_options_reject_search_terms_over_one_hundred_characters(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('projects.options', ['q' => str_repeat('a', 101)]))
            ->assertJsonValidationErrors('q');
    }

    public function test_guests_are_redirected_when_requesting_project_options(): void
    {
        $this->get(route('projects.options'))
            ->assertRedirect(route('login'));
    }
}
