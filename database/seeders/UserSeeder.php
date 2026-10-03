<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'info@amaia.eus'],
            [
                'name' => 'Amaia',
                'password' => '123456',
                'email_verified_at' => now(),
            ],
        );
    }
}
