<?php

namespace Tests\Feature\Projects;

use Tests\TestCase;
use App\Models\User;

class ProjectAccessTest extends TestCase
{
    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $this->actingAs(User::factory()->unverified()->make()->forceFill(['id' => 1]))
            ->get(route('projects.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_invalid_search_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->get(route('projects.index', ['search' => ['Ane']]))
            ->assertSessionHasErrors(['search']);
    }

    public function test_guests_cannot_access_the_project_trash(): void
    {
        $this->get(route('projects.trash.index'))->assertRedirect(route('login'));
    }
}
