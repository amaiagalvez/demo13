<?php

namespace Tests\Browser\Settings;

use App\Models\User;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProfileDirtyFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_settings_save_buttons_only_enable_after_changes_and_navigation_warns(): void
    {
        $user = User::factory()->create();
        $savedName = 'Updated profile name';

        $this->browse(function (Browser $browser) use ($user, $savedName): void {
            $nameInput = 'input[wire\\:model="name"]';
            $saveButtonDisabled = 'document.querySelector(\'[data-test="update-profile-button"]\').disabled';

            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->waitFor('[data-test="update-profile-button"]')
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->clear($nameInput)
                ->type($nameInput, $savedName)
                ->assertScript(
                    $saveButtonDisabled,
                    false,
                )
                ->click('a[href$="/projects"]')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->dismissDialog()
                ->assertPathIs('/settings/profile')
                ->click('[data-test="update-profile-button"]')
                ->waitUntil(
                    'document.body.innerText.includes('.json_encode(__('Profile updated.')).')',
                    10,
                )
                ->waitUntil(
                    $saveButtonDisabled,
                    10,
                )
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->clear($nameInput)
                ->type($nameInput, 'Another profile name')
                ->assertScript(
                    $saveButtonDisabled,
                    false,
                )
                ->clear($nameInput)
                ->type($nameInput, $savedName)
                ->assertScript(
                    $saveButtonDisabled,
                    true,
                )
                ->click('a[href$="/projects"]')
                ->waitUntil('window.location.pathname === "/projects"', 10)
                ->assertPathIs('/projects');
        });

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $savedName,
        ]);
    }
}
