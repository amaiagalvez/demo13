<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\BasicsAccessSeeder;
use Entities13\Database\Seeders\EntitiesAccessSeeder;
use Projects13\Database\Seeders\ProjectsAccessSeeder;
use Locations13\Database\Seeders\LocationsAccessSeeder;

/**
 * The roles, permissions and menus of every installed package. basics13 goes first: it creates
 * `admin`, which the domains then grant their permissions to.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BasicsAccessSeeder::class,
            EntitiesAccessSeeder::class,
            ProjectsAccessSeeder::class,
            LocationsAccessSeeder::class,
        ]);
    }
}
