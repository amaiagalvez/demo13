<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\BasicsDevelopSeeder;
use Projects13\Database\Seeders\ProjectsDevelopSeeder;
use entities13\Database\Seeders\EntitiesDevelopSeeder;

class DevelopDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment() !== 'production') {

            $this->call([
                BasicsDevelopSeeder::class,
                EntitiesDevelopSeeder::class,
                ProjectsDevelopSeeder::class,
            ]);
        }
    }
}
