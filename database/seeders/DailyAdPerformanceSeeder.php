<?php

namespace Database\Seeders;

use App\Models\DailyAdPerformance;
use App\Models\SalePlatform;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class DailyAdPerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $excelPath = public_path('enorsia_tracking.xlsx');

        if (!file_exists($excelPath)) {
            $this->command->error("Excel file not found at: {$excelPath}");
            return;
        }

        $this->command->info("Reading Excel file: {$excelPath}");

        // Load spreadsheet
        $spreadsheet = IOFactory::load($excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Find where data starts (Sl.NO row)
        $startRow = 0;
        for ($i = 0; $i < min(20, count($rows)); $i++) {
            $row = $rows[$i];
            if (!empty($row[0]) && str_contains($row[0], 'Sl.NO')) {
                $startRow = $i + 1;
                break;
            }
        }

        if ($startRow == 0) {
            $this->command->error("Could not find data header row");
            return;
        }

        $this->command->info("Data starts at row: " . ($startRow + 1));

        // Get all existing platforms
        $allPlatforms = SalePlatform::all();

        // Build platform mapping
        $platformMapping = $this->buildPlatformMapping($allPlatforms);

        $inserted = 0;
        $updated = 0;
        $currentMonth = null;
        $currentPlatformData = [];

        // Process rows - collect data per month per platform
        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Get month from column B
            $monthValue = $row[1] ?? null;
            $platformName = trim($row[2] ?? '');
            $slNo = trim($row[0] ?? '');

            // Skip if no platform name or it's a total row
            if (empty($platformName) ||
                str_contains(strtolower($platformName), 'total') ||
                str_contains($platformName, '=') ||
                is_numeric($platformName)) {
                continue;
            }

            // Parse date
            $month = $this->parseDate($monthValue);

            if ($month) {
                $currentMonth = Carbon::parse($month)->startOfMonth()->toDateString();
            }

            if (!$currentMonth) {
                continue;
            }

            // Get platform ID
            $platformId = $platformMapping[$platformName] ?? null;

            if (!$platformId) {
                $this->command->warn("Row {$i}: Unknown platform '{$platformName}' - skipping");
                continue;
            }

            // Extract metrics (use 0 for empty values)
            $data = [
                'sale_platform_id'   => $platformId,
                'month'              => $currentMonth,
                'reach'              => $this->parseNumber($row[3] ?? 0, true),
                'impressions'        => $this->parseNumber($row[4] ?? 0, true),
                'clicks'             => $this->parseNumber($row[5] ?? 0, true),
                'sessions'           => $this->parseNumber($row[7] ?? 0, true),
                'engaged_sessions'   => $this->parseNumber($row[8] ?? 0, true),
                'users'              => $this->parseNumber($row[9] ?? 0, true),
                'ads_tax_payments'   => $this->parseNumber($row[11] ?? 0),
            ];

            // Store for this platform + month
            $key = $platformId . '_' . $currentMonth;
            if (!isset($currentPlatformData[$key])) {
                $currentPlatformData[$key] = $data;
            } else {
                // Merge data (add values if multiple rows for same platform/month)
                foreach ($data as $field => $value) {
                    if (!in_array($field, ['sale_platform_id', 'month']) && is_numeric($value)) {
                        $currentPlatformData[$key][$field] = ($currentPlatformData[$key][$field] ?? 0) + $value;
                    }
                }
            }
        }

        // Now upsert all collected data
        foreach ($currentPlatformData as $data) {
            $existing = DailyAdPerformance::where('sale_platform_id', $data['sale_platform_id'])
                ->where('month', $data['month'])
                ->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                DailyAdPerformance::create($data);
                $inserted++;
            }

            if (($inserted + $updated) % 50 == 0) {
                $this->command->info("Processed " . ($inserted + $updated) . " records...");
            }
        }

        $this->command->info("\n✓ Seeder completed:");
        $this->command->info("  - Inserted: {$inserted} records");
        $this->command->info("  - Updated: {$updated} records");
        $this->command->info("  - Total: " . DailyAdPerformance::count() . " records in database");
    }

    /**
     * Build platform name to ID mapping
     */
    private function buildPlatformMapping($allPlatforms): array
    {
        $mapping = [];

        foreach ($allPlatforms as $platform) {
            $mapping[$platform->name] = $platform->id;
            $mapping[strtolower($platform->name)] = $platform->id;

            // Add variations for matching Excel names
            if ($platform->name === 'Meta') {
                $mapping['Meta Ads (Facebook & Instagram)'] = $platform->id;
                $mapping['Meta Ads'] = $platform->id;
                $mapping['Facebook'] = $platform->id;
                $mapping['Instagram'] = $platform->id;
            }
            if ($platform->name === 'Google') {
                $mapping['Google Ads'] = $platform->id;
            }
            if ($platform->name === 'Google Analytics 4') {
                $mapping['GA4'] = $platform->id;
            }
            if ($platform->name === 'Awin') {
                $mapping['Awin'] = $platform->id;
            }
            if ($platform->name === 'Klaviyo') {
                $mapping['klaviyo'] = $platform->id;
            }
            if ($platform->name === 'Influencer') {
                $mapping['Influencer'] = $platform->id;
            }
            if ($platform->name === 'Temu') {
                $mapping['Temu'] = $platform->id;
            }
            if ($platform->name === 'Rackhams') {
                $mapping['Rackhams'] = $platform->id;
            }
            if ($platform->name === 'Spartoo') {
                $mapping['Spartoo'] = $platform->id;
            }
            if ($platform->name === 'Debenhams') {
                $mapping['Debenhams'] = $platform->id;
            }
            if ($platform->name === 'Amazon UK') {
                $mapping['Amazon'] = $platform->id;
            }
        }

        return $mapping;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            // Handle Excel serial date
            if (is_numeric($date)) {
                $unix = ($date - 25569) * 86400;
                $formatted = date('Y-m-d', $unix);
                if ($formatted && $formatted !== '1970-01-01') {
                    return $formatted;
                }
            }

            // Handle string dates like "2023-09-01 00:00:00"
            if (is_string($date)) {
                $dateStr = preg_replace('/\s+\d{2}:\d{2}:\d{2}/', '', $date);
                $parsed = date('Y-m-d', strtotime($dateStr));
                if ($parsed && $parsed !== '1970-01-01') {
                    return $parsed;
                }
            }

            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse number from Excel cell
     */
    private function parseNumber($value, $isInteger = false): float|int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Handle Excel formula results
        if (is_numeric($value)) {
            return $isInteger ? (int) $value : (float) $value;
        }

        // Handle strings with K suffix (e.g., "471K")
        if (is_string($value) && preg_match('/^([\d.]+)K$/i', $value, $matches)) {
            $result = (float) $matches[1] * 1000;
            return $isInteger ? (int) $result : $result;
        }

        // Remove currency symbols, commas, and other non-numeric characters
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);

        if (is_numeric($cleaned) && $cleaned !== '') {
            $result = (float) $cleaned;
            return $isInteger ? (int) $result : $result;
        }

        return 0;
    }

    /**
     * Parse percentage from Excel cell
     */
    private function parsePercentage($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove % sign and parse
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);

        if (is_numeric($cleaned) && $cleaned !== '') {
            return (float) $cleaned;
        }

        return 0;
    }
}