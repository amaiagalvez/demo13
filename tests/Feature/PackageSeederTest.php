<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Customers13\Models\Customer;
use Projects13\Models\EpicComment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\DevelopProjectSeeder;
use Projects13\Database\Seeders\EdgeCaseProjectSeeder;
use Customers13\Database\Seeders\DevelopCustomerSeeder;
use Customers13\Database\Seeders\EdgeCaseCustomerSeeder;

class PackageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_seeders_create_the_package_resource_hierarchy(): void
    {
        User::factory()->count(5)->create();

        app(DevelopCustomerSeeder::class)->run(1);
        app(DevelopProjectSeeder::class)->run(1);

        $this->assertDatabaseCount('CUM_customers', 1);
        $this->assertDatabaseCount('PRO_projects', 3);
        $this->assertDatabaseCount('PRO_epics', 12);
        $this->assertDatabaseCount('PRO_epic_comments', 36);
        $this->assertSame(1, Project::query()->distinct()->count('customer_id'));
        $this->assertSame(12, Epic::query()->whereHas('project', fn ($query) => $query
            ->where('name', 'like', 'Perf Project %'))->count());
        $this->assertSame(36, EpicComment::query()->count());
    }

    public function test_edge_case_seeders_create_records_for_each_resource_package(): void
    {
        $this->seed(EdgeCaseCustomerSeeder::class);
        $this->seed(EdgeCaseProjectSeeder::class);

        $this->assertDatabaseCount('CUM_customers', 11);
        $this->assertSame(5, Customer::query()->where('name', 'like', 'Customer without projects %')->count());
        $this->assertSame(3, Project::query()->where('name', 'like', 'Project without epics %')->count());
        $this->assertSame(3, Epic::query()->where('name', 'like', 'Epic without comments %')->count());
        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    public function test_database_seeder_coordinates_the_package_seeders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('CUM_customers', 111);
        $this->assertDatabaseCount('PRO_projects', 306);
        $this->assertDatabaseCount('PRO_epics', 1203);
        $this->assertDatabaseCount('PRO_epic_comments', 3600);
        $this->assertSame(60, Customer::query()->where('name', 'like', 'Perf Customer %')->where('active', true)->count());
        $this->assertSame(25, Customer::query()->where('name', 'like', 'Perf Customer %')->where('active', false)->count());
        $this->assertSame(15, Customer::onlyTrashed()->where('name', 'like', 'Perf Customer %')->count());
        $this->assertDatabaseHas('CUM_customers', ['name' => 'Perf Customer 00001']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Perf Project 00001']);
        $this->assertDatabaseHas('CUM_customers', ['name' => 'Customer without projects 0']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Project without epics 0']);
        $this->assertDatabaseHas('PRO_epics', ['name' => 'Epic without comments 0']);
    }
}
