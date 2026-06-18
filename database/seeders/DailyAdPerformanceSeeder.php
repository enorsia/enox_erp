<?php

namespace Database\Seeders;

use App\Models\DailyAdPerformance;
use App\Models\SalePlatform;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DailyAdPerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all platforms that have is_spent = true (advertising platforms)
        $platforms = SalePlatform::where('is_spent', true)->get();

        // Generate dates for the last 24 months (from current month going back 24 months)
        $months = [];
        $currentDate = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $months[] = $currentDate->copy()->subMonths($i);
        }
        // Reverse to have chronological order (oldest first)
        $months = array_reverse($months);

        $this->command->info("Generating monthly ad performance data for " . count($platforms) . " platforms");
        $this->command->info("Date range: " . $months[0]->format('Y-m-d') . " to " . $months[count($months)-1]->format('Y-m-d'));

        $inserted = 0;
        $updated = 0;
        $allRecords = [];

        foreach ($platforms as $platform) {
            foreach ($months as $month) {
                $monthNum = (int)$month->format('n');
                $year = (int)$month->format('Y');
                $monthStr = $month->format('Y-m-d');

                // Seasonal multiplier for ad performance (higher impressions/clicks during holidays)
                $seasonalMultiplier = match($monthNum) {
                    11, 12 => 1.8,  // November, December (holiday season)
                    1 => 0.7,       // January (post-holiday)
                    2 => 0.8,       // February
                    3 => 0.9,       // March
                    4, 5, 6 => 1.0, // April, May, June
                    7, 8 => 0.85,   // July, August (summer slowdown)
                    9 => 1.1,       // September (back to business)
                    10 => 1.3,      // October (pre-holiday ramp)
                    default => 1.0,
                };

                // Year-over-year growth (10% increase each year)
                $yearMultiplier = 1 + (($year - (int)Carbon::now()->year + 2) * 0.1);
                $yearMultiplier = min($yearMultiplier, 1.3); // Cap at 30% growth

                // Random daily/monthly variation
                $randomVariation = rand(75, 125) / 100;

                // Platform-specific base values (monthly)
                switch ($platform->id) {
                    case 10: // Google
                        $reach = round(rand(25000, 45000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(80000, 150000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(1800, 3500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(1500, 3000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(800, 1800) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(1200, 2500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(4000, 8000) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 11: // Meta
                        $reach = round(rand(35000, 65000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(120000, 220000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(2500, 4800) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(2000, 4000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(1000, 2200) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(1600, 3200) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(3000, 6000) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 12: // Klaviyo
                        $reach = round(rand(8000, 18000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(25000, 55000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(400, 900) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(350, 800) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(200, 500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(300, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(300, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 13: // Influencer
                        $reach = round(rand(15000, 30000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(45000, 90000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(800, 1800) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(700, 1500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(400, 900) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(600, 1300) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(600, 1500) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 14: // SEO (organic - no ad spend)
                        $reach = round(rand(5000, 12000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(15000, 35000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(300, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(250, 600) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(150, 400) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(200, 500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = 0;
                        break;

                    case 15: // Awin
                        $reach = round(rand(3000, 8000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(10000, 25000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(200, 500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(180, 450) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(100, 300) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(150, 400) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(250, 600) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 16: // Others
                        $reach = round(rand(2000, 6000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(8000, 20000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(150, 400) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(120, 350) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(80, 250) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(100, 300) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(150, 400) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 20: // Amazon UK
                        $reach = round(rand(18000, 35000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(60000, 120000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(1200, 2500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(1000, 2200) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(600, 1400) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(900, 1900) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(1000, 2500) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 30: // Germany
                        $reach = round(rand(6000, 12000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(20000, 40000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(400, 800) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(350, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(200, 450) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(300, 600) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(300, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 31: // France
                        $reach = round(rand(5000, 10000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(16000, 32000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(320, 650) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(280, 560) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(160, 360) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(240, 480) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(250, 550) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 32: // Italy
                        $reach = round(rand(4000, 8000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(13000, 26000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(260, 520) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(220, 450) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(130, 290) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(190, 380) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(200, 450) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 33: // Spain
                        $reach = round(rand(3500, 7000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(11000, 22000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(220, 450) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(190, 390) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(110, 250) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(170, 340) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(180, 400) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 34: // Netherlands
                        $reach = round(rand(2500, 5000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(8000, 16000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(160, 320) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(140, 280) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(80, 180) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(120, 240) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(130, 300) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 35: // Poland
                        $reach = round(rand(2000, 4000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(6000, 13000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(120, 250) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(100, 220) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(60, 140) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(90, 190) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(100, 240) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 36: // Sweden
                        $reach = round(rand(1500, 3000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(5000, 10000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(100, 200) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(80, 180) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(50, 120) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(70, 150) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(80, 200) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 37: // Belgium
                        $reach = round(rand(1200, 2500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(4000, 8000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(80, 160) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(70, 150) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(40, 100) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(60, 130) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(70, 170) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    case 38: // Ireland
                        $reach = round(rand(1000, 2000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(3000, 6500) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(60, 130) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(50, 120) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(30, 80) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(50, 110) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(60, 150) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;

                    default:
                        // For other platforms, generate generic data
                        $reach = round(rand(5000, 15000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $impressions = round(rand(15000, 50000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $clicks = round(rand(300, 1000) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $sessions = round(rand(250, 850) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $engagedSessions = round(rand(150, 550) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $users = round(rand(200, 700) * $seasonalMultiplier * $yearMultiplier * $randomVariation);
                        $adsTaxPayments = round(rand(200, 800) * $seasonalMultiplier * $yearMultiplier * $randomVariation, 2);
                        break;
                }

                $allRecords[] = [
                    'sale_platform_id' => $platform->id,
                    'month' => $monthStr,
                    'reach' => $reach,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'sessions' => $sessions,
                    'engaged_sessions' => $engagedSessions,
                    'users' => $users,
                    'ads_tax_payments' => $adsTaxPayments,
                ];
            }
        }

        $this->command->info("Processing " . count($allRecords) . " monthly records...");

        // Upsert all records
        foreach ($allRecords as $record) {
            $existing = DailyAdPerformance::where('sale_platform_id', $record['sale_platform_id'])
                ->where('month', $record['month'])
                ->first();

            if ($existing) {
                $existing->update($record);
                $updated++;
            } else {
                DailyAdPerformance::create($record);
                $inserted++;
            }

            if (($inserted + $updated) % 100 == 0) {
                $this->command->info("Processed " . ($inserted + $updated) . " records...");
            }
        }

        $this->command->info("\n✓ Seeder completed:");
        $this->command->info("  - Inserted: {$inserted} records");
        $this->command->info("  - Updated: {$updated} records");
        $this->command->info("  - Total in database: " . DailyAdPerformance::count() . " records");

        // Show date range of imported data
        $oldest = DailyAdPerformance::min('month');
        $newest = DailyAdPerformance::max('month');
        if ($oldest && $newest) {
            $this->command->info("  - Data range: " . Carbon::parse($oldest)->format('Y-m-d') . " to " . Carbon::parse($newest)->format('Y-m-d'));
        }
    }
}