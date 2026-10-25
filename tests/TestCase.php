<?php

namespace Tests;

use LogicException;
use Laravel\Fortify\Features;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\DB;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Unit tests run without a database, as in the packages: a query there fails the test.
        if (str_starts_with(static::class, 'Tests\\Unit\\')) {
            DB::beforeExecuting(static function (string $query): never {
                throw new LogicException("Unit tests must not query the database, move the test to tests/Feature: {$query}");
            });
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
