<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'contact_number' => '0000-000-0000',
                'date_of_birth' => '1990-01-01',
                'password' => User::DEFAULT_PASSWORD,
                'role' => User::ROLE_USER,
            ],
        );

        // Default role accounts for PawID
        User::firstOrCreate(
            ['email' => 'admin@pawid.test'],
            [
                'name' => 'PawID Admin',
                'contact_number' => '0000-000-0000',
                'date_of_birth' => '1990-01-01',
                'password' => User::DEFAULT_PASSWORD,
                'role' => User::ROLE_ADMIN,
            ],
        );
        User::firstOrCreate(
            ['email' => 'staff@pawid.test'],
            [
                'name' => 'OCV Staff',
                'contact_number' => '0000-000-0000',
                'date_of_birth' => '1990-01-01',
                'password' => User::DEFAULT_PASSWORD,
                'role' => User::ROLE_STAFF,
            ],
        );
    }
}
