<?php

namespace Database\Seeders;

use App\Models\MonthlyBudget;
use Illuminate\Database\Seeder;

class MonthlyBudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MonthlyBudget::insert([
            [
                'sale_platform_id' => 1,
                'year' => 2025,
                'month' => 1,
                'budget' => 15000.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_platform_id' => 10,
                'year' => 2025,
                'month' => 1,
                'budget' => 6000.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_platform_id' => 11,
                'year' => 2025,
                'month' => 1,
                'budget' => 4000.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_platform_id' => 3,
                'year' => 2025,
                'month' => 1,
                'budget' => 2500.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_platform_id' => 20,
                'year' => 2025,
                'month' => 1,
                'budget' => 1500.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sale_platform_id' => 21,
                'year' => 2025,
                'month' => 1,
                'budget' => 1000.00,
                'currency' => 'GBP',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
