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
        // Platform budgets with base monthly amounts
        $platformBudgets = [
            // Enorsia main platform
            ['id' => 1, 'name' => 'Enorsia', 'baseBudget' => 15000],

            // Enorsia sub-channels
            ['id' => 10, 'name' => 'Google', 'baseBudget' => 6000],
            ['id' => 11, 'name' => 'Meta', 'baseBudget' => 4000],
            ['id' => 12, 'name' => 'Klaviyo', 'baseBudget' => 500],
            ['id' => 13, 'name' => 'Influencer', 'baseBudget' => 800],
            ['id' => 14, 'name' => 'SEO', 'baseBudget' => 300],
            ['id' => 15, 'name' => 'Awin', 'baseBudget' => 400],
            ['id' => 16, 'name' => 'Others', 'baseBudget' => 200],

            // Debenhams
            ['id' => 2, 'name' => 'Debenhams', 'baseBudget' => 0], // No ad spend

            // Amazon UK
            ['id' => 20, 'name' => 'Amazon UK', 'baseBudget' => 1500],

            // Amazon EU countries
            ['id' => 30, 'name' => 'Germany', 'baseBudget' => 400],
            ['id' => 31, 'name' => 'France', 'baseBudget' => 300],
            ['id' => 32, 'name' => 'Italy', 'baseBudget' => 250],
            ['id' => 33, 'name' => 'Spain', 'baseBudget' => 200],
            ['id' => 34, 'name' => 'Netherlands', 'baseBudget' => 150],
            ['id' => 35, 'name' => 'Poland', 'baseBudget' => 120],
            ['id' => 36, 'name' => 'Sweden', 'baseBudget' => 100],
            ['id' => 37, 'name' => 'Belgium', 'baseBudget' => 90],
            ['id' => 38, 'name' => 'Ireland', 'baseBudget' => 80],

            // Other top-level channels
            ['id' => 3, 'name' => 'Google Shopping', 'baseBudget' => 2500],
            ['id' => 4, 'name' => 'Spartoo', 'baseBudget' => 0],
            ['id' => 5, 'name' => 'Temu', 'baseBudget' => 0],
            ['id' => 6, 'name' => 'Rackhams', 'baseBudget' => 0],
        ];

        // Generate last 12 months (May 2025 to April 2026)
        $months = [];
        $startDate = new \DateTime('2025-05-01');
        $endDate = new \DateTime('2026-04-01');
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $months[] = [
                'year' => (int)$currentDate->format('Y'),
                'month' => (int)$currentDate->format('n'),
                'date' => clone $currentDate,
            ];
            $currentDate->modify('+1 month');
        }

        $allBudgets = [];

        foreach ($platformBudgets as $platform) {
            // Skip platforms with zero base budget (no ad spend)
            if ($platform['baseBudget'] == 0) {
                continue;
            }

            foreach ($months as $monthData) {
                $monthNum = $monthData['month'];
                $year = $monthData['year'];

                // Seasonal multiplier for ad spend
                $seasonalMultiplier = match($monthNum) {
                    11, 12 => 2.0,  // November, December (holiday season - double spend)
                    1 => 0.6,       // January (post-holiday - reduced spend)
                    2 => 0.7,       // February (still quiet)
                    3 => 0.8,       // March (gradual increase)
                    4, 5 => 0.9,    // April, May
                    6, 7, 8 => 0.85, // Summer months (slight reduction)
                    9 => 1.1,       // September (back to business)
                    10 => 1.3,      // October (pre-holiday ramp up)
                    default => 1.0,
                };

                // Year-over-year growth (10% increase from 2025 to 2026)
                $yearMultiplier = ($year == 2026) ? 1.1 : 1.0;

                // Platform-specific adjustments
                $platformMultiplier = match($platform['id']) {
                    10, 11 => 1.0,  // Google and Meta maintain base
                    12 => 1.2,      // Klaviyo growing
                    13 => 1.15,     // Influencer marketing growing
                    14 => 1.0,      // SEO steady
                    15 => 0.9,      // Awin slightly decreasing
                    16 => 0.8,      // Others decreasing
                    20 => 1.05,     // Amazon slight growth
                    30, 31, 32, 33, 34, 35, 36, 37, 38 => 1.1, // EU markets growing
                    default => 1.0,
                };

                // Random variation (±10%)
                $randomVariation = rand(90, 110) / 100;

                // Calculate final budget
                $budget = $platform['baseBudget']
                    * $seasonalMultiplier
                    * $yearMultiplier
                    * $platformMultiplier
                    * $randomVariation;

                // Round to 2 decimal places
                $budget = round($budget, 2);

                // Add notes for significant variations
                $notes = null;
                if ($monthNum == 11 || $monthNum == 12) {
                    $notes = 'Holiday season budget increase';
                } elseif ($monthNum == 1) {
                    $notes = 'Post-holiday budget reduction';
                } elseif ($monthNum == 10) {
                    $notes = 'Pre-holiday ramp up';
                }

                $allBudgets[] = [
                    'sale_platform_id' => $platform['id'],
                    'year' => $year,
                    'month' => $monthNum,
                    'budget' => $budget,
                    'currency' => 'GBP',
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert budgets in chunks to avoid memory issues
        $chunks = array_chunk($allBudgets, 100);
        foreach ($chunks as $chunk) {
            MonthlyBudget::insert($chunk);
        }
    }
}