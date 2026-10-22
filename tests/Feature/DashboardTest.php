<?php

namespace Tests\Feature;

use Tests\TestCase;
use Basics13\Models\User;
use Basics13\Enums\Language;
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

    public function test_the_dashboard_is_shown_in_the_user_language(): void
    {
        $this->actingAs(User::factory()->language(Language::FR)->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="fr"', false);
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

    public function test_the_menu_links_to_the_administration_pages_the_user_may_open(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="nav-users"', false)
            ->assertSee(route('users.index'), false)
            ->assertSee(route('roles.index'), false)
            ->assertSee(route('permissions.index'), false)
            ->assertSee(route('users.permissions-summary.index'), false);
    }

    public function test_the_menu_hides_what_the_user_has_no_permission_for(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view'])->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="nav-users"', false)
            ->assertDontSee('data-test="nav-roles"', false)
            ->assertDontSee('data-test="nav-permissions"', false)
            ->assertDontSee('data-test="nav-permissions-summary"', false);

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="nav-users"', false)
            ->assertDontSee(__('Administration'));
    }
}
