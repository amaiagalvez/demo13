<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\BasicsDevelopSeeder;
use Entities13\Database\Seeders\EntitiesDevelopSeeder;
use Projects13\Database\Seeders\ProjectsDevelopSeeder;

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
