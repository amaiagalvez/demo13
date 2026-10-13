<?php

namespace Tests;

use Illuminate\Foundation\Application;

abstract class DuskTestCase extends \Basics13\Support\Testing\DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('laravel_test', config('database.connections.mysql.database'));
    }

    protected function getEnvironmentSetUp(Application $app): void
    {
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'laravel_test');
    }
}
