<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertSeeText(__('Authentication code'))
            ->assertSeeText(__('Recovery code'));
    }
}
