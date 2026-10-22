<?php

namespace Tests\Feature;

use Tests\TestCase;
use Basics13\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_the_dashboard_shows_the_projects_summary(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ane Etxeberria']));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="dashboard-welcome"', false)
            ->assertSee(__('Hello, :name', ['name' => 'Ane Etxeberria']))
            ->assertSee('data-test="dashboard-due-this-month"', false)
            ->assertSee('data-test="dashboard-deadlines"', false)
            ->assertSee('data-test="dashboard-latest-comments"', false);
    }

    public function test_the_menu_links_to_the_account_resource(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('users.index'), false);
    }
}
