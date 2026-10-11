<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Projects13\Database\Seeders\DevelopProjectSeeder;
use Projects13\Database\Seeders\EdgeCaseProjectSeeder;
use Customers13\Database\Seeders\DevelopCustomerSeeder;
use Customers13\Database\Seeders\EdgeCaseCustomerSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment() !== 'production') {
            $this->call(UserSeeder::class);
            User::factory()->count(5)->create();

            $this->call([
                DevelopCustomerSeeder::class,
                DevelopProjectSeeder::class,
                EdgeCaseCustomerSeeder::class,
                EdgeCaseProjectSeeder::class,
            ]);
        }
    }
}
