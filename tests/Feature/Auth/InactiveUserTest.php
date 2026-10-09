<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_active_by_default_without_accepting_registration_input(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'New user',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'active' => false,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertTrue($user->active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_archived_users_cannot_log_in_with_a_valid_password(): void
    {
        $user = User::factory()->archived()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertInvalid(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_archived_users_cannot_begin_a_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->archived()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertInvalid(['email' => __('auth.failed')])
            ->assertSessionMissing('login.id');

        $this->assertGuest();
    }

    public function test_deactivation_during_a_two_factor_challenge_prevents_login(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->withTwoFactor()->create();
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
        $user->forceFill(['active' => false])->save();

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'recovery-code-1',
        ])->assertInvalid(['email' => __('auth.failed')])
            ->assertSessionMissing('login.id');

        $this->assertGuest();
    }

    public function test_active_users_can_complete_a_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->withTwoFactor()->create();
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'recovery-code-1',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_sessions_are_revoked_after_deactivation(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        User::whereKey($user->id)->update(['active' => false]);

        $this->withSession(['private-data' => 'secret'])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('private-data');

        $this->assertGuest();
    }

    public function test_archived_authenticated_json_requests_are_rejected(): void
    {
        $user = User::factory()->archived()->create();

        $this->actingAs($user)->getJson(route('dashboard'))
            ->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_deactivated_sessions_are_revoked_before_guest_route_redirects(): void
    {
        $user = User::factory()->archived()->create();

        $this->actingAs($user)->get(route('login'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_remembered_users_cannot_return_after_deactivation(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard('web');
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);
        $cookie = $response->getCookie($guard->getRecallerName(), decrypt: false);
        $this->assertNotNull($cookie);
        $user->forceFill(['active' => false])->save();
        $this->app['session']->flush();
        Auth::forgetGuards();

        $this->withUnencryptedCookies([$cookie->getName() => $cookie->getValue()])
            ->get(route('dashboard'))
            ->assertInvalid(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_archived_users_cannot_log_in_with_a_verified_passkey(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        $user = User::factory()->archived()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Test passkey',
            'credential_id' => 'aWQ',
            'credential' => [],
        ]);
        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')->once()->andReturn($passkey);
        $this->getJson('/passkeys/login/options')->assertOk();

        $this->postJson('/passkeys/login', $this->passkeyPayload())
            ->assertUnprocessable()
            ->assertInvalid(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_active_users_can_log_in_with_a_verified_passkey(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Test passkey',
            'credential_id' => 'aWQ',
            'credential' => [],
        ]);
        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')->once()->andReturn($passkey);
        $this->getJson('/passkeys/login/options')->assertOk();

        $this->postJson('/passkeys/login', $this->passkeyPayload())
            ->assertSuccessful();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function passkeyPayload(): array
    {
        return [
            'credential' => [
                'id' => 'aWQ',
                'rawId' => 'aWQ',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => rtrim(strtr(base64_encode(json_encode([
                        'type' => 'webauthn.get',
                        'challenge' => 'Y2hhbGxlbmdl',
                        'origin' => config('app.url'),
                    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '='),
                    'authenticatorData' => rtrim(strtr(base64_encode(
                        str_repeat("\0", 32)."\x01".pack('N', 0)
                    ), '+/', '-_'), '='),
                    'signature' => 'c2lnbmF0dXJl',
                    'userHandle' => null,
                ],
            ],
        ];
    }
}
