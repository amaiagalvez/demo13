<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment() !== 'production') {
            $this->call([
                UserSeeder::class,
                DevelopTableSeeder::class,
                EdgeCaseSeeder::class,
            ]);
        }
    }
}
