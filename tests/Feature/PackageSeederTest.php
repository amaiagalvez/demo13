<?php

namespace Tests\Feature;

use Tests\TestCase;
use Basics13\Models\Role;
use Entities13\Models\Entity;
use Database\Seeders\AccessSeeder;
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

    public function test_access_is_seeded_and_seeding_again_duplicates_nothing(): void
    {
        $this->seed(AccessSeeder::class);
        $counts = $this->accessRowCounts();
        $this->seed(AccessSeeder::class);

        $this->assertSame($counts, $this->accessRowCounts());
        $this->assertEqualsCanonicalizing(
            ['basics13', 'entities13', 'files13', 'locations13', 'projects13'],
            DB::table('BAS_menus')->whereNull('parent_key')->pluck('key')->all(),
        );
        $this->assertTrue(Role::query()->where('slug', 'admin')->firstOrFail()->permissions()
            ->whereIn('slug', ['users.manage', 'entities.manage', 'projects.manage', 'towns.manage', 'files.manage'])->count() === 5);
    }

    /**
     * @return list<int>
     */
    private function accessRowCounts(): array
    {
        return array_map(
            static fn (string $table): int => DB::table($table)->count(),
            ['BAS_permissions', 'BAS_roles', 'BAS_permission_role', 'BAS_menus', 'BAS_menu_permission'],
        );
    }
}
