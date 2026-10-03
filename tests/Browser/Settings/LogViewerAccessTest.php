<?php

namespace Tests\Browser\Settings;

use App\Models\User;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogViewerAccessTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Regression: the API middleware order decides whether the browser session cookie is honoured.
     * The entries are fetched over HTTP, so only a real browser proves the page is not left with a
     * "Request failed with status code 401" banner.
     */
    public function test_allowed_user_sees_the_log_entries_instead_of_an_unauthenticated_error(): void
    {
        // The browser talks to a separate server process, so the allowlist must come from the
        // environment instead of from this test process configuration.
        $allowedEmails = array_filter(explode(',', (string) env('LOG_VIEWER_EMAILS')));

        $this->assertNotEmpty($allowedEmails, 'LOG_VIEWER_EMAILS must list at least one account.');

        $user = User::factory()->create(['email' => trim($allowedEmails[0])]);
        Log::error('Log viewer browser regression entry');

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/log-viewer')
                ->waitUntilMissingText('Request failed with status code 401', 10)
                ->assertDontSee('Request failed with status code 401')
                // The file list is only rendered when the authenticated API answers with the files.
                ->waitForText('Log files on', 10)
                ->assertSee('Log files on')
                ->assertSee('laravel-');
        });
    }
}
