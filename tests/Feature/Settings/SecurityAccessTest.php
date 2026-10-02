<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
    }

    /* @chisel-password-confirmation */
    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }
    /* @end-chisel-password-confirmation */
}
