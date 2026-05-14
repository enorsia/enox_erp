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
        // Return reason types mapping
        $returnReasons = [
            ['id' => 1, 'name' => 'Wrong size'],
            ['id' => 2, 'name' => 'Defective product'],
            ['id' => 3, 'name' => 'Not as described'],
            ['id' => 4, 'name' => 'Changed mind'],
            ['id' => 5, 'name' => 'Late delivery'],
            ['id' => 6, 'name' => 'Wrong item sent'],
            ['id' => 7, 'name' => 'Other'],
        ];

        $platforms = [
            // Enorsia sub-channels
            ['id' => 10, 'name' => 'Google', 'returnRate' => 0.045],
            ['id' => 11, 'name' => 'Meta', 'returnRate' => 0.038],
            ['id' => 12, 'name' => 'Klaviyo', 'returnRate' => 0.025],
            ['id' => 13, 'name' => 'Influencer', 'returnRate' => 0.032],
            ['id' => 14, 'name' => 'SEO', 'returnRate' => 0.028],
            ['id' => 15, 'name' => 'Awin', 'returnRate' => 0.035],
            ['id' => 16, 'name' => 'Others', 'returnRate' => 0.040],
            // Debenhams
            ['id' => 2, 'name' => 'Debenhams', 'returnRate' => 0.042],
            // Amazon UK
            ['id' => 20, 'name' => 'Amazon UK', 'returnRate' => 0.055],
            // Amazon EU countries
            ['id' => 30, 'name' => 'Germany', 'returnRate' => 0.048],
            ['id' => 31, 'name' => 'France', 'returnRate' => 0.052],
            ['id' => 32, 'name' => 'Italy', 'returnRate' => 0.050],
            ['id' => 33, 'name' => 'Spain', 'returnRate' => 0.053],
            ['id' => 34, 'name' => 'Netherlands', 'returnRate' => 0.044],
            ['id' => 35, 'name' => 'Poland', 'returnRate' => 0.046],
            ['id' => 36, 'name' => 'Sweden', 'returnRate' => 0.041],
            ['id' => 37, 'name' => 'Belgium', 'returnRate' => 0.043],
            ['id' => 38, 'name' => 'Ireland', 'returnRate' => 0.047],
            // Other top-level channels
            ['id' => 4, 'name' => 'Spartoo', 'returnRate' => 0.039],
            ['id' => 5, 'name' => 'Temu', 'returnRate' => 0.060],
            ['id' => 6, 'name' => 'Rackhams', 'returnRate' => 0.036],
        ];

        // April has 30 days
        $dates = [];
        for ($day = 1; $day <= 30; $day++) {
            $dates[] = '2026-04-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        }

        $allReturns = [];

        // First, get daily sales volumes from the DailySale table
        // Since we're seeding after DailySaleSeeder, we can reference real data
        // But for random generation, we'll create correlated return data

        foreach ($platforms as $platform) {
            foreach ($dates as $date) {
                $dayOfWeek = date('N', strtotime($date));
                $isWeekend = ($dayOfWeek >= 6);
                $weekendMultiplier = $isWeekend ? 1.3 : 1.0;

                // Daily random variation for return volume
                $dailyVariation = rand(70, 130) / 100;

                // Calculate expected sales quantity for this platform/date
                // Based on typical daily quantities from the sales seeder
                $baseQuantity = match($platform['id']) {
                    10 => rand(85, 140),
                    11 => rand(70, 110),
                    12 => rand(40, 70),
                    13 => rand(20, 40),
                    14 => rand(14, 30),
                    15 => rand(8, 20),
                    16 => rand(4, 14),
                    2 => rand(50, 85),
                    20 => rand(80, 130),
                    30 => rand(20, 38),
                    31 => rand(14, 28),
                    32 => rand(10, 24),
                    33 => rand(9, 20),
                    34 => rand(6, 15),
                    35 => rand(5, 12),
                    36 => rand(4, 10),
                    37 => rand(4, 9),
                    38 => rand(3, 8),
                    4 => rand(16, 32),
                    5 => rand(7, 18),
                    6 => rand(12, 26),
                    default => 50,
                };

                $adjustedQuantity = round($baseQuantity * $weekendMultiplier * $dailyVariation);
                $totalReturnQuantity = round($adjustedQuantity * $platform['returnRate'] * $dailyVariation);

                if ($totalReturnQuantity == 0) {
                    continue; // Skip days with no returns
                }

                // Distribute returns across reason types
                $remainingReturns = $totalReturnQuantity;
                $reasonDistributions = [];

                foreach ($returnReasons as $index => $reason) {
                    if ($index === count($returnReasons) - 1) {
                        // Last reason gets the remaining
                        $reasonQuantity = $remainingReturns;
                    } else {
                        // Random distribution with weighted probabilities
                        $weight = match($reason['id']) {
                            1 => 35, // Wrong size - most common
                            2 => 20, // Defective
                            3 => 15, // Not as described
                            4 => 12, // Changed mind
                            5 => 5,  // Late delivery
                            6 => 8,  // Wrong item sent
                            7 => 5,  // Other
                            default => 10,
                        };

                        $maxForThisReason = min($remainingReturns, round($totalReturnQuantity * ($weight / 100)));
                        $reasonQuantity = ($index === 0)
                            ? rand(1, max(1, $maxForThisReason))
                            : rand(0, $maxForThisReason);
                    }

                    if ($reasonQuantity > 0) {
                        $reasonDistributions[] = [
                            'reason_id' => $reason['id'],
                            'quantity' => $reasonQuantity,
                        ];
                        $remainingReturns -= $reasonQuantity;
                    }

                    if ($remainingReturns <= 0) {
                        break;
                    }
                }

                // Create return records for each reason type
                foreach ($reasonDistributions as $dist) {
                    $returnCount = max(1, round($dist['quantity'] / rand(1, 2))); // Number of return transactions
                    $returnQuantity = $dist['quantity'];

                    // Gender distribution for returns (similar to sales but slightly different)
                    $malePercent = match($platform['id']) {
                        10, 11, 30, 31, 32, 33, 34, 35, 36, 37, 38 => rand(38, 52),
                        20 => rand(42, 58),
                        2, 4 => rand(28, 43),
                        5, 6 => rand(33, 48),
                        default => rand(38, 52),
                    };

                    $femalePercent = rand(28, 43);
                    $kidsPercent = 100 - ($malePercent + $femalePercent);

                    $maleReturns = round($returnCount * ($malePercent / 100));
                    $femaleReturns = round($returnCount * ($femalePercent / 100));
                    $kidsReturns = $returnCount - ($maleReturns + $femaleReturns);

                    $maleReturnQuantities = round($returnQuantity * ($malePercent / 100));
                    $femaleReturnQuantities = round($returnQuantity * ($femalePercent / 100));
                    $kidsReturnQuantities = $returnQuantity - ($maleReturnQuantities + $femaleReturnQuantities);

                    $allReturns[] = [
                        'sale_platform_id' => $platform['id'],
                        'return_reason_type_id' => $dist['reason_id'],
                        'date' => $date,
                        'number_of_returns' => $returnCount,
                        'number_of_return_quantities' => $returnQuantity,
                        'number_of_male_returns' => $maleReturns,
                        'number_of_female_returns' => $femaleReturns,
                        'number_of_kids_returns' => $kidsReturns,
                        'number_of_male_return_quantities' => $maleReturnQuantities,
                        'number_of_female_return_quantities' => $femaleReturnQuantities,
                        'number_of_kids_return_quantities' => $kidsReturnQuantities,
                    ];
                }
            }
        }

        // Shuffle for variety
        shuffle($allReturns);

        foreach ($allReturns as $return) {
            DailyReturn::updateOrCreate(
                [
                    'sale_platform_id' => $return['sale_platform_id'],
                    'return_reason_type_id' => $return['return_reason_type_id'],
                    'date' => $return['date'],
                ],
                $return
            );
        }
    }
}