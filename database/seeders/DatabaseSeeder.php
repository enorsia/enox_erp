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

        User::factory()->create([
            'name' => 'SUPER ADMIN',
            'email' => config('enoxsuite.super_admin_username') ?? 'admin@example.com',
            'password' => bcrypt(config('enoxsuite.super_admin_password')),
        ]);
    }
}
