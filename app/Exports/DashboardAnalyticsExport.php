<?php

namespace App\Exports;

use App\Models\DailyReturn;
use App\Services\DashboardAnalyticsService;
use App\Services\SalesReportExportColumns;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class DashboardAnalyticsExport
{
    private const CLR_ACCENT        = 'FF009966';
    private const CLR_TITLE_BG      = 'FF005C3E';
    private const CLR_TITLE_FG      = 'FFFFFFFF';
    private const CLR_HDR_BG        = 'FF009966';
    private const CLR_HDR_FG        = 'FFFFFFFF';
    private const CLR_PLAT_BG       = 'FF52B08C';
    private const CLR_PLAT_FG       = 'FFFFFFFF';
    private const CLR_COLLABEL      = 'FFCCEEDD';
    private const CLR_COLLABEL_FG   = 'FF003D2B';
    private const CLR_WEEK          = 'FFE6F3F0';
    private const CLR_WEEK_FG       = 'FF3D2B00';
    private const CLR_ROW_ALT       = 'FFF0FAF5';
    private const CLR_TOTAL         = 'FFB3E6CC';
    private const CLR_TOTAL_FG      = 'FF003D2B';
    private const CLR_BUDGET        = 'FFFFF9CC';
    private const CLR_FORE          = 'FFFFF0AA';
    private const CLR_ROAS          = 'FFFFDDC0';
    private const CLR_WHITE         = 'FFFFFFFF';
    private const CLR_DARK_TEXT     = 'FF1A3A2A';
    private const CLR_AVERAGE_DAILY = 'FFE8D5F2';
    private const CLR_NEGATIVE      = 'FFFF0000';
    private const CLR_SEC_TITLE     = 'FF003D2B';
    private const CLR_SEC_HDR       = 'FF009966';
    private const CLR_SEC_ALT       = 'FFF0FAF5';
    private const CLR_PLATFORM_1    = 'FFE8F0FE';
    private const CLR_PLATFORM_2    = 'FFFEF3E2';
    private const CLR_PLATFORM_3    = 'FFE6F7E6';
    private const CLR_PLATFORM_4    = 'FFFFE6E6';
    private const CLR_PLATFORM_5    = 'FFF5E6FF';

    private const COL_WEEK  = 1;
    private const COL_DATE  = 2;
    private const COL_SALES = 3;
    private const COL_ROAS  = 4;
    private const COL_SPEND = 5;

    private const PLATFORM_COLORS = [
        self::CLR_PLATFORM_1,
        self::CLR_PLATFORM_2,
        self::CLR_PLATFORM_3,
        self::CLR_PLATFORM_4,
        self::CLR_PLATFORM_5,
    ];

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private array  $months,
        private array  $label,
        private array  $tables = ['daily_report', 'return_breakdown', 'weekly_breakdown'],
        private array  $columnSelection = [],
    ) {}

    public function download(DashboardAnalyticsService $service): StreamedResponse
    {
        Log::info('DashboardAnalyticsExport: download() started', [
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
            'months'    => $this->months,
            'label'     => $this->label,
            'tables'    => $this->tables,
        ]);

        try {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        if (count($this->months) > 1) {
            foreach ($this->months as $month) {
                $monthCarbon = Carbon::createFromDate($month['year'], $month['month'], 1);
                $monthStart  = $monthCarbon->copy()->startOfMonth()->toDateString();
                $monthEnd    = $monthCarbon->copy()->endOfMonth()->toDateString();

                if ($monthStart < $this->dateFrom) $monthStart = $this->dateFrom;
                if ($monthEnd   > $this->dateTo)   $monthEnd   = $this->dateTo;

                $monthTitle = $this->sanitizeSheetTitle($monthCarbon->format('M-Y'));
                $sheet      = $spreadsheet->createSheet();
                $sheet->setTitle($monthTitle);

                $this->writeSheetData($sheet, $service, $monthStart, $monthEnd, [$month], ['label' => $monthTitle]);
            }
        } else {
            $sheetTitle = $this->sanitizeSheetTitle($this->label['label'] ?? 'Report');
            $sheet      = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);
            $this->writeSheetData($sheet, $service, $this->dateFrom, $this->dateTo, $this->months, $this->label);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Analytics Report - '
            . ($this->label['label'] ?? 'Report')
            . ' - ' . now()->format('d M Y') . '.xlsx';

        Log::info('DashboardAnalyticsExport: download() spreadsheet built successfully', [
            'filename' => $filename,
            'sheets'   => count($spreadsheet->getAllSheets()),
        ]);

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);

        } catch (\Throwable $e) {
            Log::error('DashboardAnalyticsExport: download() failed', [
                'error'     => $e->getMessage(),
                'class'     => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
                'date_from' => $this->dateFrom,
                'date_to'   => $this->dateTo,
                'label'     => $this->label,
            ]);
            throw $e;
        }
    }

    private function writeSheetData(
        $sheet,
        DashboardAnalyticsService $service,
        string $dateFrom,
        string $dateTo,
        array  $months,
        array  $label,
    ): void {
        Log::info('DashboardAnalyticsExport: writeSheetData() started', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'label'     => $label,
            'tables'    => $this->tables,
        ]);

        try {
        $includeDailyReport     = in_array('daily_report',      $this->tables);
        $includeReturnBreakdown = in_array('return_breakdown',   $this->tables);
        $includeWeeklyBreakdown = in_array('weekly_breakdown',   $this->tables);

        $lastMainRow  = 0;
        $dataStartRow = 0;
        $dataEndRow   = 0;
        $wbLastCol    = 0;
        $retLastCol   = 0;
        $wbSecStart   = 0;
        $retSecStart  = 0;
        $moneyFmt     = '#,##0.00';

        $export = $service->getDailyExportData($dateFrom, $dateTo, $months);


        $columnData       = $export['column_data'];
        $tree             = $columnData['tree'] ?? [];
        $rootPlatforms    = $export['root_platforms'];
        $rows             = $export['rows'];
        $summaryRows      = $export['summary_rows'];
        $weeklyRows       = $export['weekly_rows'];
        $returnReasonData = $export['return_reason_data'];
        $totals           = $export['totals'];
        $numRoots         = count($rootPlatforms);

        $colService       = new SalesReportExportColumns();
        $groupedColumns   = $colService->groupedColumnsFromTree($tree);
        $dailyDefs        = $colService->filterDefs(SalesReportExportColumns::DAILY_REPORT, $colService->dailyReportDefs($groupedColumns, $rootPlatforms), $this->columnSelection);
        $returnDefs       = $colService->filterDefs(SalesReportExportColumns::RETURN_BREAKDOWN, $colService->returnBreakdownDefs($rootPlatforms), $this->columnSelection);
        $weeklyDefs       = $colService->filterDefs(SalesReportExportColumns::WEEKLY_BREAKDOWN, $colService->weeklyBreakdownDefs($rootPlatforms), $this->columnSelection);
        $allPlatCols      = $this->buildGroupedColumns($tree);

        $returnsByDatePlatform = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('DATE(date) as dt, sale_platform_id, SUM(return_amount) as amount, SUM(number_of_returns) as order_qty, SUM(number_of_return_quantities) as item_qty')
            ->groupByRaw('DATE(date), sale_platform_id')
            ->get()
            ->groupBy('dt')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [
                (int) $r->sale_platform_id => [
                    'amount'    => (float) ($r->amount    ?? 0),
                    'order_qty' => (float) ($r->order_qty ?? 0),
                    'item_qty'  => (float) ($r->item_qty  ?? 0),
                ],
            ])->toArray())
            ->toArray();

        $returnAmountByDate = array_map(
            fn ($platforms) => array_sum(array_column($platforms, 'amount')),
            $returnsByDatePlatform
        );

        $weeklyReturnGbpMap = [];
        foreach ($rows as $row) {
            $wk = $row['week'];
            $weeklyReturnGbpMap[$wk] = ($weeklyReturnGbpMap[$wk] ?? 0)
                + ($returnAmountByDate[$row['date']] ?? 0);
        }
        foreach ($weeklyRows as &$wRow) {
            $wRow['returns_gbp'] = $weeklyReturnGbpMap[$wRow['week']] ?? 0;
        }
        unset($wRow);

        $rootLeafSalesMap = [];
        foreach ($allPlatCols as $col) {
            if ($col['level'] === 0 && $col['col_type'] === 'sales') {
                $rootLeafSalesMap[$col['platform_id']] = $col['leaf_ids'];
            }
        }

        $weeklySalesByRoot   = [];
        $weeklyReturnsByRoot = [];

        foreach ($rows as $row) {
            $wk = $row['week'];
            $dt = $row['date'];
            if (!isset($weeklySalesByRoot[$wk])) {
                $weeklySalesByRoot[$wk] = array_fill_keys(array_column($rootPlatforms, 'id'), 0.0);
                foreach ($rootPlatforms as $root) {
                    $weeklyReturnsByRoot[$wk][$root['id']] = ['amount' => 0.0, 'order_qty' => 0.0, 'item_qty' => 0.0];
                }
            }
            foreach ($rootPlatforms as $root) {
                $rid     = $root['id'];
                $leafIds = $rootLeafSalesMap[$rid] ?? [$rid];
                foreach ($leafIds as $leafId) {
                    $weeklySalesByRoot[$wk][$rid] += (float) ($row['platform'][$leafId]['sales'] ?? 0);
                    $ret = $returnsByDatePlatform[$dt][$leafId] ?? null;
                    if ($ret) {
                        $weeklyReturnsByRoot[$wk][$rid]['amount']    += $ret['amount'];
                        $weeklyReturnsByRoot[$wk][$rid]['order_qty'] += $ret['order_qty'];
                        $weeklyReturnsByRoot[$wk][$rid]['item_qty']  += $ret['item_qty'];
                    }
                }
            }
        }

        $mainLastCol     = ($includeDailyReport && count($dailyDefs) > 0) ? count($dailyDefs) : 0;

        $firstHdrRow  = 7;
        $colLabelRow  = $firstHdrRow;
        $dataStartRow = $firstHdrRow + 1;

        if ($includeDailyReport && count($dailyDefs) > 0) {
        $titleStr      = 'Tracking Digital Marketing COST VS Allocation – ' . ($label['label'] ?? '');
        $titleStartCol = Coordinate::stringFromColumnIndex(1);
        $titleEndCol   = Coordinate::stringFromColumnIndex($mainLastCol);
        $sheet->setCellValue($titleStartCol . '6', $titleStr);
        $sheet->mergeCells("{$titleStartCol}6:{$titleEndCol}6");
        $this->styleTitle($sheet, "{$titleStartCol}6:{$titleEndCol}6");

        $sheet->setShowSummaryRight(false);
        $colLabelRow  = $this->writeDailyHeaders($sheet, $dailyDefs, $firstHdrRow);
        $dataStartRow = $colLabelRow + 1;

        $r            = $dataStartRow;
        $weekColIndex = $this->defColumnIndex($dailyDefs, 'week');
        $weekRanges   = [];
        $prevWeek     = null;

        foreach ($rows as $row) {
            $weekNum = $row['week'];
            if ($weekColIndex !== null) {
                if ($weekNum !== $prevWeek) {
                    $sheet->setCellValueByColumnAndRow($weekColIndex, $r, 'Week ' . $weekNum);
                    $weekRanges[$weekNum] = ['start' => $r, 'end' => $r];
                    $prevWeek = $weekNum;
                } else {
                    $weekRanges[$weekNum]['end'] = $r;
                }
            }

            foreach ($dailyDefs as $i => $def) {
                if ($def['key'] === 'week') {
                    continue;
                }
                $val = $this->dailyDataValue($def, $row);
                if ($val !== null) {
                    $sheet->setCellValueByColumnAndRow($i + 1, $r, $val);
                }
                $sheet->getStyleByColumnAndRow($i + 1, $r)
                    ->getAlignment()
                    ->setHorizontal($this->dailyColumnAlignment($def))
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            if ($r % 2 === 0) {
                $this->fillRow($sheet, $r, $mainLastCol, self::CLR_ROW_ALT);
            }
            $r++;
        }
        $dataEndRow = $r - 1;

        foreach ($weekRanges as $wRange) {
            if ($weekColIndex === null) {
                break;
            }
            if ($wRange['end'] > $wRange['start']) {
                $col = Coordinate::stringFromColumnIndex($weekColIndex);
                $sheet->mergeCells($col . $wRange['start'] . ':' . $col . $wRange['end']);
            }
            $sheet->getStyleByColumnAndRow($weekColIndex, $wRange['start'])->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyleByColumnAndRow($weekColIndex, $wRange['start'])->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_WEEK);
            $sheet->getStyleByColumnAndRow($weekColIndex, $wRange['start'])->getFont()->setBold(true)
                ->getColor()->setARGB(self::CLR_WEEK_FG);
        }

        $summaryColorMap = [
            'average_daily'  => self::CLR_AVERAGE_DAILY,
            'total_sale'     => self::CLR_TOTAL,
            'total_spend'    => self::CLR_TOTAL,
            'total_budget'   => self::CLR_BUDGET,
            'balance_budget' => self::CLR_BUDGET,
            'roi'            => self::CLR_ROAS,
            'forecasting'    => self::CLR_FORE,
        ];

        foreach ($summaryRows as $key => $sRow) {
            $color          = $summaryColorMap[$key] ?? self::CLR_WHITE;
            $useSumFormulas = in_array($key, ['total_sale', 'total_spend'], true) && $dataEndRow >= $dataStartRow;

            foreach ($dailyDefs as $i => $def) {
                if ($def['key'] === 'week') {
                    continue;
                }
                $cell = $this->dailySummaryCell($def, $sRow, $key, $allPlatCols, $useSumFormulas, $dataStartRow, $dataEndRow, $i + 1);
                if ($cell === null) {
                    continue;
                }
                if (!empty($cell['formula'])) {
                    $sheet->setCellValueByColumnAndRow($i + 1, $r, $cell['formula']);
                } else {
                    $sheet->setCellValueByColumnAndRow($i + 1, $r, $cell['value']);
                }
                if (!empty($cell['format'])) {
                    $sheet->getStyleByColumnAndRow($i + 1, $r)->getNumberFormat()->setFormatCode($cell['format']);
                }
                if ($def['type'] === 'fixed' && $def['field'] === 'date') {
                    $sheet->getStyleByColumnAndRow($i + 1, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyleByColumnAndRow($i + 1, $r)->getFont()->setBold(true);
                } else {
                    $sheet->getStyleByColumnAndRow($i + 1, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                if (isset($cell['value']) && is_numeric($cell['value']) && (float) $cell['value'] < 0) {
                    $sheet->getStyleByColumnAndRow($i + 1, $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
                }
            }

            $this->fillRow($sheet, $r, $mainLastCol, $color);
            $r++;
        }
        $lastMainRow = $r - 1;
        $r   += 4;
        } elseif ($includeDailyReport) {
            $r = 7;
        } else {
            $r = 7;
        }

        $anc = 2;

        if ($includeReturnBreakdown && count($returnDefs) > 0) {
        $retLastCol     = $anc + count($returnDefs) - 1;
        $retGrandTotal  = array_sum($returnReasonData['totals_by_root']);

        $retSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $retLastCol, $r, 'Return Breakdown');
        $r++;

        foreach ($returnDefs as $i => $def) {
            $sheet->setCellValueByColumnAndRow($anc + $i, $r, $this->returnHeaderLabel($def));
        }
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $anc, $retLastCol, $r);
        $r++;

        $pctFmt = '0.0%';
        foreach ($returnReasonData['reasons'] as $reason) {
            foreach ($returnDefs as $i => $def) {
                $val = $this->returnDataValue($def, $reason, $retGrandTotal);
                $sheet->setCellValueByColumnAndRow($anc + $i, $r, $val);
                if ($def['type'] === 'return_root_pct' || $def['type'] === 'return_total_pct') {
                    $sheet->getStyleByColumnAndRow($anc + $i, $r)->getNumberFormat()->setFormatCode($pctFmt);
                }
            }
            $this->alignSecRow($sheet, $anc, $retLastCol, $r);
            if ($r % 2 === 0) {
                $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_ALT);
            }
            $r++;
        }

        foreach ($returnDefs as $i => $def) {
            $val = $this->returnTotalValue($def, $returnReasonData, $retGrandTotal);
            $sheet->setCellValueByColumnAndRow($anc + $i, $r, $val);
            if ($def['type'] === 'return_root_pct' || $def['type'] === 'return_total_pct') {
                $sheet->getStyleByColumnAndRow($anc + $i, $r)->getNumberFormat()->setFormatCode($pctFmt);
            }
        }
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $retLastCol, $r);
        $retSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $retLastCol, $retSecStart, $retSecEnd);
        $r = $retSecEnd + 4;
        }

        if ($includeWeeklyBreakdown && count($weeklyDefs) > 0) {
        $wbLastCol  = $anc + count($weeklyDefs) - 1;
        $wbSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $wbLastCol, $r, 'Weekly Breakdown : Sale vs Spends vs Return');
        $r++;

        $headerRow1 = $r;
        $headerRow2 = $this->writeWeeklyHeaders($sheet, $weeklyDefs, $anc, $r, $rootPlatforms);
        $r          = $headerRow2 + 1;

        $totalRetPcs    = 0;
        $totalRetGbp    = 0;
        $totalOrders    = 0;
        $totalItems     = 0;
        $platformTotals = [];
        foreach ($rootPlatforms as $root) {
            $platformTotals[$root['id']] = array_fill_keys(
                ['sales', 'orders', 'qty', 'return', 'ret_orders', 'ret_qty'],
                0.0,
            );
        }

        foreach ($weeklyRows as $wRow) {
            $wk         = $wRow['week'];
            $sales      = (float) ($wRow['sales'] ?? 0);
            $spend      = (float) ($wRow['spend'] ?? 0);
            $retPcs     = (float) ($wRow['returns_pcs'] ?? 0);
            $retGbp     = (float) ($wRow['returns_gbp'] ?? 0);
            $weekOrders = (float) array_sum($wRow['root_orders'] ?? []);
            $weekItems  = (float) array_sum($wRow['root_qty'] ?? []);

            foreach ($weeklyDefs as $i => $def) {
                $val = $this->weeklyDataValue(
                    $def,
                    $wRow,
                    $weeklySalesByRoot[$wk] ?? [],
                    $weeklyReturnsByRoot[$wk] ?? [],
                    $sales,
                    $spend,
                    $weekOrders,
                    $weekItems,
                    $retPcs,
                    $retGbp,
                );
                $sheet->setCellValueByColumnAndRow($anc + $i, $r, $val);
                if ($def['type'] === 'weekly_platform') {
                    $platformTotals[$def['root_id']][$def['field']] += is_numeric($val) ? (float) $val : 0;
                }
            }

            $totalRetPcs += $retPcs;
            $totalRetGbp += $retGbp;
            $totalOrders += $weekOrders;
            $totalItems  += $weekItems;

            $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
            if ($r % 2 === 0) {
                $fixedEnd = $anc + count(array_filter($weeklyDefs, fn ($d) => $d['type'] === 'weekly_fixed')) - 1;
                if ($fixedEnd >= $anc) {
                    $this->fillSecRange($sheet, $anc, $fixedEnd, $r, self::CLR_SEC_ALT);
                }
            }
            $r++;
        }

        $totalSales     = (float) ($totals['sales'] ?? 0);
        $totalSpend     = (float) ($totals['spent'] ?? 0);
        $pctTotalRetPcs = $totalItems > 0 ? $totalRetPcs / $totalItems : 0;
        $pctTotalRetGbp = $totalSales > 0 ? $totalRetGbp / $totalSales : 0;

        foreach ($weeklyDefs as $i => $def) {
            $val = $this->weeklyTotalValue(
                $def,
                $totalSales,
                $totalSpend,
                $totalOrders,
                $totalItems,
                $totalRetPcs,
                $totalRetGbp,
                $pctTotalRetPcs,
                $pctTotalRetGbp,
                $platformTotals,
            );
            $sheet->setCellValueByColumnAndRow($anc + $i, $r, $val);
        }

        $this->fillSecRange($sheet, $anc, $wbLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
        $wbSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $wbLastCol, $wbSecStart, $wbSecEnd);

        $moneyFmt  = '#,##0.00';
        $pctFmt    = '0.00%';
        $dataStart = $headerRow2 + 1;
        foreach ($weeklyDefs as $i => $def) {
            $ci = $anc + $i;
            $colLtr = Coordinate::stringFromColumnIndex($ci);
            if (in_array($def['field'] ?? '', ['sales', 'spend', 'return_amount', 'return'], true)
                || ($def['type'] === 'weekly_platform' && in_array($def['field'], ['sales', 'return'], true))) {
                $sheet->getStyle("{$colLtr}{$dataStart}:{$colLtr}{$wbSecEnd}")->getNumberFormat()->setFormatCode($moneyFmt);
            }
            if (in_array($def['field'] ?? '', ['return_qty_pct', 'return_amount_pct'], true)) {
                $sheet->getStyle("{$colLtr}{$dataStart}:{$colLtr}{$wbSecEnd}")->getNumberFormat()->setFormatCode($pctFmt);
            }
        }
        }

        if ($includeDailyReport && count($dailyDefs) > 0 && $lastMainRow > 0) {
            foreach ($dailyDefs as $i => $def) {
                $ci     = $i + 1;
                $colLtr = Coordinate::stringFromColumnIndex($ci);
                if ($def['type'] === 'fixed' && in_array($def['field'], ['daily_sales', 'daily_spend'], true)) {
                    $sheet->getStyle("{$colLtr}{$dataStartRow}:{$colLtr}{$lastMainRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                }
                if ($def['type'] === 'platform') {
                    $sheet->getStyle("{$colLtr}{$dataStartRow}:{$colLtr}{$lastMainRow}")->getNumberFormat()->setFormatCode($moneyFmt);
                }
                if (($def['field'] ?? '') === 'daily_roas') {
                    $sheet->getStyle("{$colLtr}{$dataStartRow}:{$colLtr}{$dataEndRow}")->getNumberFormat()->setFormatCode('0.00%');
                }
            }
            $mainRange = 'A1:' . Coordinate::stringFromColumnIndex($mainLastCol) . $lastMainRow;
            $sheet->getStyle($mainRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        if ($includeDailyReport && count($dailyDefs) > 0) {
            for ($ci = 1; $ci <= $mainLastCol; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth($ci <= 2 ? 14 : 12);
            }
            $freezeCol = max(1, $this->defColumnIndex($dailyDefs, 'date') ?? 2);
            $sheet->freezePane(Coordinate::stringFromColumnIndex($freezeCol) . $dataStartRow);
        }
        if (($includeReturnBreakdown && count($returnDefs) > 0) || ($includeWeeklyBreakdown && count($weeklyDefs) > 0)) {
            $secLastCol = max($wbLastCol, $retLastCol);
            if ($secLastCol >= $anc) {
                $sheet->getColumnDimensionByColumn($anc)->setWidth(18);
                for ($ci = $anc + 1; $ci <= $secLastCol; $ci++) {
                    $sheet->getColumnDimensionByColumn($ci)->setWidth(12);
                }
            }
        }
        if ($includeReturnBreakdown && count($returnDefs) > 0 && $retSecStart > 0) {
            $sheet->getRowDimension($retSecStart)->setRowHeight(22);
        }
        if ($includeWeeklyBreakdown && count($weeklyDefs) > 0 && $wbSecStart > 0) {
            $sheet->getRowDimension($wbSecStart)->setRowHeight(22);
        }

        // Write the 3-row app header at the top (rows 1-3)
        $headerMaxCol = max($mainLastCol, $wbLastCol, $retLastCol);
        if ($headerMaxCol < 1) $headerMaxCol = 10;
        $headerEndCol = Coordinate::stringFromColumnIndex($headerMaxCol);
        $this->applyHeaderRows(
            $sheet,
            $headerEndCol,
            'Tracking Digital Marketing COST VS Allocation – ' . ($label['label'] ?? '')
        );

        Log::info('DashboardAnalyticsExport: writeSheetData() completed successfully', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'label'     => $label,
        ]);

        } catch (\Throwable $e) {
            Log::error('DashboardAnalyticsExport: writeSheetData() failed', [
                'error'     => $e->getMessage(),
                'class'     => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'label'     => $label,
            ]);
            throw $e;
        }
    }

    private function defColumnIndex(array $defs, string $key): ?int
    {
        foreach ($defs as $i => $def) {
            if ($def['key'] === $key) {
                return $i + 1;
            }
        }

        return null;
    }

    private function writeDailyHeaders($sheet, array $defs, int $row1): int
    {
        $hasSub = false;
        foreach ($defs as $def) {
            if ($def['sub'] !== null) {
                $hasSub = true;
                break;
            }
        }
        $row2 = $hasSub ? $row1 + 1 : $row1;

        foreach ($defs as $i => $def) {
            $ci = $i + 1;
            if ($def['sub'] === null) {
                $sheet->setCellValueByColumnAndRow($ci, $row1, $def['header']);
                if ($hasSub) {
                    $col = Coordinate::stringFromColumnIndex($ci);
                    $sheet->mergeCells("{$col}{$row1}:{$col}{$row2}");
                }
                $this->applyHeaderStyle($sheet, Coordinate::stringFromColumnIndex($ci) . $row1 . ':' . Coordinate::stringFromColumnIndex($ci) . $row2);
            } else {
                $sheet->setCellValueByColumnAndRow($ci, $row2, $def['sub']);
                $isSummary = ($def['kind'] ?? '') === 'summary';
                $hdrBg     = $isSummary ? self::CLR_HDR_BG : self::CLR_COLLABEL;
                $fg        = $isSummary ? 'FFFFFFFF' : self::CLR_COLLABEL_FG;
                $sheet->getStyleByColumnAndRow($ci, $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($hdrBg);
                $sheet->getStyleByColumnAndRow($ci, $row2)->getFont()->setBold(true)->getColor()->setARGB($fg);
                $sheet->getStyleByColumnAndRow($ci, $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        }

        if ($hasSub) {
            $this->finalizeSubHeaderGroups($sheet, $defs, $row1, fn (array $def) => ($def['type'] ?? '') === 'platform');
        }

        for ($hr = $row1; $hr <= $row2; $hr++) {
            $sheet->getRowDimension($hr)->setRowHeight(28);
        }

        return $row2;
    }

    private function finalizeSubHeaderGroups(
        $sheet,
        array $defs,
        int $row1,
        callable $usePlatformStyle,
        ?callable $columnIndexFor = null,
        ?callable $afterGroupStyle = null,
    ): void {
        $columnIndexFor ??= fn (int $i) => $i + 1;

        $groupStart = null;
        $groupEnd   = null;
        $prevHead   = null;
        $groupDef   = null;

        $flush = function () use ($sheet, $row1, $usePlatformStyle, $afterGroupStyle, &$groupStart, &$groupEnd, &$groupDef) {
            if ($groupStart === null || $groupEnd === null) {
                return;
            }

            $startLtr = Coordinate::stringFromColumnIndex($groupStart);
            $endLtr   = Coordinate::stringFromColumnIndex($groupEnd);
            $range    = "{$startLtr}{$row1}:{$endLtr}{$row1}";

            if ($groupEnd > $groupStart) {
                $sheet->mergeCells($range);
            }

            if ($groupDef !== null && $usePlatformStyle($groupDef)) {
                $isSummary = ($groupDef['kind'] ?? '') === 'summary';
                $hdrBg     = $isSummary ? self::CLR_HDR_BG : self::CLR_PLAT_BG;
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($hdrBg);
                $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            } else {
                $this->applyHeaderStyle($sheet, $range);
            }

            if ($groupDef !== null && $afterGroupStyle !== null) {
                $afterGroupStyle($sheet, $range, $groupDef);
            }

            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        };

        foreach ($defs as $i => $def) {
            if ($def['sub'] === null) {
                continue;
            }

            $ci = $columnIndexFor($i);
            if ($def['header'] !== $prevHead) {
                $flush();
                $groupStart = $ci;
                $groupDef   = $def;
                $prevHead   = $def['header'];
                $sheet->setCellValueByColumnAndRow($ci, $row1, $def['header']);
            }
            $groupEnd = $ci;
        }

        $flush();
    }

    private function dailyColumnAlignment(array $def): string
    {
        if (($def['key'] ?? '') === 'week') {
            return Alignment::HORIZONTAL_CENTER;
        }

        if (($def['type'] ?? '') === 'fixed' && ($def['field'] ?? '') === 'date') {
            return Alignment::HORIZONTAL_LEFT;
        }

        return Alignment::HORIZONTAL_RIGHT;
    }

    private function dailyDataValue(array $def, array $row): mixed
    {
        return match ($def['type']) {
            'fixed' => match ($def['field']) {
                'date'        => Carbon::parse($row['date'])->format('d-M-Y'),
                'daily_sales' => round((float) $row['total_sales'], 2),
                'daily_roas'  => $row['roas'],
                'daily_spend' => round((float) $row['total_spent'], 2),
                default       => null,
            },
            'platform' => $this->platformColumnValue($def, $row),
            'order_qty_root'  => $row['root_groups'][$def['root_id']]['orders'] ?? 0,
            'order_qty_total' => $row['total_orders'],
            'item_qty_root'   => $row['root_groups'][$def['root_id']]['qty'] ?? 0,
            'item_qty_total'  => $row['total_qty'],
            'gender'          => $row[$def['field']] ?? 0,
            default           => null,
        };
    }

    private function platformColumnValue(array $def, array $row): float
    {
        $val = 0.0;
        if ($def['kind'] === 'leaf') {
            $pid = $def['platform_id'];
            $val = $def['col_type'] === 'cost'
                ? ($row['platform'][$pid]['cost'] ?? 0)
                : ($row['platform'][$pid]['sales'] ?? 0);
        } else {
            foreach ($def['leaf_ids'] as $leafId) {
                $val += $def['col_type'] === 'cost'
                    ? ($row['platform'][$leafId]['cost'] ?? 0)
                    : ($row['platform'][$leafId]['sales'] ?? 0);
            }
        }

        return round((float) $val, 2);
    }

    private function dailySummaryCell(
        array $def,
        array $sRow,
        string $rowKey,
        array $allPlatCols,
        bool $useSumFormulas,
        int $dataStartRow,
        int $dataEndRow,
        int $colIndex,
    ): ?array {
        $excelCol = Coordinate::stringFromColumnIndex($colIndex);

        if ($def['type'] === 'fixed') {
            if ($def['field'] === 'date') {
                return ['value' => $sRow['label']];
            }
            if ($def['field'] === 'daily_sales' && $sRow['col_c'] !== null) {
                if ($useSumFormulas && $rowKey === 'total_sale') {
                    return ['formula' => "=SUM({$excelCol}{$dataStartRow}:{$excelCol}{$dataEndRow})"];
                }

                return ['value' => round((float) $sRow['col_c'], 2)];
            }
            if ($def['field'] === 'daily_spend' && $sRow['col_e'] !== null) {
                $isPercent = !empty($sRow['col_e_format']) && str_contains($sRow['col_e_format'], '%');
                if ($useSumFormulas && $rowKey === 'total_spend' && !$isPercent) {
                    return ['formula' => "=SUM({$excelCol}{$dataStartRow}:{$excelCol}{$dataEndRow})"];
                }

                return [
                    'value'  => $isPercent ? (float) $sRow['col_e'] : round((float) $sRow['col_e'], 2),
                    'format' => $sRow['col_e_format'] ?? null,
                ];
            }

            return null;
        }

        if ($def['type'] === 'platform') {
            $colKey = "{$def['platform_id']}_{$def['col_type']}";
            if ($useSumFormulas && in_array($rowKey, ['total_sale', 'total_spend'], true)) {
                return ['formula' => "=SUM({$excelCol}{$dataStartRow}:{$excelCol}{$dataEndRow})"];
            }
            if ($def['kind'] === 'summary') {
                $platCol = [
                    'platform_id' => $def['platform_id'],
                    'col_type'    => $def['col_type'],
                    'leaf_ids'    => $def['leaf_ids'],
                ];
                $val = $this->summaryPlatformValue($sRow, $platCol, $rowKey);
                if ($val == 0) {
                    return null;
                }

                return ['value' => round((float) $val, 2)];
            }
            if (!isset($sRow['platform'][$colKey])) {
                return null;
            }
            $isPercent = !empty($sRow['platform_formats'][$colKey]) && str_contains($sRow['platform_formats'][$colKey], '%');

            return [
                'value'  => $isPercent ? (float) $sRow['platform'][$colKey] : round((float) $sRow['platform'][$colKey], 2),
                'format' => $sRow['platform_formats'][$colKey] ?? null,
            ];
        }

        if ($useSumFormulas && in_array($rowKey, ['total_sale', 'total_spend'], true)) {
            return ['formula' => "=SUM({$excelCol}{$dataStartRow}:{$excelCol}{$dataEndRow})"];
        }

        return match ($def['type']) {
            'order_qty_root'  => !empty($sRow['root_orders'][$def['root_id']]) ? ['value' => round((float) $sRow['root_orders'][$def['root_id']], 2)] : null,
            'order_qty_total' => !empty($sRow['total_orders']) ? ['value' => round((float) $sRow['total_orders'], 2)] : null,
            'item_qty_root'   => !empty($sRow['root_qty'][$def['root_id']]) ? ['value' => round((float) $sRow['root_qty'][$def['root_id']], 2)] : null,
            'item_qty_total'  => !empty($sRow['total_qty']) ? ['value' => round((float) $sRow['total_qty'], 2)] : null,
            'gender'          => $sRow[$def['field']] !== null ? ['value' => round((float) $sRow[$def['field']], 2)] : null,
            default           => null,
        };
    }

    private function returnHeaderLabel(array $def): string
    {
        return match ($def['type']) {
            'reason'           => 'Reason',
            'return_root_qty'  => $def['header'],
            'return_root_pct'  => '%' . $def['header'],
            'return_gender'    => $def['sub'] ?? $def['header'],
            'return_total_qty' => 'Total',
            'return_total_pct' => '% Total',
            default            => $def['sub'] ?? $def['header'],
        };
    }

    private function returnDataValue(array $def, array $reason, float $grandTotal): mixed
    {
        $reasonTotal = array_sum($reason['by_root']);

        return match ($def['type']) {
            'reason'           => $reason['name'],
            'return_root_qty'  => $reason['by_root'][$def['root_id']] ?? 0,
            'return_root_pct'  => $grandTotal > 0 ? ($reason['by_root'][$def['root_id']] ?? 0) / $grandTotal : 0,
            'return_gender'    => $reason[$def['field']] ?? 0,
            'return_total_qty' => $reasonTotal,
            'return_total_pct' => $grandTotal > 0 ? $reasonTotal / $grandTotal : 0,
            default            => null,
        };
    }

    private function returnTotalValue(array $def, array $payload, float $grandTotal): mixed
    {
        return match ($def['type']) {
            'reason'           => 'Total',
            'return_root_qty'  => $payload['totals_by_root'][$def['root_id']] ?? 0,
            'return_root_pct'  => $grandTotal > 0 ? ($payload['totals_by_root'][$def['root_id']] ?? 0) / $grandTotal : 0,
            'return_gender'    => $payload['totals_' . $def['field']] ?? 0,
            'return_total_qty' => $grandTotal,
            'return_total_pct' => $grandTotal > 0 ? 1 : 0,
            default            => null,
        };
    }

    private function writeWeeklyHeaders($sheet, array $defs, int $startCol, int $row1, array $rootPlatforms): int
    {
        $hasSub = false;
        foreach ($defs as $def) {
            if ($def['sub'] !== null) {
                $hasSub = true;
                break;
            }
        }
        $row2 = $hasSub ? $row1 + 1 : $row1;

        $rootColorIndex = [];
        foreach ($rootPlatforms as $idx => $root) {
            $rootColorIndex[$root['id']] = $idx;
        }

        foreach ($defs as $i => $def) {
            $ci = $startCol + $i;
            if ($def['sub'] === null) {
                $col = Coordinate::stringFromColumnIndex($ci);
                $sheet->setCellValue($col . $row1, $def['header']);
                if ($hasSub) {
                    $sheet->mergeCells("{$col}{$row1}:{$col}{$row2}");
                }
                $this->applyHeaderStyle($sheet, "{$col}{$row1}:{$col}{$row2}");
            } else {
                $sheet->setCellValueByColumnAndRow($ci, $row2, $def['sub']);
                $colorIdx  = $rootColorIndex[$def['root_id'] ?? -1] ?? 0;
                $groupFill = self::PLATFORM_COLORS[$colorIdx % count(self::PLATFORM_COLORS)];
                $this->applyHeaderStyle($sheet, Coordinate::stringFromColumnIndex($ci) . $row2);
                $sheet->getStyleByColumnAndRow($ci, $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupFill);
                $sheet->getStyleByColumnAndRow($ci, $row2)->getFont()->getColor()->setARGB('FF000000');
            }
        }

        if ($hasSub) {
            $this->finalizeSubHeaderGroups(
                $sheet,
                $defs,
                $row1,
                fn (array $def) => false,
                fn (int $i) => $startCol + $i,
                function ($sheet, string $range, array $groupDef) use ($rootColorIndex) {
                    if (($groupDef['type'] ?? '') !== 'weekly_platform') {
                        return;
                    }
                    $colorIdx  = $rootColorIndex[$groupDef['root_id'] ?? -1] ?? 0;
                    $groupFill = self::PLATFORM_COLORS[$colorIdx % count(self::PLATFORM_COLORS)];
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupFill);
                    $sheet->getStyle($range)->getFont()->getColor()->setARGB('FF000000');
                },
            );
        }

        return $row2;
    }

    private function weeklyDataValue(
        array $def,
        array $wRow,
        array $weeklySalesByRoot,
        array $weeklyReturnsByRoot,
        float $sales,
        float $spend,
        float $weekOrders,
        float $weekItems,
        float $retPcs,
        float $retGbp,
    ): mixed {
        if ($def['type'] === 'weekly_fixed') {
            return match ($def['field']) {
                'week'              => $wRow['label'],
                'sales'             => round($sales, 2),
                'spend'             => round($spend, 2),
                'order'             => $weekOrders,
                'order_qty'         => $weekItems,
                'return_qty'        => $retPcs,
                'return_qty_pct'    => $weekItems > 0 ? $retPcs / $weekItems : 0,
                'return_amount'     => round($retGbp, 2),
                'return_amount_pct' => $sales > 0 ? $retGbp / $sales : 0,
                default             => null,
            };
        }

        $rid = $def['root_id'];
        $wk  = $wRow['week'];

        return match ($def['field']) {
            'sales'      => round((float) ($weeklySalesByRoot[$rid] ?? 0), 2),
            'orders'     => (float) ($wRow['root_orders'][$rid] ?? 0),
            'qty'        => (float) ($wRow['root_qty'][$rid] ?? 0),
            'return'     => round((float) ($weeklyReturnsByRoot[$rid]['amount'] ?? 0), 2),
            'ret_orders' => (float) ($weeklyReturnsByRoot[$rid]['order_qty'] ?? 0),
            'ret_qty'    => (float) ($weeklyReturnsByRoot[$rid]['item_qty'] ?? 0),
            default      => null,
        };
    }

    private function weeklyTotalValue(
        array $def,
        float $totalSales,
        float $totalSpend,
        float $totalOrders,
        float $totalItems,
        float $totalRetPcs,
        float $totalRetGbp,
        float $pctTotalRetPcs,
        float $pctTotalRetGbp,
        array $platformTotals,
    ): mixed {
        if ($def['type'] === 'weekly_fixed') {
            return match ($def['field']) {
                'week'              => 'Total',
                'sales'             => round($totalSales, 2),
                'spend'             => round($totalSpend, 2),
                'order'             => $totalOrders,
                'order_qty'         => $totalItems,
                'return_qty'        => $totalRetPcs,
                'return_qty_pct'    => $pctTotalRetPcs,
                'return_amount'     => round($totalRetGbp, 2),
                'return_amount_pct' => $pctTotalRetGbp,
                default             => null,
            };
        }

        $totals = $platformTotals[$def['root_id']] ?? [];
        $val    = $totals[$def['field']] ?? 0;

        return in_array($def['field'], ['sales', 'return'], true) ? round((float) $val, 2) : $val;
    }

    private function applyHeaderRows($sheet, string $endCol, string $title): void
    {
        $appName = config('app.name', 'ENOX ERP');
        foreach ([$appName, $title, 'Generated: ' . now()->format('d M Y H:i')] as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$endCol}{$row}");
        }
        $sheet->getStyle("A1:{$endCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(18)->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(22);
    }

    private function styleTitle($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(13)->getColor()->setARGB(self::CLR_TITLE_FG);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(6)->setRowHeight(26);
    }

    private function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HDR_BG);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_HDR_FG);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function summaryPlatformValue(array $sRow, array $platCol, string $rowKey): float
    {
        $val = 0.0;
        foreach ($platCol['leaf_ids'] as $leafId) {
            $lk = "{$leafId}_{$platCol['col_type']}";
            if (isset($sRow['platform'][$lk])) {
                $val += (float) $sRow['platform'][$lk];
            }
        }

        if ($platCol['col_type'] === 'cost'
            && $rowKey === 'total_budget'
            && !empty($sRow['parent_budget'][$platCol['platform_id']])) {
            $val += (float) $sRow['parent_budget'][$platCol['platform_id']];
        }

        return $val;
    }

    private function fillRow($sheet, int $row, int $lastColIdx, string $argb): void
    {
        $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex($lastColIdx) . $row)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    private function fillSecRange($sheet, int $colStart, int $colEnd, int $row, string $argb, bool $bold = false): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $row . ':' . Coordinate::stringFromColumnIndex($colEnd) . $row;
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        if ($bold) $sheet->getStyle($range)->getFont()->setBold(true);
    }

    private function writeSectionTitle($sheet, int $colStart, int $colEnd, int $row, string $title): void
    {
        $startLtr = Coordinate::stringFromColumnIndex($colStart);
        $endLtr   = Coordinate::stringFromColumnIndex($colEnd);
        $range    = "{$startLtr}{$row}:{$endLtr}{$row}";
        $sheet->setCellValue($startLtr . $row, $title);
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SEC_TITLE);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function applySecHdrTextStyle($sheet, int $colStart, int $colEnd, int $row): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $row . ':' . Coordinate::stringFromColumnIndex($colEnd) . $row;
        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function alignSecRow($sheet, int $colStart, int $colEnd, int $row): void
    {
        $sheet->getStyleByColumnAndRow($colStart, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        if ($colEnd > $colStart) {
            $range = Coordinate::stringFromColumnIndex($colStart + 1) . $row . ':' . Coordinate::stringFromColumnIndex($colEnd) . $row;
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    private function sectionBorder($sheet, int $colStart, int $colEnd, int $rowStart, int $rowEnd): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $rowStart . ':' . Coordinate::stringFromColumnIndex($colEnd) . $rowEnd;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function shortName(string $name): string
    {
        $name = trim(preg_replace('/\s*(platform|marketplace|store)\s*/i', '', $name));
        return mb_strlen($name) > 10 ? mb_substr($name, 0, 9) . '.' : $name;
    }

    /** Excel sheet titles cannot contain * : / \ ? [ ] and are limited to 31 characters. */
    private function sanitizeSheetTitle(string $title): string
    {
        $title = str_replace(['*', ':', '/', '\\', '?', '[', ']'], ' ', $title);
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
        $title = mb_substr($title, 0, 31);

        return $title !== '' ? $title : 'Report';
    }

    // Build grouped platform columns

    private function buildGroupedColumns(array $tree, int $depth = 0): array
    {
        $cols = [];

        foreach ($tree as $node) {
            if (!empty($node['children'])) {
                $leafIds = [];
                $this->collectLeafIdsFromTree($node['children'], $leafIds);

                if ($node['is_spent']) {
                    $cols[] = ['kind' => 'summary', 'platform_id' => $node['id'], 'col_type' => 'cost',
                                'level' => $depth, 'name' => $node['name'], 'leaf_ids' => $leafIds, 'visible' => true, 'collapsed' => false];
                }
                if ($node['is_sales']) {
                    $cols[] = ['kind' => 'summary', 'platform_id' => $node['id'], 'col_type' => 'sales',
                                'level' => $depth, 'name' => $node['name'], 'leaf_ids' => $leafIds, 'visible' => true, 'collapsed' => false];
                }

                $childCols = $this->buildGroupedColumns($node['children'], $depth + 1);
                if (!empty($childCols)) {
                    $childCols[0]['collapsed'] = true;
                }
                $cols = array_merge($cols, $childCols);
            } else {
                if ($node['is_spent']) {
                    $cols[] = ['kind' => 'leaf', 'platform_id' => $node['id'], 'col_type' => 'cost',
                                'level' => $depth, 'name' => $node['name'], 'leaf_ids' => [$node['id']], 'visible' => ($depth === 0), 'collapsed' => false];
                }
                if ($node['is_sales']) {
                    $cols[] = ['kind' => 'leaf', 'platform_id' => $node['id'], 'col_type' => 'sales',
                                'level' => $depth, 'name' => $node['name'], 'leaf_ids' => [$node['id']], 'visible' => ($depth === 0), 'collapsed' => false];
                }
            }
        }

        return $cols;
    }

    // Collect leaf ids
    private function collectLeafIdsFromTree(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            if (empty($node['children'])) {
                $ids[] = $node['id'];
            } else {
                $this->collectLeafIdsFromTree($node['children'], $ids);
            }
        }
    }
}

