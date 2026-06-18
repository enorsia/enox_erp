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
        $platforms = [
            // Enorsia sub-channels
            ['id' => 10, 'name' => 'Google'],
            ['id' => 11, 'name' => 'Meta'],
            ['id' => 12, 'name' => 'Klaviyo'],
            ['id' => 13, 'name' => 'Influencer'],
            ['id' => 14, 'name' => 'SEO'],
            ['id' => 15, 'name' => 'Awin'],
            ['id' => 16, 'name' => 'Others'],
            // Debenhams
            ['id' => 2, 'name' => 'Debenhams'],
            // Amazon UK
            ['id' => 20, 'name' => 'Amazon UK'],
            // Amazon EU countries
            ['id' => 30, 'name' => 'Germany'],
            ['id' => 31, 'name' => 'France'],
            ['id' => 32, 'name' => 'Italy'],
            ['id' => 33, 'name' => 'Spain'],
            ['id' => 34, 'name' => 'Netherlands'],
            ['id' => 35, 'name' => 'Poland'],
            ['id' => 36, 'name' => 'Sweden'],
            ['id' => 37, 'name' => 'Belgium'],
            ['id' => 38, 'name' => 'Ireland'],
            // Other top-level channels
            ['id' => 4, 'name' => 'Spartoo'],
            ['id' => 5, 'name' => 'Temu'],
            ['id' => 6, 'name' => 'Rackhams'],
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

        $allSales = [];

        foreach ($platforms as $platform) {
            foreach ($dates as $date) {
                // Get month number for seasonal adjustments
                $monthNum = (int)date('n', strtotime($date));

                // Seasonal multiplier (higher sales in Q4, lower in Q1)
                $seasonalMultiplier = match($monthNum) {
                    11, 12 => 1.5,  // November, December (Black Friday, Christmas)
                    1 => 0.7,       // January (post-holiday)
                    2, 3 => 0.8,    // February, March
                    4, 5, 6 => 1.0, // April, May, June
                    7, 8 => 0.9,    // July, August (summer slowdown)
                    9, 10 => 1.1,   // September, October (pre-holiday ramp)
                    default => 1.0,
                };

                // Weekday vs weekend pattern (higher sales on weekends)
                $dayOfWeek = date('N', strtotime($date));
                $isWeekend = ($dayOfWeek >= 6);
                $weekendMultiplier = $isWeekend ? 1.4 : 1.0;

                // Random factor for daily variation
                $dailyVariation = rand(70, 130) / 100;

                // Platform-specific base values with seasonal adjustment
                switch ($platform['id']) {
                    case 10: // Google
                        $spent = round(rand(800, 1500) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(3500, 5500) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(70, 110);
                        $quantities = rand(85, 140);
                        break;
                    case 11: // Meta
                        $spent = round(rand(600, 1100) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(2500, 4200) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(55, 90);
                        $quantities = rand(70, 110);
                        break;
                    case 12: // Klaviyo
                        $spent = 0;
                        $salesAmount = round(rand(1000, 2200) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(30, 55);
                        $quantities = rand(40, 70);
                        break;
                    case 13: // Influencer
                        $spent = round(rand(200, 500) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(600, 1300) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(15, 30);
                        $quantities = rand(20, 40);
                        break;
                    case 14: // SEO
                        $spent = 0;
                        $salesAmount = round(rand(400, 900) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(10, 22);
                        $quantities = rand(14, 30);
                        break;
                    case 15: // Awin
                        $spent = 0;
                        $salesAmount = round(rand(250, 600) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(6, 15);
                        $quantities = rand(8, 20);
                        break;
                    case 16: // Others
                        $spent = 0;
                        $salesAmount = round(rand(100, 350) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(3, 10);
                        $quantities = rand(4, 14);
                        break;
                    case 2: // Debenhams
                        $spent = 0;
                        $salesAmount = round(rand(1400, 2800) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(40, 70);
                        $quantities = rand(50, 85);
                        break;
                    case 20: // Amazon UK
                        $spent = round(rand(300, 700) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(2800, 4800) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(65, 105);
                        $quantities = rand(80, 130);
                        break;
                    case 30: // Germany
                        $spent = round(rand(80, 160) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(700, 1300) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(16, 30);
                        $quantities = rand(20, 38);
                        break;
                    case 31: // France
                        $spent = round(rand(50, 110) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(450, 850) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(10, 22);
                        $quantities = rand(14, 28);
                        break;
                    case 32: // Italy
                        $spent = round(rand(40, 90) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(350, 700) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(8, 18);
                        $quantities = rand(10, 24);
                        break;
                    case 33: // Spain
                        $spent = round(rand(35, 80) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(300, 600) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(7, 16);
                        $quantities = rand(9, 20);
                        break;
                    case 34: // Netherlands
                        $spent = round(rand(25, 60) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(200, 420) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(5, 12);
                        $quantities = rand(6, 15);
                        break;
                    case 35: // Poland
                        $spent = round(rand(20, 50) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(150, 320) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(4, 10);
                        $quantities = rand(5, 12);
                        break;
                    case 36: // Sweden
                        $spent = round(rand(15, 40) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(120, 260) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(3, 8);
                        $quantities = rand(4, 10);
                        break;
                    case 37: // Belgium
                        $spent = round(rand(12, 35) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(100, 220) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(3, 7);
                        $quantities = rand(4, 9);
                        break;
                    case 38: // Ireland
                        $spent = round(rand(10, 30) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $salesAmount = round(rand(80, 180) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(2, 6);
                        $quantities = rand(3, 8);
                        break;
                    case 4: // Spartoo
                        $spent = 0;
                        $salesAmount = round(rand(500, 1000) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(12, 25);
                        $quantities = rand(16, 32);
                        break;
                    case 5: // Temu
                        $spent = 0;
                        $salesAmount = round(rand(200, 500) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(5, 14);
                        $quantities = rand(7, 18);
                        break;
                    case 6: // Rackhams
                        $spent = 0;
                        $salesAmount = round(rand(350, 750) * $weekendMultiplier * $dailyVariation * $seasonalMultiplier, 2);
                        $orders = rand(9, 20);
                        $quantities = rand(12, 26);
                        break;
                    default:
                        continue 2;
                }

                // Apply orders and quantities scaling with seasonal adjustment
                $orders = round($orders * $seasonalMultiplier);
                $quantities = round($quantities * $seasonalMultiplier);

                // Ensure minimum values
                $orders = max(1, $orders);
                $quantities = max(1, $quantities);

                // Calculate gender splits with proper rounding that sums correctly
                $malePercent = match($platform['id']) {
                    10, 11, 30, 31, 32, 33, 34, 35, 36, 37, 38 => rand(40, 55),
                    20 => rand(45, 60),
                    2, 4 => rand(30, 45),
                    5, 6 => rand(35, 50),
                    default => rand(40, 55),
                };

                $femalePercent = rand(30, 45);
                $kidsPercent = 100 - ($malePercent + $femalePercent);

                // Ensure kidsPercent is not negative
                if ($kidsPercent < 0) {
                    $overage = -$kidsPercent;
                    $femalePercent = max(0, $femalePercent - $overage);
                    $kidsPercent = 100 - ($malePercent + $femalePercent);
                }

                // Calculate orders with remainder distributed
                $maleOrders = (int)round($orders * ($malePercent / 100));
                $femaleOrders = (int)round($orders * ($femalePercent / 100));
                $kidsOrders = $orders - ($maleOrders + $femaleOrders);

                // Ensure no negative orders
                if ($kidsOrders < 0) {
                    $kidsOrders = 0;
                    $maleOrders = (int)round($orders * 0.6);
                    $femaleOrders = $orders - $maleOrders;
                }

                // Calculate quantities with remainder distributed
                $maleQuantities = (int)round($quantities * ($malePercent / 100));
                $femaleQuantities = (int)round($quantities * ($femalePercent / 100));
                $kidsQuantities = $quantities - ($maleQuantities + $femaleQuantities);

                // Ensure no negative quantities
                if ($kidsQuantities < 0) {
                    $kidsQuantities = 0;
                    $maleQuantities = (int)round($quantities * 0.6);
                    $femaleQuantities = $quantities - $maleQuantities;
                }

                $allSales[] = [
                    'sale_platform_id' => $platform['id'],
                    'date' => $date,
                    'spent' => $spent,
                    'sales' => $salesAmount,
                    'number_of_orders' => $orders,
                    'number_of_quantities' => $quantities,
                    'number_of_male_orders' => $maleOrders,
                    'number_of_female_orders' => $femaleOrders,
                    'number_of_kids_orders' => $kidsOrders,
                    'number_of_male_quantities' => $maleQuantities,
                    'number_of_female_quantities' => $femaleQuantities,
                    'number_of_kids_quantities' => $kidsQuantities,
                ];
            }
        }

        // Shuffle for variety but keep data integrity
        shuffle($allSales);

        // Insert or update in chunks to avoid memory issues
        $chunks = array_chunk($allSales, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $sale) {
                DailySale::updateOrCreate(
                    ['sale_platform_id' => $sale['sale_platform_id'], 'date' => $sale['date']],
                    $sale
                );
            }
        }
    }
}