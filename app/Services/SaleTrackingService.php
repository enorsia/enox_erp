<?php

namespace App\Services;

use App\Models\DailyAdPerformance;
use App\Models\DailyReturn;
use App\Models\DailySale;
use App\Models\SalePlatform;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class SaleTrackingService
{
    // Column indices in the Excel file (1-based)
    private const COL_SL         = 'A'; // Sl. NO
    private const COL_MONTH      = 'B'; // Months
    private const COL_PLATFORM   = 'C'; // platforms
    private const COL_REACH      = 'D'; // Reach
    private const COL_IMPRESSIONS= 'E'; // Impressions
    private const COL_CLICKS     = 'F'; // Clicks
    private const COL_SESSIONS   = 'G'; // Sessions
    private const COL_ENGAGED    = 'H'; // Engaged sessions
    private const COL_USERS      = 'I'; // Users
    private const COL_ADS_TAX    = 'K'; // Ads in.tax Payments

    // ── List / index ─────────────────────────────────────────────

    public function getList(array $filters): LengthAwarePaginator
    {
        return DailyAdPerformance::with('salePlatform')
            ->whereHas('salePlatform', fn ($q) => $q->where('show_in_sale_tracking', true))
            ->filter($filters)
            ->orderBy('month')   // oldest first, latest last
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * Build month-wise card groups for the index page.
     * Financial metrics (revenue, net_cost, total_return, etc.) are computed from
     * DailySale and DailyReturn — they are no longer stored on the model.
     */
    public function buildMonthViewGroups(LengthAwarePaginator $paginator): array
    {
        $allRecords = $paginator->getCollection();

        $platformIds = $allRecords->pluck('sale_platform_id')->filter()->unique()->values()->toArray();
        $monthKeys   = $allRecords->map(fn ($r) => optional($r->month)->format('Y-m'))->filter()->unique()->values()->toArray();

        $saleLookup   = $this->getSaleDataForExport($platformIds, $monthKeys);
        $returnLookup = $this->getReturnDataForExport($monthKeys);

        $monthGroups = [];

        foreach (
            $allRecords
                ->groupBy(fn ($r) => optional($r->month)->format('Y-m') ?? '')
                ->sortKeys()
            as $monthKey => $monthRecords
        ) {
            $platformCards = [];
            $monthRevenue  = 0.0;
            $monthCost     = 0.0;   // sum of ads_tax_payments per month
            $monthOrders   = 0;

            foreach ($monthRecords as $rec) {
                $platId  = $rec->sale_platform_id;
                $netCost = (float) ($saleLookup[$platId][$monthKey]['net_cost']    ?? 0);
                $revenue = (float) ($saleLookup[$platId][$monthKey]['revenue']     ?? 0);
                $orders  = (int)   ($saleLookup[$platId][$monthKey]['orders']      ?? 0);
                $prods   = (int)   ($saleLookup[$platId][$monthKey]['quantities']  ?? 0);
                $adsTax  = (float) ($rec->ads_tax_payments ?? 0);

                // Attach computed values as dynamic properties for easy display in view
                $rec->computed_net_cost  = $netCost;
                $rec->computed_revenue   = $revenue;
                $rec->computed_orders    = $orders;
                $rec->computed_products  = $prods;

                $monthRevenue += $revenue;
                $monthCost    += $adsTax;   // total_cost = SUM(ads_tax) per month
                $monthOrders  += $orders;

                $platformCards[] = $rec;
            }

            $totalReturn = (float) ($returnLookup[$monthKey] ?? 0);
            $netRevenue  = $monthRevenue - $totalReturn;
            $roas        = $monthCost > 0 ? round(($monthRevenue / $monthCost) * 100, 4) : null;
            $roi         = $roas !== null ? (int) round($roas) : null;

            $monthGroups[] = [
                'monthKey'       => $monthKey,
                'monthFormatted' => $monthRecords->first()->month
                    ? Carbon::parse($monthKey . '-01')->format('F Y')
                    : '—',
                'totalRevenue'   => $monthRevenue,
                'totalCost'      => $monthCost,
                'totalReturn'    => $totalReturn,
                'totalNetRev'    => $netRevenue,
                'totalOrders'    => $monthOrders,
                'roas'           => $roas,
                'roi'            => $roi,
                'entries'        => $monthRecords->values(),
                'platformCards'  => $platformCards,
            ];
        }

        return $monthGroups;
    }

    // ── Additional export data from DailySale / DailyReturn ──────

    /**
     * Returns a lookup of DailySale aggregates grouped by platform and month.
     * Result: [sale_platform_id][Y-m] => ['net_cost' => float, 'revenue' => float]
     */
    public function getSaleDataForExport(array $platformIds, array $monthKeys): array
    {
        if (empty($platformIds) || empty($monthKeys)) {
            return [];
        }

        $query = DailySale::selectRaw(
            'sale_platform_id,
             YEAR(date)  AS yr,
             MONTH(date) AS mn,
             SUM(spent)              AS total_spent,
             SUM(sales)              AS total_sales,
             SUM(number_of_orders)     AS total_orders,
             SUM(number_of_quantities) AS total_quantities'
        )
        ->whereIn('sale_platform_id', $platformIds)
        ->groupBy('sale_platform_id', DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->where(function ($q) use ($monthKeys) {
            foreach ($monthKeys as $mk) {
                [$year, $month] = explode('-', $mk);
                $q->orWhere(function ($inner) use ($year, $month) {
                    $inner->whereYear('date', (int) $year)
                          ->whereMonth('date', (int) $month);
                });
            }
        });

        $lookup = [];
        foreach ($query->get() as $row) {
            $mk = $row->yr . '-' . str_pad($row->mn, 2, '0', STR_PAD_LEFT);
            $lookup[$row->sale_platform_id][$mk] = [
                'net_cost'   => (float) ($row->total_spent      ?? 0),
                'revenue'    => (float) ($row->total_sales      ?? 0),
                'orders'     => (int)   ($row->total_orders     ?? 0),
                'quantities' => (int)   ($row->total_quantities ?? 0),
            ];
        }

        return $lookup;
    }

    /**
     * Returns a lookup of DailyReturn aggregates grouped by month (all platforms combined).
     * Result: [Y-m] => total_return (float)
     */
    public function getReturnDataForExport(array $monthKeys): array
    {
        if (empty($monthKeys)) {
            return [];
        }

        $query = DailyReturn::selectRaw(
            'YEAR(date)         AS yr,
             MONTH(date)        AS mn,
             SUM(return_amount) AS total_return'
        )
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->where(function ($q) use ($monthKeys) {
            foreach ($monthKeys as $mk) {
                [$year, $month] = explode('-', $mk);
                $q->orWhere(function ($inner) use ($year, $month) {
                    $inner->whereYear('date', (int) $year)
                          ->whereMonth('date', (int) $month);
                });
            }
        });

        $lookup = [];
        foreach ($query->get() as $row) {
            $mk = $row->yr . '-' . str_pad($row->mn, 2, '0', STR_PAD_LEFT);
            $lookup[$mk] = (float) ($row->total_return ?? 0);
        }

        return $lookup;
    }

    // ── Excel reader ──────────────────────────────────────────────

    public function readExcelFile(string $filePath): array
    {
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $spreadsheet  = $reader->load($filePath);
        $sheet        = $spreadsheet->getActiveSheet();
        $highestRow   = $sheet->getHighestRow();
        $records      = [];
        $currentMonth = null;

        for ($row = 4; $row <= $highestRow; $row++) {
            $sl       = $sheet->getCell(self::COL_SL . $row)->getCalculatedValue();
            $month    = $sheet->getCell(self::COL_MONTH . $row)->getCalculatedValue();
            $platform = trim((string) $sheet->getCell(self::COL_PLATFORM . $row)->getCalculatedValue());

            if ($sl !== null && $sl !== '') {
                $currentMonth = $month;
            }
            if (empty($platform)) continue;

            $adsTax = $this->toFloat($sheet->getCell(self::COL_ADS_TAX . $row)->getCalculatedValue());

            $records[] = [
                'month'              => $this->excelDateToCarbon($currentMonth),
                'platform_name'      => $platform,
                'reach'              => $this->toInt($sheet->getCell(self::COL_REACH . $row)->getCalculatedValue()),
                'impressions'        => $this->toInt($sheet->getCell(self::COL_IMPRESSIONS . $row)->getCalculatedValue()),
                'clicks'             => $this->toInt($sheet->getCell(self::COL_CLICKS . $row)->getCalculatedValue()),
                'sessions'           => $this->toInt($sheet->getCell(self::COL_SESSIONS . $row)->getCalculatedValue()),
                'engaged_sessions'   => $this->toInt($sheet->getCell(self::COL_ENGAGED . $row)->getCalculatedValue()),
                'users'              => $this->toInt($sheet->getCell(self::COL_USERS . $row)->getCalculatedValue()),
                'ads_tax_payments'   => $adsTax,
            ];
        }

        return $records;
    }

    public function getExcelColumns(string $filePath): array
    {
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = [];
        $row         = $sheet->getRowIterator(3, 3)->current();
        $cellIter    = $row->getCellIterator('A', 'U');
        $cellIter->setIterateOnlyExistingCells(false);
        foreach ($cellIter as $cell) {
            $val = trim((string) $cell->getValue());
            if ($val !== '') $headers[$cell->getColumn()] = $val;
        }
        return $headers;
    }

    // ── Export ────────────────────────────────────────────────────

    /**
     * Get total revenue for a given month to use as the Sales Growth % baseline.
     */
    public function getPrevMonthRevenueForGrowth(array $platformIds, string $monthKey): float
    {
        if (empty($platformIds) || empty($monthKey)) {
            return 0.0;
        }

        [$year, $month] = explode('-', $monthKey);
        $year  = (int) $year;
        $month = (int) $month;

        // ── 1. Try DailySale ─────────────────────────────────────────
        $row = DailySale::selectRaw('SUM(sales) AS total')
            ->whereIn('sale_platform_id', $platformIds)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->first();
        $total = (float) ($row->total ?? 0);

        // ── 2. Fallback: DailyAdPerformance (no revenue column anymore — return 0) ─
        return $total;
    }

    public function getExportQuery(array $filters): Builder
    {
        return DailyAdPerformance::with('salePlatform')
            ->whereHas('salePlatform', fn ($q) => $q->where('show_in_sale_tracking', true))
            ->filter($filters)
            ->orderBy('month')
            ->orderBy('id');
    }

    public function buildExportData(array $filters): array
    {
        $records = $this->getExportQuery($filters)->get();
        $groups  = [];
        foreach ($records as $rec) {
            $monthKey = optional($rec->month)->format('Y-m') ?? 'unknown';
            $groups[$monthKey][] = $rec;
        }
        return $groups;
    }

    // ── Bulk validation rules ─────────────────────────────────────

    public function bulkStoreRules(): array
    {
        return [
            'month'                              => 'required|date_format:Y-m',
            'entries'                            => 'required|array|min:1',
            'entries.*.sale_platform_id'         => 'nullable|exists:sale_platforms,id',
            'entries.*.reach'                    => 'nullable|integer|min:0',
            'entries.*.impressions'              => 'nullable|integer|min:0',
            'entries.*.clicks'                   => 'nullable|integer|min:0',
            'entries.*.sessions'                 => 'nullable|integer|min:0',
            'entries.*.engaged_sessions'         => 'nullable|integer|min:0',
            'entries.*.users'                    => 'nullable|integer|min:0',
            'entries.*.ads_tax_payments'         => 'nullable|numeric|min:0',
        ];
    }

    public function bulkUpdateRules(): array
    {
        return [
            'month'                              => 'required|date_format:Y-m',
            'entries'                            => 'present|array',
            'entries.*.id'                       => 'nullable|integer|min:1',   // MUST be here so Laravel doesn't strip it
            'entries.*.sale_platform_id'         => 'nullable|exists:sale_platforms,id',
            'entries.*.reach'                    => 'nullable|integer|min:0',
            'entries.*.impressions'              => 'nullable|integer|min:0',
            'entries.*.clicks'                   => 'nullable|integer|min:0',
            'entries.*.sessions'                 => 'nullable|integer|min:0',
            'entries.*.engaged_sessions'         => 'nullable|integer|min:0',
            'entries.*.users'                    => 'nullable|integer|min:0',
            'entries.*.ads_tax_payments'         => 'nullable|numeric|min:0',
        ];
    }

    // ── Single-record rules (kept for API fallback) ───────────────

    public function storeRules(): array
    {
        return [
            'sale_platform_id'   => 'nullable|exists:sale_platforms,id',
            'month'              => 'required|date_format:Y-m',
            'reach'              => 'nullable|integer|min:0',
            'impressions'        => 'nullable|integer|min:0',
            'clicks'             => 'nullable|integer|min:0',
            'sessions'           => 'nullable|integer|min:0',
            'engaged_sessions'   => 'nullable|integer|min:0',
            'users'              => 'nullable|integer|min:0',
            'ads_tax_payments'   => 'nullable|numeric|min:0',
        ];
    }

    // ── CRUD ──────────────────────────────────────────────────────

    public function bulkCreate(string $month, array $entries): array
    {
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $created   = [];
        foreach ($entries as $entry) {
            $entry['month'] = $monthDate;
            $created[]      = DailyAdPerformance::create($this->normalise($entry));
        }
        return $created;
    }

    public function getByMonth(string $month): \Illuminate\Database\Eloquent\Collection
    {
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();
        return DailyAdPerformance::with('salePlatform')
            ->whereYear('month',  Carbon::parse($monthDate)->year)
            ->whereMonth('month', Carbon::parse($monthDate)->month)
            ->orderBy('id')
            ->get();
    }

    /**
     * Sync: delete entries_delete[], update entries with id, create entries without id.
     */
    public function syncForMonth(string $month, array $entries, array $deleteIds = []): void
    {
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $monthYear = Carbon::parse($monthDate)->year;
        $monthNum  = Carbon::parse($monthDate)->month;

        if (!empty($deleteIds)) {
            DailyAdPerformance::whereYear('month', $monthYear)
                ->whereMonth('month', $monthNum)
                ->whereIn('id', $deleteIds)
                ->delete();
        }

        foreach ($entries as $entry) {
            $entry['month'] = $monthDate;
            $data           = $this->normalise($entry);

            if (!empty($data['id'])) {
                $id = (int) $data['id'];
                unset($data['id']);
                DailyAdPerformance::where('id', $id)->update($data);
            } else {
                unset($data['id']);
                DailyAdPerformance::create($data);
            }
        }
    }

    public function create(array $data): DailyAdPerformance
    {
        if (!empty($data['month'])) {
            $data['month'] = Carbon::parse($data['month'])->startOfMonth()->toDateString();
        }
        return DailyAdPerformance::create($this->normalise($data));
    }

    public function update(DailyAdPerformance $record, array $data): DailyAdPerformance
    {
        if (!empty($data['month'])) {
            $data['month'] = Carbon::parse($data['month'])->startOfMonth()->toDateString();
        }
        $record->update($this->normalise($data));
        return $record;
    }

    public function delete(DailyAdPerformance $record): void
    {
        $record->delete();
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function normalise(array $data): array
    {
        foreach ($data as $key => $val) {
            if ($val === '') $data[$key] = null;
        }
        $ints = ['reach','impressions','clicks','sessions','engaged_sessions','users'];
        foreach ($ints as $f) {
            if (isset($data[$f]) && $data[$f] !== null) $data[$f] = (int) $data[$f];
        }
        return $data;
    }

    private function toFloat($val): ?float
    {
        if ($val === null || $val === '') return null;
        $val = str_replace(['%', ',', ' '], '', (string) $val);
        return is_numeric($val) ? (float) $val : null;
    }

    private function toInt($val): ?int
    {
        if ($val === null || $val === '') return null;
        $val = str_replace([',', ' ', 'K'], ['', '', '000'], (string) $val);
        return is_numeric($val) ? (int) $val : null;
    }

    private function excelDateToCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $value)
                )->startOfMonth();
            } catch (\Exception $e) { return null; }
        }
        try { return Carbon::parse($value)->startOfMonth(); }
        catch (\Exception $e) { return null; }
    }
}

