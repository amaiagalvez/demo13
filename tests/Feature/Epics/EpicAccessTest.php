<?php

namespace Tests\Feature\Epics;

use Tests\TestCase;
use App\Models\User;

class EpicAccessTest extends TestCase
{
    public function test_unverified_users_are_redirected_to_email_verification(): void
    {
        $this->actingAs(User::factory()->unverified()->make()->forceFill(['id' => 1]))
            ->get(route('epics.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_invalid_search_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->make()->forceFill(['id' => 1]))
            ->get(route('epics.index', ['search' => ['Ane']]))
            ->assertSessionHasErrors(['search']);
    }

    public function test_guests_cannot_access_the_epic_trash(): void
    {
        $this->get(route('epics.trash.index'))->assertRedirect(route('login'));
    }
}
