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

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->waitFor('[data-test="update-profile-button"]')
                ->assertScript(
                    'document.querySelector(\'[data-test="update-profile-button"]\').disabled',
                    true,
                )
                ->clear('input[wire\\:model="name"]')
                ->type('input[wire\\:model="name"]', 'Updated profile name')
                ->assertScript(
                    'document.querySelector(\'[data-test="update-profile-button"]\').disabled',
                    false,
                )
                ->click('a[href$="/projects"]')
                ->assertDialogOpened(__('You have unsaved changes. Leave without saving?'))
                ->dismissDialog()
                ->assertPathIs('/settings/profile')
                ->click('[data-test="update-profile-button"]')
                ->waitUntil(
                    'document.body.innerText.includes("Profile updated.")',
                    10,
                )
                ->assertScript(
                    'document.querySelector(\'[data-test="update-profile-button"]\').disabled',
                    true,
                );
        });

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated profile name',
        ]);
    }
}
