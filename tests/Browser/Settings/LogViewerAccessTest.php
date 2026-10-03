<?php

namespace Tests\Browser\Settings;

use Tests\DuskTestCase;
use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogViewerAccessTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Regression: the package API middleware order decides whether the browser session cookie is
     * honoured, and only a real browser proves it, since the UI fetches entries over HTTP.
     */
    public function test_allowed_user_sees_the_log_entries_instead_of_an_unauthenticated_error(): void
    {
        config()->set('log-viewer.allowed_emails', ['ops@example.test']);
        $user = User::factory()->create(['email' => 'ops@example.test']);
        User::factory()->create(['email' => 'someone@example.test']);
        Log::error('Log viewer browser regression entry');

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/log-viewer')
                ->waitFor('input[type="search"]')
                ->assertDontSee('Request failed with status code 401')
                ->assertSee('Log viewer browser regression entry');
        });
    }
}
