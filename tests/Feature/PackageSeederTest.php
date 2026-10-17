<?php

namespace Tests\Feature;

use Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Support\Facades\DB;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PackageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_coordinates_the_package_seeders(): void
    {
        $this->assertSame('laravel_phpunit', DB::scalar('SELECT DATABASE()'));

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('CUM_customers', 101);
        $this->assertDatabaseCount('CUM_customer_types', 25);
        $this->assertDatabaseCount('CUM_customer_customer_type', 2);
        $this->assertDatabaseCount('PRO_projects', 306);
        $this->assertDatabaseCount('PRO_epics', 1203);
        $this->assertDatabaseCount('PRO_epic_comments', 3600);
        $this->assertSame(60, Customer::query()->where('name', 'like', 'Perf Customer %')->where('active', true)->count());
        $this->assertSame(25, Customer::query()->where('name', 'like', 'Perf Customer %')->where('active', false)->count());
        $this->assertSame(15, Customer::onlyTrashed()->where('name', 'like', 'Perf Customer %')->count());
        $this->assertDatabaseHas('CUM_customers', ['name' => 'Perf Customer 00001']);
        $this->assertDatabaseHas('CUM_customer_types', ['name_eu' => 'Perf Bezero Mota 00001']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Perf Project 00001']);
        $this->assertDatabaseHas('CUM_customers', ['name' => 'Customer holding customer types 0']);
        $this->assertDatabaseHas('CUM_customer_types', ['name_eu' => 'Bezero mota erabilia']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Project without epics 0']);
        $this->assertDatabaseHas('PRO_epics', ['name' => 'Epic without comments 0']);
    }
}
