<?php

namespace Database\Seeders;

use App\Models\DailyReturn;
use App\Models\DailySale;
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

        // Generate dates for the last 12 months (May 2025 to April 2026)
        $dates = [];
        $startDate = new \DateTime('2025-05-01');
        $endDate = new \DateTime('2026-04-30');
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->modify('+1 day');
        }

        $allReturns = [];

        foreach ($platforms as $platform) {
            foreach ($dates as $date) {
                // Get month number for seasonal adjustments (returns also have seasonality)
                $monthNum = (int)date('n', strtotime($date));

                // Seasonal multiplier for returns (higher returns after holiday season)
                $seasonalMultiplier = match($monthNum) {
                    1 => 1.4,       // January (post-holiday returns spike)
                    2 => 1.2,       // February (continued returns)
                    11, 12 => 0.8,  // November, December (lower returns during holidays)
                    3, 4 => 1.0,    // March, April
                    5, 6 => 0.9,    // May, June
                    7, 8 => 0.95,   // July, August
                    9, 10 => 1.0,   // September, October
                    default => 1.0,
                };

                $dayOfWeek = date('N', strtotime($date));
                $isWeekend = ($dayOfWeek >= 6);
                $weekendMultiplier = $isWeekend ? 1.2 : 1.0; // Slightly fewer returns on weekends

                // Daily random variation for return volume
                $dailyVariation = rand(70, 130) / 100;

                // Try to get actual sales quantity from DailySale table first
                $actualSale = DailySale::where('sale_platform_id', $platform['id'])
                    ->where('date', $date)
                    ->first();

                if ($actualSale) {
                    // Use actual sales data for more accurate returns
                    $baseQuantity = $actualSale->number_of_quantities;
                } else {
                    // Fallback to estimated quantities if sales data not available
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
                }

                // Apply seasonal and weekend multipliers to returns
                $adjustedQuantity = round($baseQuantity * $weekendMultiplier * $dailyVariation);

                // Base return rate with seasonal adjustment
                $adjustedReturnRate = $platform['returnRate'] * $seasonalMultiplier;

                // Calculate total return quantity
                $totalReturnQuantity = round($adjustedQuantity * $adjustedReturnRate * $dailyVariation);

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
                        // Weighted distribution based on reason type
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

                        // Adjust weights seasonally (more defective returns in winter? etc.)
                        $adjustedWeight = $weight;
                        if ($reason['id'] == 2 && in_array($monthNum, [1, 2, 3])) {
                            $adjustedWeight = $weight * 1.3; // More defects in winter months
                        }
                        if ($reason['id'] == 5 && in_array($monthNum, [11, 12])) {
                            $adjustedWeight = $weight * 1.5; // More late deliveries during holidays
                        }

                        $maxForThisReason = min($remainingReturns, round($totalReturnQuantity * ($adjustedWeight / 100)));
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
                    // Number of return transactions (1-3 items per return typically)
                    $returnCount = max(1, round($dist['quantity'] / rand(1, 3)));
                    $returnQuantity = $dist['quantity'];

                    // Gender distribution for returns (similar to sales but slightly different)
                    $malePercent = match($platform['id']) {
                        10, 11, 30, 31, 32, 33, 34, 35, 36, 37, 38 => rand(38, 52),
                        20 => rand(42, 58),
                        2, 4 => rand(28, 43),
                        5, 6 => rand(33, 48),
                        default => rand(38, 52),
                    };

                    // Average return value between £20 and £60, with seasonal variation
                    $avgReturnValue = match($monthNum) {
                        11, 12 => rand(35, 75), // Higher value returns during holidays
                        1, 2 => rand(25, 65),   // Mixed values in post-holiday
                        default => rand(20, 60),
                    };

                    $return_amount = round($returnQuantity * $avgReturnValue);

                    $femalePercent = rand(28, 43);
                    $kidsPercent = 100 - ($malePercent + $femalePercent);

                    // Ensure kidsPercent is not negative
                    if ($kidsPercent < 0) {
                        $overage = -$kidsPercent;
                        $femalePercent = max(0, $femalePercent - $overage);
                        $kidsPercent = 100 - ($malePercent + $femalePercent);
                    }

                    // Calculate gender splits with proper rounding
                    $maleReturns = (int)round($returnCount * ($malePercent / 100));
                    $femaleReturns = (int)round($returnCount * ($femalePercent / 100));
                    $kidsReturns = $returnCount - ($maleReturns + $femaleReturns);

                    // Ensure no negative values
                    if ($kidsReturns < 0) {
                        $kidsReturns = 0;
                        $maleReturns = (int)round($returnCount * 0.6);
                        $femaleReturns = $returnCount - $maleReturns;
                    }

                    $maleReturnQuantities = (int)round($returnQuantity * ($malePercent / 100));
                    $femaleReturnQuantities = (int)round($returnQuantity * ($femalePercent / 100));
                    $kidsReturnQuantities = $returnQuantity - ($maleReturnQuantities + $femaleReturnQuantities);

                    // Ensure no negative quantities
                    if ($kidsReturnQuantities < 0) {
                        $kidsReturnQuantities = 0;
                        $maleReturnQuantities = (int)round($returnQuantity * 0.6);
                        $femaleReturnQuantities = $returnQuantity - $maleReturnQuantities;
                    }

                    $allReturns[] = [
                        'sale_platform_id' => $platform['id'],
                        'return_reason_type_id' => $dist['reason_id'],
                        'date' => $date,
                        'return_amount' => $return_amount,
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

        // Insert or update in chunks to avoid memory issues
        $chunks = array_chunk($allReturns, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $return) {
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
}