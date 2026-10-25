<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_displays_its_localized_title(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText(__('Welcome'));
    }
}
