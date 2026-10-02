<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk();
        $appName = config('app.name');

        self::assertIsString($appName);
        $response->assertSeeText(__('Profile settings').' - '.$appName);
    }
}
