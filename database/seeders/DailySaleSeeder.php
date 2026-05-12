<?php

namespace Database\Seeders;

use App\Models\DailySale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DailySaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sales = [
            // Enorsia sub-channels (leaf nodes — raw data)
            ['sale_platform_id' => 10, 'date' => '2025-01-15', 'spent' => 1200.00, 'sales' => 4800.00, 'number_of_orders' => 95,  'number_of_quantities' => 120, 'number_of_male_orders' => 40, 'number_of_female_orders' => 45, 'number_of_kids_orders' => 10, 'number_of_male_quantities' => 52, 'number_of_female_quantities' => 56, 'number_of_kids_quantities' => 12], // Google
            ['sale_platform_id' => 11, 'date' => '2025-01-15', 'spent' => 850.00,  'sales' => 3200.00, 'number_of_orders' => 70,  'number_of_quantities' => 88,  'number_of_male_orders' => 28, 'number_of_female_orders' => 32, 'number_of_kids_orders' => 10, 'number_of_male_quantities' => 36, 'number_of_female_quantities' => 40, 'number_of_kids_quantities' => 12], // Meta
            ['sale_platform_id' => 12, 'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 1500.00, 'number_of_orders' => 40,  'number_of_quantities' => 50,  'number_of_male_orders' => 15, 'number_of_female_orders' => 20, 'number_of_kids_orders' => 5,  'number_of_male_quantities' => 19, 'number_of_female_quantities' => 25, 'number_of_kids_quantities' => 6],  // Klaviyo
            ['sale_platform_id' => 13, 'date' => '2025-01-15', 'spent' => 300.00,  'sales' => 900.00,  'number_of_orders' => 20,  'number_of_quantities' => 25,  'number_of_male_orders' => 8,  'number_of_female_orders' => 10, 'number_of_kids_orders' => 2,  'number_of_male_quantities' => 10, 'number_of_female_quantities' => 13, 'number_of_kids_quantities' => 2],  // Influencer
            ['sale_platform_id' => 14, 'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 600.00,  'number_of_orders' => 15,  'number_of_quantities' => 18,  'number_of_male_orders' => 6,  'number_of_female_orders' => 7,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 7,  'number_of_female_quantities' => 9,  'number_of_kids_quantities' => 2],  // SEO
            ['sale_platform_id' => 15, 'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 400.00,  'number_of_orders' => 10,  'number_of_quantities' => 12,  'number_of_male_orders' => 4,  'number_of_female_orders' => 5,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 5,  'number_of_female_quantities' => 6,  'number_of_kids_quantities' => 1],  // Awin
            ['sale_platform_id' => 16, 'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 200.00,  'number_of_orders' => 5,   'number_of_quantities' => 6,   'number_of_male_orders' => 2,  'number_of_female_orders' => 2,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 2,  'number_of_female_quantities' => 3,  'number_of_kids_quantities' => 1],  // Others

            // Debenhams (leaf node)
            ['sale_platform_id' => 2,  'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 2100.00, 'number_of_orders' => 55,  'number_of_quantities' => 68,  'number_of_male_orders' => 20, 'number_of_female_orders' => 25, 'number_of_kids_orders' => 10, 'number_of_male_quantities' => 26, 'number_of_female_quantities' => 30, 'number_of_kids_quantities' => 12],

            // Amazon UK (leaf node)
            ['sale_platform_id' => 20, 'date' => '2025-01-15', 'spent' => 500.00,  'sales' => 3800.00, 'number_of_orders' => 90,  'number_of_quantities' => 110, 'number_of_male_orders' => 35, 'number_of_female_orders' => 42, 'number_of_kids_orders' => 13, 'number_of_male_quantities' => 44, 'number_of_female_quantities' => 52, 'number_of_kids_quantities' => 14],

            // Amazon EU countries (leaf nodes)
            ['sale_platform_id' => 30, 'date' => '2025-01-15', 'spent' => 120.00,  'sales' => 980.00,  'number_of_orders' => 22,  'number_of_quantities' => 28,  'number_of_male_orders' => 9,  'number_of_female_orders' => 10, 'number_of_kids_orders' => 3,  'number_of_male_quantities' => 11, 'number_of_female_quantities' => 14, 'number_of_kids_quantities' => 3],  // Germany
            ['sale_platform_id' => 31, 'date' => '2025-01-15', 'spent' => 80.00,   'sales' => 620.00,  'number_of_orders' => 14,  'number_of_quantities' => 18,  'number_of_male_orders' => 5,  'number_of_female_orders' => 7,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 6,  'number_of_female_quantities' => 9,  'number_of_kids_quantities' => 3],  // France
            ['sale_platform_id' => 32, 'date' => '2025-01-15', 'spent' => 60.00,   'sales' => 480.00,  'number_of_orders' => 11,  'number_of_quantities' => 14,  'number_of_male_orders' => 4,  'number_of_female_orders' => 5,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 5,  'number_of_female_quantities' => 7,  'number_of_kids_quantities' => 2],  // Italy
            ['sale_platform_id' => 33, 'date' => '2025-01-15', 'spent' => 55.00,   'sales' => 410.00,  'number_of_orders' => 10,  'number_of_quantities' => 13,  'number_of_male_orders' => 4,  'number_of_female_orders' => 4,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 5,  'number_of_female_quantities' => 6,  'number_of_kids_quantities' => 2],  // Spain
            ['sale_platform_id' => 34, 'date' => '2025-01-15', 'spent' => 40.00,   'sales' => 290.00,  'number_of_orders' => 7,   'number_of_quantities' => 9,   'number_of_male_orders' => 3,  'number_of_female_orders' => 3,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 4,  'number_of_female_quantities' => 4,  'number_of_kids_quantities' => 1],  // Netherlands
            ['sale_platform_id' => 35, 'date' => '2025-01-15', 'spent' => 30.00,   'sales' => 210.00,  'number_of_orders' => 5,   'number_of_quantities' => 6,   'number_of_male_orders' => 2,  'number_of_female_orders' => 2,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 2,  'number_of_female_quantities' => 3,  'number_of_kids_quantities' => 1],  // Poland
            ['sale_platform_id' => 36, 'date' => '2025-01-15', 'spent' => 25.00,   'sales' => 180.00,  'number_of_orders' => 4,   'number_of_quantities' => 5,   'number_of_male_orders' => 2,  'number_of_female_orders' => 1,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 2,  'number_of_female_quantities' => 2,  'number_of_kids_quantities' => 1],  // Sweden
            ['sale_platform_id' => 37, 'date' => '2025-01-15', 'spent' => 20.00,   'sales' => 150.00,  'number_of_orders' => 4,   'number_of_quantities' => 5,   'number_of_male_orders' => 1,  'number_of_female_orders' => 2,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 2,  'number_of_female_quantities' => 2,  'number_of_kids_quantities' => 1],  // Belgium
            ['sale_platform_id' => 38, 'date' => '2025-01-15', 'spent' => 15.00,   'sales' => 110.00,  'number_of_orders' => 3,   'number_of_quantities' => 4,   'number_of_male_orders' => 1,  'number_of_female_orders' => 1,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 1,  'number_of_female_quantities' => 2,  'number_of_kids_quantities' => 1],  // Ireland

            // Other top-level channels
            ['sale_platform_id' => 4,  'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 750.00,  'number_of_orders' => 18,  'number_of_quantities' => 22,  'number_of_male_orders' => 7,  'number_of_female_orders' => 9,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 9,  'number_of_female_quantities' => 11, 'number_of_kids_quantities' => 2],  // Spartoo
            ['sale_platform_id' => 5,  'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 320.00,  'number_of_orders' => 8,   'number_of_quantities' => 10,  'number_of_male_orders' => 3,  'number_of_female_orders' => 4,  'number_of_kids_orders' => 1,  'number_of_male_quantities' => 4,  'number_of_female_quantities' => 5,  'number_of_kids_quantities' => 1],  // Temu
            ['sale_platform_id' => 6,  'date' => '2025-01-15', 'spent' => 0.00,    'sales' => 540.00,  'number_of_orders' => 13,  'number_of_quantities' => 16,  'number_of_male_orders' => 5,  'number_of_female_orders' => 6,  'number_of_kids_orders' => 2,  'number_of_male_quantities' => 6,  'number_of_female_quantities' => 8,  'number_of_kids_quantities' => 2],  // Rackhams
        ];

        foreach ($sales as $sale) {
            DailySale::updateOrCreate(
                ['sale_platform_id' => $sale['sale_platform_id'], 'date' => $sale['date']],
                $sale
            );
        }
    }
}

