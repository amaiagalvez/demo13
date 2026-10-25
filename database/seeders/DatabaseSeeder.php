<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // To load in production
        $this->call(AccessSeeder::class);
        $this->call(EmailTemplateSeeder::class);

        // To load in local
        if (app()->environment() !== 'production') {
            $this->call(DevelopDatabaseSeeder::class);
        }
    }
}
