<?php

namespace Tests\Browser\Users;

use Tests\DuskTestCase;
use Basics13\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The leave button lives in the profile dropdown of the host shell, which only renders it while
 * the session carries an impersonation.
 */
class LeaveImpersonationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_the_impersonated_session_returns_to_the_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->browse(function (Browser $browser) use ($superAdmin, $target): void {
            $browser->loginAs($superAdmin)
                ->visit('/users')
                ->click("[data-test='user-impersonate-{$target->getKey()}']")
                ->waitFor('dialog[open] [data-test="user-confirm-submit"]')
                ->click('dialog[open] [data-test="user-confirm-submit"]')
                ->waitForLocation('/dashboard')
                ->click('[data-test="sidebar-menu-button"]')
                ->waitFor('[data-test="leave-impersonation-button"]')
                ->assertSee(__('basics13::messages.leave_impersonation'))
                ->click('[data-test="leave-impersonation-button"]')
                ->waitForLocation('/users')
                ->assertAuthenticatedAs($superAdmin)
                ->click('[data-test="sidebar-menu-button"]')
                ->waitFor('[data-test="desktop-logout-button"]')
                ->assertMissing('[data-test="leave-impersonation-button"]');
        });
    }
}
