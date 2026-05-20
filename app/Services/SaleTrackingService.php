<?php

namespace App\Services;

use App\Models\DailyAdPerformance;
use App\Models\SalePlatform;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
    private const COL_NET_COST   = 'J'; // Net Cost
    private const COL_ADS_TAX    = 'K'; // Ads in.tax Payments
    private const COL_TOTAL_COST = 'L'; // Total Cost
    private const COL_ORDERS     = 'M'; // Number of Order
    private const COL_PRODUCTS   = 'N'; // Number of Product
    private const COL_SALES_GROW = 'O'; // sales grow %
    private const COL_REVENUE    = 'P'; // Revenue
    private const COL_TOTAL_REV  = 'Q'; // Total Revenue
    private const COL_RETURN     = 'R'; // Total Return
    private const COL_NET_REV    = 'S'; // Net Revenue
    private const COL_ROI        = 'T'; // ROI
    private const COL_ROAS       = 'U'; // ROAS

    // ── List / index ─────────────────────────────────────────────

    public function getList(array $filters): LengthAwarePaginator
    {
        return DailyAdPerformance::with('salePlatform')
            ->whereHas('salePlatform', fn ($q) => $q->where('show_in_sale_tracking', true))
            ->filter($filters)
            ->orderByDesc('month')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * Build month-wise card groups for the index page.
     * Returns: monthGroups[] with monthKey, monthFormatted, totals, entries, platformCards[]
     */
    public function buildMonthViewGroups(LengthAwarePaginator $paginator): array
    {
        $monthGroups = [];

        foreach (
            $paginator->getCollection()
                ->groupBy(fn($r) => optional($r->month)->format('Y-m') ?? '')
                ->sortKeysDesc()
            as $monthKey => $monthRecords
        ) {
            $platformCards = [];
            foreach ($monthRecords as $rec) {
                $platformCards[] = $rec;
            }

            $monthGroups[] = [
                'monthKey'       => $monthKey,
                'monthFormatted' => $monthRecords->first()->month
                    ? Carbon::parse($monthKey . '-01')->format('F Y')
                    : '—',
                'totalRevenue'   => $monthRecords->sum('total_revenue'),
                'totalCost'      => $monthRecords->sum('total_cost'),
                'totalOrders'    => $monthRecords->sum('number_of_orders'),
                'totalNetRev'    => $monthRecords->sum('net_revenue'),
                'entries'        => $monthRecords->values(),
                'platformCards'  => $platformCards,
            ];
        }

        return $monthGroups;
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

            $netCost   = $this->toFloat($sheet->getCell(self::COL_NET_COST . $row)->getCalculatedValue());
            $adsTax    = $this->toFloat($sheet->getCell(self::COL_ADS_TAX . $row)->getCalculatedValue());
            $totalCost = $this->toFloat($sheet->getCell(self::COL_TOTAL_COST . $row)->getCalculatedValue());

            if ($totalCost === null && $netCost !== null && $adsTax !== null) {
                $totalCost = $netCost + $adsTax;
            }

            $records[] = [
                'month'              => $this->excelDateToCarbon($currentMonth),
                'platform_name'      => $platform,
                'reach'              => $this->toInt($sheet->getCell(self::COL_REACH . $row)->getCalculatedValue()),
                'impressions'        => $this->toInt($sheet->getCell(self::COL_IMPRESSIONS . $row)->getCalculatedValue()),
                'clicks'             => $this->toInt($sheet->getCell(self::COL_CLICKS . $row)->getCalculatedValue()),
                'sessions'           => $this->toInt($sheet->getCell(self::COL_SESSIONS . $row)->getCalculatedValue()),
                'engaged_sessions'   => $this->toInt($sheet->getCell(self::COL_ENGAGED . $row)->getCalculatedValue()),
                'users'              => $this->toInt($sheet->getCell(self::COL_USERS . $row)->getCalculatedValue()),
                'net_cost'           => $netCost,
                'ads_tax_payments'   => $adsTax,
                'total_cost'         => $totalCost,
                'number_of_orders'   => $this->toInt($sheet->getCell(self::COL_ORDERS . $row)->getCalculatedValue()),
                'number_of_products' => $this->toInt($sheet->getCell(self::COL_PRODUCTS . $row)->getCalculatedValue()),
                'sales_grow_percent' => $this->toFloat($sheet->getCell(self::COL_SALES_GROW . $row)->getCalculatedValue()),
                'revenue'            => $this->toFloat($sheet->getCell(self::COL_REVENUE . $row)->getCalculatedValue()),
                'total_revenue'      => $this->toFloat($sheet->getCell(self::COL_TOTAL_REV . $row)->getCalculatedValue()),
                'total_return'       => $this->toFloat($sheet->getCell(self::COL_RETURN . $row)->getCalculatedValue()),
                'net_revenue'        => $this->toFloat($sheet->getCell(self::COL_NET_REV . $row)->getCalculatedValue()),
                'roi'                => $this->toFloat($sheet->getCell(self::COL_ROI . $row)->getCalculatedValue()),
                'roas'               => $this->toFloat($sheet->getCell(self::COL_ROAS . $row)->getCalculatedValue()),
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

    public function getExportQuery(array $filters): Builder
    {
        return DailyAdPerformance::with('salePlatform')
            ->whereHas('salePlatform', fn ($q) => $q->where('show_in_sale_tracking', true))
            ->filter($filters)
            ->orderByDesc('month')
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
            'month'                              => 'required|date',
            'entries'                            => 'required|array|min:1',
            'entries.*.sale_platform_id'         => 'nullable|exists:sale_platforms,id',
            'entries.*.reach'                    => 'nullable|integer|min:0',
            'entries.*.impressions'              => 'nullable|integer|min:0',
            'entries.*.clicks'                   => 'nullable|integer|min:0',
            'entries.*.sessions'                 => 'nullable|integer|min:0',
            'entries.*.engaged_sessions'         => 'nullable|integer|min:0',
            'entries.*.users'                    => 'nullable|integer|min:0',
            'entries.*.net_cost'                 => 'nullable|numeric|min:0',
            'entries.*.ads_tax_payments'         => 'nullable|numeric|min:0',
            'entries.*.total_cost'               => 'nullable|numeric|min:0',
            'entries.*.number_of_orders'         => 'nullable|integer|min:0',
            'entries.*.number_of_products'       => 'nullable|integer|min:0',
            'entries.*.sales_grow_percent'       => 'nullable|numeric',
            'entries.*.revenue'                  => 'nullable|numeric|min:0',
            'entries.*.total_revenue'            => 'nullable|numeric|min:0',
            'entries.*.total_return'             => 'nullable|numeric|min:0',
            'entries.*.net_revenue'              => 'nullable|numeric',
            'entries.*.roi'                      => 'nullable|numeric',
            'entries.*.roas'                     => 'nullable|numeric',
            'entries.*.notes'                    => 'nullable|string|max:1000',
        ];
    }

    public function bulkUpdateRules(): array
    {
        return [
            'month'                              => 'required|date',
            'entries'                            => 'present|array',
            'entries.*.sale_platform_id'         => 'nullable|exists:sale_platforms,id',
            'entries.*.reach'                    => 'nullable|integer|min:0',
            'entries.*.impressions'              => 'nullable|integer|min:0',
            'entries.*.clicks'                   => 'nullable|integer|min:0',
            'entries.*.sessions'                 => 'nullable|integer|min:0',
            'entries.*.engaged_sessions'         => 'nullable|integer|min:0',
            'entries.*.users'                    => 'nullable|integer|min:0',
            'entries.*.net_cost'                 => 'nullable|numeric|min:0',
            'entries.*.ads_tax_payments'         => 'nullable|numeric|min:0',
            'entries.*.total_cost'               => 'nullable|numeric|min:0',
            'entries.*.number_of_orders'         => 'nullable|integer|min:0',
            'entries.*.number_of_products'       => 'nullable|integer|min:0',
            'entries.*.sales_grow_percent'       => 'nullable|numeric',
            'entries.*.revenue'                  => 'nullable|numeric|min:0',
            'entries.*.total_revenue'            => 'nullable|numeric|min:0',
            'entries.*.total_return'             => 'nullable|numeric|min:0',
            'entries.*.net_revenue'              => 'nullable|numeric',
            'entries.*.roi'                      => 'nullable|numeric',
            'entries.*.roas'                     => 'nullable|numeric',
            'entries.*.notes'                    => 'nullable|string|max:1000',
        ];
    }

    // ── Single-record rules (kept for API fallback) ───────────────

    public function storeRules(): array
    {
        return [
            'sale_platform_id'   => 'nullable|exists:sale_platforms,id',
            'month'              => 'required|date',
            'reach'              => 'nullable|integer|min:0',
            'impressions'        => 'nullable|integer|min:0',
            'clicks'             => 'nullable|integer|min:0',
            'sessions'           => 'nullable|integer|min:0',
            'engaged_sessions'   => 'nullable|integer|min:0',
            'users'              => 'nullable|integer|min:0',
            'net_cost'           => 'nullable|numeric|min:0',
            'ads_tax_payments'   => 'nullable|numeric|min:0',
            'total_cost'         => 'nullable|numeric|min:0',
            'number_of_orders'   => 'nullable|integer|min:0',
            'number_of_products' => 'nullable|integer|min:0',
            'sales_grow_percent' => 'nullable|numeric',
            'revenue'            => 'nullable|numeric|min:0',
            'total_revenue'      => 'nullable|numeric|min:0',
            'total_return'       => 'nullable|numeric|min:0',
            'net_revenue'        => 'nullable|numeric',
            'roi'                => 'nullable|numeric',
            'roas'               => 'nullable|numeric',
            'notes'              => 'nullable|string|max:1000',
        ];
    }

    // ── CRUD ──────────────────────────────────────────────────────

    public function bulkCreate(string $month, array $entries): array
    {
        // Store as first of month
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $created   = [];
        foreach ($entries as $entry) {
            $entry['month'] = $monthDate;
            $created[]      = DailyAdPerformance::create($this->computeDerived($this->normalise($entry)));
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
            $data           = $this->computeDerived($this->normalise($entry));

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
        return DailyAdPerformance::create($this->computeDerived($this->normalise($data)));
    }

    public function update(DailyAdPerformance $record, array $data): DailyAdPerformance
    {
        if (!empty($data['month'])) {
            $data['month'] = Carbon::parse($data['month'])->startOfMonth()->toDateString();
        }
        $record->update($this->computeDerived($this->normalise($data)));
        return $record;
    }

    public function delete(DailyAdPerformance $record): void
    {
        $record->delete();
    }

    // ── Computed fields ───────────────────────────────────────────

    public function computeDerived(array $data): array
    {
        $netCost  = isset($data['net_cost'])         ? (float) $data['net_cost']         : null;
        $adsTax   = isset($data['ads_tax_payments']) ? (float) $data['ads_tax_payments'] : null;

        if (empty($data['total_cost']) && $netCost !== null && $adsTax !== null) {
            $data['total_cost'] = round($netCost + $adsTax, 2);
        }

        $totalCost   = !empty($data['total_cost'])   ? (float) $data['total_cost']   : null;
        $revenue     = isset($data['revenue'])       ? (float) $data['revenue']       : null;
        $totalReturn = isset($data['total_return'])  ? (float) $data['total_return']  : null;

        if (empty($data['total_revenue']) && $revenue !== null) {
            $data['total_revenue'] = $revenue;
        }

        $totalRevenue = !empty($data['total_revenue']) ? (float) $data['total_revenue'] : null;

        if (empty($data['net_revenue']) && $totalRevenue !== null && $totalReturn !== null) {
            $data['net_revenue'] = round($totalRevenue - $totalReturn, 2);
        }

        if (empty($data['roi']) && $totalCost !== null && $totalCost > 0 && $revenue !== null) {
            $data['roi'] = round(($revenue / $totalCost) * 100, 4);
        }

        if (empty($data['roas']) && $totalCost !== null && $totalCost > 0 && $revenue !== null) {
            $data['roas'] = round($revenue / $totalCost, 4);
        }

        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function normalise(array $data): array
    {
        foreach ($data as $key => $val) {
            if ($val === '') $data[$key] = null;
        }
        $ints = ['reach','impressions','clicks','sessions','engaged_sessions','users',
                 'number_of_orders','number_of_products'];
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

