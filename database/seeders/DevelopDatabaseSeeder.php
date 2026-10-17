<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\BasicsDevelopSeeder;
use Projects13\Database\Seeders\ProjectsDevelopSeeder;
use Customers13\Database\Seeders\CustomersDevelopSeeder;

class DevelopDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment() !== 'production') {

            $this->call([
                BasicsDevelopSeeder::class,
                CustomersDevelopSeeder::class,
                ProjectsDevelopSeeder::class,
            ]);
        }
    }
}
