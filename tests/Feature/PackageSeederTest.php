<?php

namespace Tests\Feature;

use Tests\TestCase;
use entities13\Models\Entity;
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

        $this->assertDatabaseCount('ENT_entities', 101);
        $this->assertDatabaseCount('ENT_entity_types', 25);
        $this->assertDatabaseCount('ENT_entity_entity_type', 2);
        $this->assertDatabaseCount('PRO_projects', 306);
        $this->assertDatabaseCount('PRO_epics', 1203);
        $this->assertDatabaseCount('PRO_epic_comments', 3600);
        $this->assertSame(60, Entity::query()->where('name', 'like', 'Perf Entity %')->where('active', true)->count());
        $this->assertSame(25, Entity::query()->where('name', 'like', 'Perf Entity %')->where('active', false)->count());
        $this->assertSame(15, Entity::onlyTrashed()->where('name', 'like', 'Perf Entity %')->count());
        $this->assertDatabaseHas('ENT_entities', ['name' => 'Perf Entity 00001']);
        $this->assertDatabaseHas('ENT_entity_types', ['name_eu' => 'Perf Bezero Mota 00001']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Perf Project 00001']);
        $this->assertDatabaseHas('ENT_entities', ['name' => 'Entity holding entity types 0']);
        $this->assertDatabaseHas('ENT_entity_types', ['name_eu' => 'Bezero mota erabilia']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Project without epics 0']);
        $this->assertDatabaseHas('PRO_epics', ['name' => 'Epic without comments 0']);
    }
}
