<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Laravel\Fortify\Features;

class AuthPagesTest extends TestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        $user = User::factory()->unverified()->make();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_password_confirmation_screen_can_be_rendered(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('password.confirm'))
            ->assertOk();
    }

    public function test_password_reset_link_screen_can_be_rendered(): void
    {
        $this->skipUnlessFortifyHas(Features::resetPasswords());

        $this->get(route('password.request'))->assertOk();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->get(route('register'))->assertOk();
    }

    public function test_two_factor_challenge_redirects_guests_to_login(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $this->get(route('two-factor.login'))->assertRedirect(route('login'));
    }
}
