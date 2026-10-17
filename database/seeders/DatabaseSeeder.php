<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // To load in production
        //

        // To load in local
        if (app()->environment() !== 'production') {
            $this->call(DevelopDatabaseSeeder::class);
        }
    }
}
