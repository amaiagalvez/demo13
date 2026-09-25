<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;

class ProfilePageTest extends TestCase
{
    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('profile.edit'))
            ->assertOk();
    }
}
