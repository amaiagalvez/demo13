<?php

namespace Tests\Browser\Settings;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

class LogViewerAccessTest extends DuskTestCase
{
    public function test_guests_are_redirected_to_login_from_the_log_viewer(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/log-viewer')
                ->assertPathIs('/login');
        });
    }
}
