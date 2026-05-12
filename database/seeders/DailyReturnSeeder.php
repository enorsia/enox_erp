<?php

namespace Database\Seeders;

use App\Models\DailyReturn;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DailyReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $returns = [
            // Google (sale_platform_id=10) — 5 total returns across 3 reasons
            ['sale_platform_id' => 10, 'return_reason_type_id' => 1, 'date' => '2025-01-15', 'number_of_returns' => 2, 'number_of_return_quantities' => 2, 'number_of_male_returns' => 1, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 1, 'number_of_kids_return_quantities' => 0], // Wrong size
            ['sale_platform_id' => 10, 'return_reason_type_id' => 2, 'date' => '2025-01-15', 'number_of_returns' => 2, 'number_of_return_quantities' => 3, 'number_of_male_returns' => 1, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 2, 'number_of_kids_return_quantities' => 0], // Defective
            ['sale_platform_id' => 10, 'return_reason_type_id' => 4, 'date' => '2025-01-15', 'number_of_returns' => 1, 'number_of_return_quantities' => 1, 'number_of_male_returns' => 0, 'number_of_female_returns' => 0, 'number_of_kids_returns' => 1, 'number_of_male_return_quantities' => 0, 'number_of_female_return_quantities' => 0, 'number_of_kids_return_quantities' => 1], // Changed mind

            // Meta (sale_platform_id=11) — 3 total returns across 2 reasons
            ['sale_platform_id' => 11, 'return_reason_type_id' => 1, 'date' => '2025-01-15', 'number_of_returns' => 1, 'number_of_return_quantities' => 1, 'number_of_male_returns' => 0, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 0, 'number_of_female_return_quantities' => 1, 'number_of_kids_return_quantities' => 0], // Wrong size
            ['sale_platform_id' => 11, 'return_reason_type_id' => 3, 'date' => '2025-01-15', 'number_of_returns' => 2, 'number_of_return_quantities' => 3, 'number_of_male_returns' => 1, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 2, 'number_of_kids_return_quantities' => 0], // Not as described

            // Amazon UK (sale_platform_id=20) — 8 total returns across 4 reasons
            ['sale_platform_id' => 20, 'return_reason_type_id' => 1, 'date' => '2025-01-15', 'number_of_returns' => 3, 'number_of_return_quantities' => 3, 'number_of_male_returns' => 1, 'number_of_female_returns' => 2, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 2, 'number_of_kids_return_quantities' => 0], // Wrong size
            ['sale_platform_id' => 20, 'return_reason_type_id' => 2, 'date' => '2025-01-15', 'number_of_returns' => 2, 'number_of_return_quantities' => 2, 'number_of_male_returns' => 1, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 1, 'number_of_kids_return_quantities' => 0], // Defective
            ['sale_platform_id' => 20, 'return_reason_type_id' => 6, 'date' => '2025-01-15', 'number_of_returns' => 2, 'number_of_return_quantities' => 2, 'number_of_male_returns' => 1, 'number_of_female_returns' => 0, 'number_of_kids_returns' => 1, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 0, 'number_of_kids_return_quantities' => 1], // Wrong item sent
            ['sale_platform_id' => 20, 'return_reason_type_id' => 7, 'date' => '2025-01-15', 'number_of_returns' => 1, 'number_of_return_quantities' => 2, 'number_of_male_returns' => 0, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 0, 'number_of_female_return_quantities' => 2, 'number_of_kids_return_quantities' => 0], // Other

            // Amazon Germany (sale_platform_id=30) — 2 total returns across 2 reasons
            ['sale_platform_id' => 30, 'return_reason_type_id' => 1, 'date' => '2025-01-15', 'number_of_returns' => 1, 'number_of_return_quantities' => 1, 'number_of_male_returns' => 1, 'number_of_female_returns' => 0, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 1, 'number_of_female_return_quantities' => 0, 'number_of_kids_return_quantities' => 0], // Wrong size
            ['sale_platform_id' => 30, 'return_reason_type_id' => 2, 'date' => '2025-01-15', 'number_of_returns' => 1, 'number_of_return_quantities' => 1, 'number_of_male_returns' => 0, 'number_of_female_returns' => 1, 'number_of_kids_returns' => 0, 'number_of_male_return_quantities' => 0, 'number_of_female_return_quantities' => 1, 'number_of_kids_return_quantities' => 0], // Defective
        ];

        foreach ($returns as $return) {
            DailyReturn::updateOrCreate(
                [
                    'sale_platform_id'     => $return['sale_platform_id'],
                    'return_reason_type_id'=> $return['return_reason_type_id'],
                    'date'                 => $return['date'],
                ],
                $return
            );
        }
    }
}

