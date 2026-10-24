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

    public function test_the_dashboard_only_greets_the_user(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ane Etxeberria']));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Hello, :name', ['name' => 'Ane Etxeberria']))
            ->assertSee(route('work.dashboard'), false)
            ->assertDontSee('data-test="dashboard-welcome"', false);
    }

    public function test_the_work_dashboard_shows_the_projects_summary(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ane Etxeberria']));

        $this->get(route('work.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Hello, :name', ['name' => 'Ane Etxeberria']))
            ->assertSee('data-test="dashboard-welcome"', false)
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
            ->assertSee(route('users.permissions-summary.index'), false)
            ->assertSee(route('sessions.index'), false)
            ->assertSee(route('configuration.index'), false);
    }

    public function test_the_session_list_alone_shows_the_administration_group(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Administration'))
            ->assertSee('data-test="nav-admin-dashboard"', false)
            ->assertSee('data-test="nav-sessions"', false)
            ->assertDontSee('data-test="nav-configuration"', false)
            ->assertDontSee('data-test="nav-users"', false);
    }

    public function test_the_configuration_alone_shows_the_administration_group(): void
    {
        $this->actingAs(User::factory()->withPermissions(['settings.view'])->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Administration'))
            ->assertSee('data-test="nav-configuration"', false)
            ->assertDontSee('data-test="nav-sessions"', false)
            ->assertDontSee('data-test="nav-admin-dashboard"', false);
    }

    public function test_the_administration_dashboard_marks_only_its_own_entry(): void
    {
        $this->actingAs(User::factory()->withPermissions(['sessions.view'])->create());

        $html = $this->get(route('sessions.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a [^>]*data-current="data-current"[^>]*data-test="nav-admin-dashboard"/', (string) $html);
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*data-current="data-current"[^>]*data-test="nav-sessions"/', (string) $html);
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

    public function test_the_super_admin_menu_keeps_every_entry_the_browser_tests_use(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $response = $this->get(route('dashboard'))->assertOk();

        foreach (['work-dashboard', 'admin-dashboard', 'users', 'user-types', 'roles', 'permissions', 'permissions-summary', 'sessions', 'configuration'] as $key) {
            $response->assertSee('data-test="nav-'.$key.'"', false);
        }
    }

    public function test_a_delegated_manager_sees_only_the_pages_they_may_open(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view_own'])->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="nav-work-dashboard"', false)
            ->assertSee('data-test="nav-users"', false)
            ->assertDontSee('data-test="nav-projects"', false)
            ->assertDontSee('data-test="nav-roles"', false);
    }
}
