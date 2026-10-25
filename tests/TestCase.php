<?php

namespace Tests;

use Laravel\Fortify\Features;
use Database\Seeders\AccessSeeder;
use Basics13\Support\Testing\ValidatesFormRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use ValidatesFormRequests;

    /**
     * `migrate:fresh` seeds the roles, permissions and menus once per run, as `db:seed` does in
     * the application.
     */
    protected bool $seed = true;

    /** @var class-string */
    protected string $seeder = AccessSeeder::class;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
