<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use Livewire\Livewire;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* @chisel-2fa */
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        /* @end-chisel-2fa */
        /* @chisel-passkeys */
        Features::passkeys([
            'confirmPassword' => true,
        ]);
        /* @end-chisel-passkeys */
    }

    public function test_security_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'));

        $response->assertOk();

        /* @chisel-passkeys */
        $response->assertSee(__('Passkeys'))
            ->assertSee('aria-labelledby="delete-passkey-modal-heading"', false)
            ->assertSee('id="delete-passkey-modal-heading"', false);
        $response->assertSee(__('No passkeys yet'));
        /* @end-chisel-passkeys */
        /* @chisel-2fa */
        $response->assertSee(__('Two-factor authentication'))
            ->assertSee('aria-labelledby="two-factor-setup-modal-heading"', false)
            ->assertSee('id="two-factor-setup-modal-heading"', false);
        $response->assertSee(__('Enable 2FA'));
        /* @end-chisel-2fa */
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee(__('Update password'))
            ->assertDontSee(__('Manage your passkeys for passwordless sign-in'))
            ->assertDontSee(__('Add a passkey to sign in without a password'))
            ->assertDontSee(__('Two-factor authentication'));
    }

    public function test_two_factor_authentication_disabled_when_confirmation_abandoned_between_requests(): void
    {
        /* @chisel-2fa */
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::settings.security');

        $component->assertSet('twoFactorEnabled', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
        /* @end-chisel-2fa */
    }

    /* @chisel-2fa */
    public function test_clients_cannot_change_two_factor_management_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('pages::settings.security')
            ->set('canManageTwoFactor', false);
    }

    public function test_clients_cannot_change_two_factor_enabled_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('pages::settings.security')
            ->set('twoFactorEnabled', true);
    }

    public function test_clients_cannot_change_two_factor_confirmation_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('pages::settings.security')
            ->set('requiresConfirmation', false);
    }
    /* @end-chisel-2fa */

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.security')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasErrors(['current_password']);
    }

    /* @chisel-passkeys */
    public function test_user_cannot_confirm_deletion_of_another_users_passkey(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $passkey = $otherUser->passkeys()->create([
            'name' => 'Another user passkey',
            'credential_id' => 'aWQ',
            'credential' => [],
        ]);

        $this->actingAs($user);

        Livewire::test('pages::settings.security')
            ->call('confirmDelete', $passkey->id)
            ->assertNotFound();

        $this->assertModelExists($passkey);
    }
    /* @end-chisel-passkeys */

    /* @chisel-password-confirmation */
    public function test_expired_password_confirmation_blocks_livewire_security_updates(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('wire:snapshot="', false);

        $html = $response->getContent();
        if (! is_string($html)) {
            self::fail('The security page response must contain HTML.');
        }

        $matchCount = preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);

        if ($matchCount !== 1) {
            self::fail('The security page must contain a Livewire snapshot.');
        }

        $encodedSnapshot = $matches[1];
        $snapshot = html_entity_decode($encodedSnapshot, ENT_QUOTES | ENT_HTML5);
        $snapshotData = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($snapshotData)) {
            self::fail('The Livewire snapshot must decode to an array.');
        }

        $this->assertSame('settings/security', data_get($snapshotData, 'memo.path'));

        $expiredAt = now()->subSeconds(10801)->timestamp;
        $this->withSession(['auth.password_confirmed_at' => $expiredAt]);
        $this->assertSame($expiredAt, session('auth.password_confirmed_at'));
        $this->getJson(route('security.edit'))->assertStatus(423);

        $updateResponse = $this->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [[
                    'method' => 'updatePassword',
                    'params' => [],
                    'path' => '',
                ]],
            ]],
        ], [
            'Accept' => 'application/json',
            'X-Livewire' => 'true',
        ]);

        $updateResponse->assertStatus(423);
    }
    /* @end-chisel-password-confirmation */
}
