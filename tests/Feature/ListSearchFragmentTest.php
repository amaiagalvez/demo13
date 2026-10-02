<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListSearchFragmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_list_routes_return_only_the_results_fragment_for_fragment_requests(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            'customers.index',
            'customers.trash.index',
            'projects.index',
            'projects.trash.index',
            'epics.index',
            'epics.trash.index',
        ] as $route) {
            $response = $this->get(route($route), ['X-List-Fragment' => 'true']);

            $response
                ->assertOk()
                ->assertSee('data-list-results')
                ->assertDontSee('data-test="customer-create-button"')
                ->assertDontSee('<!DOCTYPE html>');
        }
    }
}
