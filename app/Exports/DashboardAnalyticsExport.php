<?php

namespace App\Exports;

use App\Models\DailyReturn;
use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ) {}

    public function download(DashboardAnalyticsService $service): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        if (count($this->months) > 1) {
            foreach ($this->months as $month) {
                $monthCarbon = Carbon::createFromDate($month['year'], $month['month'], 1);
                $monthStart  = $monthCarbon->copy()->startOfMonth()->toDateString();
                $monthEnd    = $monthCarbon->copy()->endOfMonth()->toDateString();

                if ($monthStart < $this->dateFrom) $monthStart = $this->dateFrom;
                if ($monthEnd   > $this->dateTo)   $monthEnd   = $this->dateTo;

                $monthTitle = $monthCarbon->format('M-Y');
                $sheet      = $spreadsheet->createSheet();
                $sheet->setTitle($monthTitle);

                $this->writeSheetData($sheet, $service, $monthStart, $monthEnd, [$month], ['label' => $monthTitle]);
            }
        } else {
            $sheetTitle = mb_substr($this->label['label'] ?? 'Report', 0, 31);
            $sheet      = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);
            $this->writeSheetData($sheet, $service, $this->dateFrom, $this->dateTo, $this->months, $this->label);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'analytics-'
            . str_replace(' ', '_', strtolower($this->label['label'] ?? 'report'))
            . '-' . now()->format('Y-m-d') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function writeSheetData(
        $sheet,
        DashboardAnalyticsService $service,
        string $dateFrom,
        string $dateTo,
        array  $months,
        array  $label,
    ): void {
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

        $platBaseCol    = self::COL_SPEND + 1;
        $allPlatCols    = $this->buildGroupedColumns($tree);
        $numAllPlatCols = count($allPlatCols);

        $platColMap = [];
        foreach ($allPlatCols as $i => $col) {
            if ($col['kind'] === 'leaf') {
                $platColMap["{$col['platform_id']}_{$col['col_type']}"] = $platBaseCol + $i;
            }
        }

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

        $rsBase          = $platBaseCol + $numAllPlatCols;
        $rsRootOrderBase = $rsBase;
        $rsOrdersCol     = $rsBase + $numRoots;
        $rsQtyRootBase   = $rsBase + $numRoots + 1;
        $rsQtyCol        = $rsBase + 2 * $numRoots + 1;
        $rsKidsCol       = $rsBase + 2 * $numRoots + 2;
        $rsFemaleCol     = $rsBase + 2 * $numRoots + 3;
        $rsMaleCol       = $rsBase + 2 * $numRoots + 4;
        $mainLastCol     = $rsMaleCol;

        $rsRootOrderCols = [];
        $rsRootQtyCols   = [];
        foreach ($rootPlatforms as $i => $root) {
            $rsRootOrderCols[$root['id']] = $rsRootOrderBase + $i;
            $rsRootQtyCols[$root['id']]   = $rsQtyRootBase   + $i;
        }

        $firstHdrRow  = 7;
        $colLabelRow  = $numAllPlatCols > 0 ? $firstHdrRow + 1 : $firstHdrRow;
        $dataStartRow = $colLabelRow + 1;

        if ($includeDailyReport) {
        $titleStr      = 'Tracking Digital Marketing COST VS Allocation – ' . ($label['label'] ?? '');
        $titleStartCol = Coordinate::stringFromColumnIndex(self::COL_SALES);
        $titleEndCol   = Coordinate::stringFromColumnIndex($mainLastCol);
        $sheet->setCellValue($titleStartCol . '6', $titleStr);
        $sheet->mergeCells("{$titleStartCol}6:{$titleEndCol}6");
        $this->styleTitle($sheet, "{$titleStartCol}6:{$titleEndCol}6");
        foreach (['A6', 'B6'] as $cell) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_ACCENT);
        }

        foreach ([
            self::COL_WEEK  => 'Week',
            self::COL_DATE  => 'Date',
            self::COL_SALES => 'Daily Sales',
            self::COL_ROAS  => 'Daily ROAS',
            self::COL_SPEND => 'Daily Spend',
        ] as $ci => $lbl) {
            $cl = Coordinate::stringFromColumnIndex($ci);
            $sheet->setCellValue($cl . $firstHdrRow, $lbl);
            if ($colLabelRow > $firstHdrRow) {
                $sheet->mergeCells("{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
            }
            $this->applyHeaderStyle($sheet, "{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
        }

        $sheet->setShowSummaryRight(false);

        if ($numAllPlatCols > 0) {
            $prevPlatId = null;
            $mergeStart = null;

            foreach ($allPlatCols as $i => $col) {
                $ci        = $platBaseCol + $i;
                $cl        = Coordinate::stringFromColumnIndex($ci);
                $platId    = $col['platform_id'];
                $isSummary = $col['kind'] === 'summary';

                if ($platId !== $prevPlatId) {
                    if ($mergeStart !== null && $mergeStart < $ci - 1) {
                        $sheet->mergeCells(Coordinate::stringFromColumnIndex($mergeStart) . $firstHdrRow . ':' . Coordinate::stringFromColumnIndex($ci - 1) . $firstHdrRow);
                    }
                    $sheet->setCellValueByColumnAndRow($ci, $firstHdrRow, $col['name']);
                    $hdrBg = $isSummary ? self::CLR_HDR_BG : self::CLR_PLAT_BG;
                    $sheet->getStyleByColumnAndRow($ci, $firstHdrRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($hdrBg);
                    $sheet->getStyleByColumnAndRow($ci, $firstHdrRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $sheet->getStyleByColumnAndRow($ci, $firstHdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                    $mergeStart = $ci;
                    $prevPlatId = $platId;
                } else {
                    $hdrBg = $isSummary ? self::CLR_HDR_BG : self::CLR_PLAT_BG;
                    $sheet->getStyleByColumnAndRow($ci, $firstHdrRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($hdrBg);
                    $sheet->getStyleByColumnAndRow($ci, $firstHdrRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                }

                $sheet->setCellValueByColumnAndRow($ci, $colLabelRow, $col['col_type'] === 'cost' ? 'Spend' : 'Sales');
                $sheet->getStyleByColumnAndRow($ci, $colLabelRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_COLLABEL);
                $sheet->getStyleByColumnAndRow($ci, $colLabelRow)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_COLLABEL_FG);
                $sheet->getStyleByColumnAndRow($ci, $colLabelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getColumnDimensionByColumn($ci)->setOutlineLevel($col['level']);
            }

            if ($mergeStart !== null) {
                $lastCi = $platBaseCol + $numAllPlatCols - 1;
                if ($mergeStart < $lastCi) {
                    $sheet->mergeCells(Coordinate::stringFromColumnIndex($mergeStart) . $firstHdrRow . ':' . Coordinate::stringFromColumnIndex($lastCi) . $firstHdrRow);
                }
            }
        }

        if ($numRoots > 0) {
            $orderStartLtr = Coordinate::stringFromColumnIndex($rsRootOrderBase);
            $orderEndLtr   = Coordinate::stringFromColumnIndex($rsOrdersCol);
            $sheet->setCellValue($orderStartLtr . $firstHdrRow, 'Order QTY');
            $sheet->mergeCells("{$orderStartLtr}{$firstHdrRow}:{$orderEndLtr}{$firstHdrRow}");
            $this->applyHeaderStyle($sheet, "{$orderStartLtr}{$firstHdrRow}:{$orderEndLtr}{$firstHdrRow}");
            foreach ($rootPlatforms as $root) {
                $cl = Coordinate::stringFromColumnIndex($rsRootOrderCols[$root['id']]);
                $sheet->setCellValue($cl . $colLabelRow, $this->shortName($root['name']));
                $this->applyHeaderStyle($sheet, "{$cl}{$colLabelRow}:{$cl}{$colLabelRow}");
            }
            $totalOrderLtr = Coordinate::stringFromColumnIndex($rsOrdersCol);
            $sheet->setCellValue($totalOrderLtr . $colLabelRow, 'Total');
            $this->applyHeaderStyle($sheet, "{$totalOrderLtr}{$colLabelRow}:{$totalOrderLtr}{$colLabelRow}");

            $itemStartLtr = Coordinate::stringFromColumnIndex($rsQtyRootBase);
            $itemEndLtr   = Coordinate::stringFromColumnIndex($rsQtyCol);
            $sheet->setCellValue($itemStartLtr . $firstHdrRow, 'Order Item QTY');
            $sheet->mergeCells("{$itemStartLtr}{$firstHdrRow}:{$itemEndLtr}{$firstHdrRow}");
            $this->applyHeaderStyle($sheet, "{$itemStartLtr}{$firstHdrRow}:{$itemEndLtr}{$firstHdrRow}");
            foreach ($rootPlatforms as $root) {
                $cl = Coordinate::stringFromColumnIndex($rsRootQtyCols[$root['id']]);
                $sheet->setCellValue($cl . $colLabelRow, $this->shortName($root['name']));
                $this->applyHeaderStyle($sheet, "{$cl}{$colLabelRow}:{$cl}{$colLabelRow}");
            }
            $totalQtyLtr = Coordinate::stringFromColumnIndex($rsQtyCol);
            $sheet->setCellValue($totalQtyLtr . $colLabelRow, 'Total');
            $this->applyHeaderStyle($sheet, "{$totalQtyLtr}{$colLabelRow}:{$totalQtyLtr}{$colLabelRow}");
        }

        $genderStartLtr = Coordinate::stringFromColumnIndex($rsKidsCol);
        $genderEndLtr   = Coordinate::stringFromColumnIndex($rsMaleCol);
        $sheet->setCellValue($genderStartLtr . $firstHdrRow, 'Gender Order QTY');
        $sheet->mergeCells("{$genderStartLtr}{$firstHdrRow}:{$genderEndLtr}{$firstHdrRow}");
        $this->applyHeaderStyle($sheet, "{$genderStartLtr}{$firstHdrRow}:{$genderEndLtr}{$firstHdrRow}");
        $sheet->setCellValue($genderStartLtr . $colLabelRow, 'Kids');
        $this->applyHeaderStyle($sheet, "{$genderStartLtr}{$colLabelRow}:{$genderStartLtr}{$colLabelRow}");
        $femaleLtr = Coordinate::stringFromColumnIndex($rsFemaleCol);
        $sheet->setCellValue($femaleLtr . $colLabelRow, 'Female');
        $this->applyHeaderStyle($sheet, "{$femaleLtr}{$colLabelRow}:{$femaleLtr}{$colLabelRow}");
        $maleLtr = Coordinate::stringFromColumnIndex($rsMaleCol);
        $sheet->setCellValue($maleLtr . $colLabelRow, 'Male');
        $this->applyHeaderStyle($sheet, "{$maleLtr}{$colLabelRow}:{$maleLtr}{$colLabelRow}");

        for ($hr = $firstHdrRow; $hr <= $colLabelRow; $hr++) {
            $sheet->getRowDimension($hr)->setRowHeight(28);
        }

        $r          = $dataStartRow;
        $weekRanges = [];
        $prevWeek   = null;

        foreach ($rows as $row) {
            $weekNum = $row['week'];
            if ($weekNum !== $prevWeek) {
                $sheet->setCellValue('A' . $r, 'Week ' . $weekNum);
                $weekRanges[$weekNum] = ['start' => $r, 'end' => $r];
                $prevWeek = $weekNum;
            } else {
                $weekRanges[$weekNum]['end'] = $r;
            }

            $sheet->setCellValue('B' . $r, Carbon::parse($row['date'])->format('d-M-Y'));
            $sheet->setCellValue('C' . $r, round((float) $row['total_sales'], 2));
            $sheet->setCellValue('D' . $r, $row['roas']);
            $sheet->setCellValue('E' . $r, round((float) $row['total_spent'], 2));

            foreach ($allPlatCols as $i => $platCol) {
                $ci  = $platBaseCol + $i;
                $val = 0;
                if ($platCol['kind'] === 'leaf') {
                    $pid = $platCol['platform_id'];
                    $val = $platCol['col_type'] === 'cost'
                        ? ($row['platform'][$pid]['cost']  ?? 0)
                        : ($row['platform'][$pid]['sales'] ?? 0);
                } else {
                    foreach ($platCol['leaf_ids'] as $leafId) {
                        $val += $platCol['col_type'] === 'cost'
                            ? ($row['platform'][$leafId]['cost']  ?? 0)
                            : ($row['platform'][$leafId]['sales'] ?? 0);
                    }
                }
                $sheet->setCellValueByColumnAndRow($ci, $r, round((float) $val, 2));
            }

            foreach ($rootPlatforms as $root) {
                $rid = $root['id'];
                $sheet->setCellValueByColumnAndRow($rsRootOrderCols[$rid], $r, $row['root_groups'][$rid]['orders'] ?? 0);
                $sheet->setCellValueByColumnAndRow($rsRootQtyCols[$rid],   $r, $row['root_groups'][$rid]['qty']    ?? 0);
            }
            $sheet->setCellValueByColumnAndRow($rsOrdersCol, $r, $row['total_orders']);
            $sheet->setCellValueByColumnAndRow($rsQtyCol,    $r, $row['total_qty']);
            $sheet->setCellValueByColumnAndRow($rsKidsCol,   $r, $row['kids']);
            $sheet->setCellValueByColumnAndRow($rsFemaleCol, $r, $row['female']);
            $sheet->setCellValueByColumnAndRow($rsMaleCol,   $r, $row['male']);

            if ($r % 2 === 0) {
                $this->fillRow($sheet, $r, $mainLastCol, self::CLR_ROW_ALT);
            }
            $sheet->getStyle('C' . $r . ':' . Coordinate::stringFromColumnIndex($mainLastCol) . $r)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r++;
        }
        $dataEndRow = $r - 1;

        foreach ($weekRanges as $wRange) {
            if ($wRange['end'] > $wRange['start']) {
                $sheet->mergeCells('A' . $wRange['start'] . ':A' . $wRange['end']);
            }
            $sheet->getStyle('A' . $wRange['start'])->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $wRange['start'])->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_WEEK);
            $sheet->getStyle('A' . $wRange['start'])->getFont()->setBold(true)
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
            $color = $summaryColorMap[$key] ?? self::CLR_WHITE;
            $sheet->setCellValue('B' . $r, $sRow['label']);
            $sheet->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            if ($sRow['col_c'] !== null) {
                $sheet->setCellValue('C' . $r, round((float) $sRow['col_c'], 2));
                $sheet->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            if ($sRow['col_e'] !== null) {
                $colEIsPercent = !empty($sRow['col_e_format']) && str_contains($sRow['col_e_format'], '%');
                $sheet->setCellValue('E' . $r, $colEIsPercent ? (float) $sRow['col_e'] : round((float) $sRow['col_e'], 2));
                $sheet->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if (!empty($sRow['col_e_format'])) {
                    $sheet->getStyle('E' . $r)->getNumberFormat()->setFormatCode($sRow['col_e_format']);
                }
            }

            foreach ($sRow['platform'] as $colKey => $value) {
                if (!isset($platColMap[$colKey])) continue;
                $ci = $platColMap[$colKey];
                $platIsPercent = !empty($sRow['platform_formats'][$colKey]) && str_contains($sRow['platform_formats'][$colKey], '%');
                $sheet->setCellValueByColumnAndRow($ci, $r, $platIsPercent ? (float) $value : round((float) $value, 2));
                $sheet->getStyleByColumnAndRow($ci, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if (!empty($sRow['platform_formats'][$colKey])) {
                    $sheet->getStyleByColumnAndRow($ci, $r)->getNumberFormat()->setFormatCode($sRow['platform_formats'][$colKey]);
                }
            }

            foreach ($allPlatCols as $i => $platCol) {
                if ($platCol['kind'] !== 'summary') continue;
                $ci  = $platBaseCol + $i;
                $val = 0;
                foreach ($platCol['leaf_ids'] as $leafId) {
                    $lk = "{$leafId}_{$platCol['col_type']}";
                    if (isset($sRow['platform'][$lk])) $val += $sRow['platform'][$lk];
                }
                if ($val != 0) {
                    $summaryIsPercent = false;
                    if (!empty($sRow['platform_formats'])) {
                        foreach ($platCol['leaf_ids'] as $leafId) {
                            $lk = "{$leafId}_{$platCol['col_type']}";
                            if (!empty($sRow['platform_formats'][$lk]) && str_contains($sRow['platform_formats'][$lk], '%')) {
                                $summaryIsPercent = true;
                                break;
                            }
                        }
                    }
                    $sheet->setCellValueByColumnAndRow($ci, $r, $summaryIsPercent ? (float) $val : round((float) $val, 2));
                    $sheet->getStyleByColumnAndRow($ci, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    if (!empty($sRow['platform_formats'])) {
                        foreach ($platCol['leaf_ids'] as $leafId) {
                            $lk = "{$leafId}_{$platCol['col_type']}";
                            if (!empty($sRow['platform_formats'][$lk])) {
                                $sheet->getStyleByColumnAndRow($ci, $r)->getNumberFormat()->setFormatCode($sRow['platform_formats'][$lk]);
                                break;
                            }
                        }
                    }
                }
            }

            if (!empty($sRow['total_orders'])) $sheet->setCellValueByColumnAndRow($rsOrdersCol, $r, round((float) $sRow['total_orders'], 2));
            if (!empty($sRow['root_orders'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_orders'][$rid], $rsRootOrderCols[$rid])) {
                        $sheet->setCellValueByColumnAndRow($rsRootOrderCols[$rid], $r, round((float) $sRow['root_orders'][$rid], 2));
                    }
                }
            }
            if (!empty($sRow['total_qty'])) $sheet->setCellValueByColumnAndRow($rsQtyCol, $r, round((float) $sRow['total_qty'], 2));
            if (!empty($sRow['root_qty'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_qty'][$rid], $rsRootQtyCols[$rid])) {
                        $sheet->setCellValueByColumnAndRow($rsRootQtyCols[$rid], $r, round((float) $sRow['root_qty'][$rid], 2));
                    }
                }
            }
            if ($sRow['kids']   !== null) $sheet->setCellValueByColumnAndRow($rsKidsCol,   $r, round((float) $sRow['kids'],   2));
            if ($sRow['female'] !== null) $sheet->setCellValueByColumnAndRow($rsFemaleCol,  $r, round((float) $sRow['female'], 2));
            if ($sRow['male']   !== null) $sheet->setCellValueByColumnAndRow($rsMaleCol,    $r, round((float) $sRow['male'],   2));

            $this->fillRow($sheet, $r, $mainLastCol, $color);
            $sheet->getStyle('B' . $r)->getFont()->setBold(true);

            if ($sRow['col_c'] !== null && (float) $sRow['col_c'] < 0) $sheet->getStyle('C' . $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            if ($sRow['col_e'] !== null && (float) $sRow['col_e'] < 0) $sheet->getStyle('E' . $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            foreach ($sRow['platform'] as $colKey => $value) {
                if (!isset($platColMap[$colKey])) continue;
                if ((float) $value < 0) {
                    $sheet->getStyleByColumnAndRow($platColMap[$colKey], $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
                }
            }
            foreach ($allPlatCols as $i => $platCol) {
                if ($platCol['kind'] !== 'summary') continue;
                $ci = $platBaseCol + $i;
                $val = 0;
                foreach ($platCol['leaf_ids'] as $leafId) {
                    $lk = "{$leafId}_{$platCol['col_type']}";
                    if (isset($sRow['platform'][$lk])) $val += $sRow['platform'][$lk];
                }
                if ((float) $val < 0) $sheet->getStyleByColumnAndRow($ci, $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            }
            if (!empty($sRow['root_orders'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_orders'][$rid], $rsRootOrderCols[$rid]) && (float) $sRow['root_orders'][$rid] < 0) {
                        $sheet->getStyleByColumnAndRow($rsRootOrderCols[$rid], $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
                    }
                }
            }
            if (!empty($sRow['total_qty']) && (float) $sRow['total_qty'] < 0) $sheet->getStyleByColumnAndRow($rsQtyCol, $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            if (!empty($sRow['root_qty'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_qty'][$rid], $rsRootQtyCols[$rid]) && (float) $sRow['root_qty'][$rid] < 0) {
                        $sheet->getStyleByColumnAndRow($rsRootQtyCols[$rid], $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
                    }
                }
            }
            if ($sRow['kids']   !== null && (float) $sRow['kids']   < 0) $sheet->getStyleByColumnAndRow($rsKidsCol,   $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            if ($sRow['female'] !== null && (float) $sRow['female'] < 0) $sheet->getStyleByColumnAndRow($rsFemaleCol,  $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);
            if ($sRow['male']   !== null && (float) $sRow['male']   < 0) $sheet->getStyleByColumnAndRow($rsMaleCol,    $r)->getFont()->getColor()->setARGB(self::CLR_NEGATIVE);

            $r++;
        }
        $lastMainRow = $r - 1;
        $r   += 4;
        } else {
            $r = 7;
        }

        $anc = 2;

        if ($includeReturnBreakdown) {
        $retLabelCol  = $anc;
        $retRootStart = $anc + 1;
        $retRootCols    = [];
        $retRootPctCols = [];
        foreach ($rootPlatforms as $i => $root) {
            $retRootCols[$root['id']]    = $retRootStart + $i * 2;
            $retRootPctCols[$root['id']] = $retRootStart + $i * 2 + 1;
        }
        $retKidsCol     = $retRootStart + $numRoots * 2;
        $retFemaleCol   = $retKidsCol + 1;
        $retMaleCol     = $retFemaleCol + 1;
        $retTotalCol    = $retMaleCol + 1;
        $retPctTotalCol = $retTotalCol + 1;
        $retLastCol     = $retPctTotalCol;

        $retGrandTotal = array_sum($returnReasonData['totals_by_root']);
        $retRootTotals = $returnReasonData['totals_by_root'];

        $retSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $retLastCol, $r, 'Return Breakdown');
        $r++;

        $sheet->setCellValueByColumnAndRow($retLabelCol, $r, 'Reason');
        foreach ($rootPlatforms as $root) {
            $sn = $this->shortName($root['name']);
            $sheet->setCellValueByColumnAndRow($retRootCols[$root['id']],    $r, $sn);
            $sheet->setCellValueByColumnAndRow($retRootPctCols[$root['id']], $r, '%' . $sn);
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,     $r, 'Kids');
        $sheet->setCellValueByColumnAndRow($retFemaleCol,   $r, 'Female');
        $sheet->setCellValueByColumnAndRow($retMaleCol,     $r, 'Male');
        $sheet->setCellValueByColumnAndRow($retTotalCol,    $r, 'Total');
        $sheet->setCellValueByColumnAndRow($retPctTotalCol, $r, '% Total');
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $anc, $retLastCol, $r);
        $r++;

        $pctFmt = '0.0%';
        foreach ($returnReasonData['reasons'] as $reason) {
            $reasonTotal = array_sum($reason['by_root']);
            $pctTotal    = $retGrandTotal > 0 ? $reasonTotal / $retGrandTotal : 0;
            $sheet->setCellValueByColumnAndRow($retLabelCol, $r, $reason['name']);
            foreach ($rootPlatforms as $root) {
                $rootCount = $reason['by_root'][$root['id']] ?? 0;
                $rootPct   = $retGrandTotal > 0 ? $rootCount / $retGrandTotal : 0;
                $sheet->setCellValueByColumnAndRow($retRootCols[$root['id']],    $r, $rootCount);
                $sheet->setCellValueByColumnAndRow($retRootPctCols[$root['id']], $r, $rootPct);
                $sheet->getStyleByColumnAndRow($retRootPctCols[$root['id']], $r)->getNumberFormat()->setFormatCode($pctFmt);
            }
            $sheet->setCellValueByColumnAndRow($retKidsCol,     $r, $reason['kids']);
            $sheet->setCellValueByColumnAndRow($retFemaleCol,   $r, $reason['female']);
            $sheet->setCellValueByColumnAndRow($retMaleCol,     $r, $reason['male']);
            $sheet->setCellValueByColumnAndRow($retTotalCol,    $r, $reasonTotal);
            $sheet->setCellValueByColumnAndRow($retPctTotalCol, $r, $pctTotal);
            $sheet->getStyleByColumnAndRow($retPctTotalCol, $r)->getNumberFormat()->setFormatCode($pctFmt);
            $this->alignSecRow($sheet, $anc, $retLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_ALT);
            $r++;
        }

        $sheet->setCellValueByColumnAndRow($retLabelCol, $r, 'Total');
        foreach ($rootPlatforms as $root) {
            $rootTotal      = $returnReasonData['totals_by_root'][$root['id']] ?? 0;
            $rootPctOfGrand = $retGrandTotal > 0 ? $rootTotal / $retGrandTotal : 0;
            $sheet->setCellValueByColumnAndRow($retRootCols[$root['id']],    $r, $rootTotal);
            $sheet->setCellValueByColumnAndRow($retRootPctCols[$root['id']], $r, $rootPctOfGrand);
            $sheet->getStyleByColumnAndRow($retRootPctCols[$root['id']], $r)->getNumberFormat()->setFormatCode($pctFmt);
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,     $r, $returnReasonData['totals_kids']);
        $sheet->setCellValueByColumnAndRow($retFemaleCol,   $r, $returnReasonData['totals_female']);
        $sheet->setCellValueByColumnAndRow($retMaleCol,     $r, $returnReasonData['totals_male']);
        $sheet->setCellValueByColumnAndRow($retTotalCol,    $r, $retGrandTotal);
        $sheet->setCellValueByColumnAndRow($retPctTotalCol, $r, $retGrandTotal > 0 ? 1 : 0);
        $sheet->getStyleByColumnAndRow($retPctTotalCol, $r)->getNumberFormat()->setFormatCode($pctFmt);
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $retLastCol, $r);
        $retSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $retLastCol, $retSecStart, $retSecEnd);
        $r = $retSecEnd + 4;
        }

        if ($includeWeeklyBreakdown) {
        $fixedLabels = ['Week', 'Sales (£)', 'Spend (£)', 'Order', 'Order Qty', 'Return Qty', 'Return Qty %', 'Return Amount (£)', 'Return Amount %'];
        $childLabels = ['Sales (£)', 'Orders', 'Qty', 'Return (£)', 'Ret Orders', 'Ret Qty'];
        $childCount  = count($childLabels);

        $fixedStartCol    = $anc;
        $fixedEndCol      = $fixedStartCol + count($fixedLabels) - 1;
        $platformStartCol = $fixedEndCol + 1;
        $wbLastCol = $numRoots > 0
            ? $platformStartCol + $numRoots * $childCount - 1
            : $fixedEndCol;

        $wbSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $wbLastCol, $r, 'Weekly Breakdown : Sale vs Spends vs Return');
        $r++;

        $headerRow1 = $r;
        $headerRow2 = $r + 1;

        foreach ($fixedLabels as $i => $lbl) {
            $cl = Coordinate::stringFromColumnIndex($fixedStartCol + $i);
            $sheet->setCellValue($cl . $headerRow1, $lbl);
            $sheet->mergeCells("{$cl}{$headerRow1}:{$cl}{$headerRow2}");
            $this->applyHeaderStyle($sheet, "{$cl}{$headerRow1}:{$cl}{$headerRow2}");
        }

        foreach ($rootPlatforms as $i => $root) {
            $groupStart = $platformStartCol + $i * $childCount;
            $groupEnd   = $groupStart + $childCount - 1;
            $startLtr   = Coordinate::stringFromColumnIndex($groupStart);
            $endLtr     = Coordinate::stringFromColumnIndex($groupEnd);
            $groupFill  = self::PLATFORM_COLORS[$i % count(self::PLATFORM_COLORS)];

            $sheet->setCellValue($startLtr . $headerRow1, $this->shortName($root['name']));
            $sheet->mergeCells("{$startLtr}{$headerRow1}:{$endLtr}{$headerRow1}");
            $this->applyHeaderStyle($sheet, "{$startLtr}{$headerRow1}:{$endLtr}{$headerRow1}");
            $sheet->getStyle("{$startLtr}{$headerRow1}:{$endLtr}{$headerRow1}")->getFont()->getColor()->setARGB('FF000000');

            foreach ($childLabels as $j => $lbl) {
                $cl = Coordinate::stringFromColumnIndex($groupStart + $j);
                $sheet->setCellValue($cl . $headerRow2, $lbl);
                $this->applyHeaderStyle($sheet, "{$cl}{$headerRow2}:{$cl}{$headerRow2}");
                $sheet->getStyle($cl . $headerRow2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupFill);
                $sheet->getStyle($cl . $headerRow2)->getFont()->getColor()->setARGB('FF000000');
            }
            $sheet->getStyle("{$startLtr}{$headerRow1}:{$endLtr}{$headerRow2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupFill);
        }

        $r = $headerRow2 + 1;

        $totalRetPcs    = 0;
        $totalRetGbp    = 0;
        $totalOrders    = 0;
        $totalItems     = 0;
        $platformTotals = array_fill_keys(array_column($rootPlatforms, 'id'), array_fill(0, $childCount, 0.0));

        foreach ($weeklyRows as $wRow) {
            $wk             = $wRow['week'];
            $sales          = (float) ($wRow['sales']      ?? 0);
            $spend          = (float) ($wRow['spend']      ?? 0);
            $retPcs         = (float) ($wRow['returns_pcs'] ?? 0);
            $retGbp         = (float) ($wRow['returns_gbp'] ?? 0);
            $weekOrders     = (float) array_sum($wRow['root_orders'] ?? []);
            $weekItems      = (float) array_sum($wRow['root_qty']    ?? []);
            $pctRetPcs      = $weekItems  > 0 ? $retPcs / $weekItems  : 0;
            $pctRetGbp      = $sales      > 0 ? $retGbp / $sales      : 0;

            $sheet->setCellValueByColumnAndRow($fixedStartCol + 0, $r, $wRow['label']);
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 1, $r, round($sales, 2));
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 2, $r, round($spend, 2));
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 3, $r, $weekOrders);
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 4, $r, $weekItems);
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 5, $r, $retPcs);
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 6, $r, $pctRetPcs);
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 7, $r, round($retGbp, 2));
            $sheet->setCellValueByColumnAndRow($fixedStartCol + 8, $r, $pctRetGbp);

            foreach ($rootPlatforms as $i => $root) {
                $rid        = $root['id'];
                $groupStart = $platformStartCol + $i * $childCount;
                $groupEnd   = $groupStart + $childCount - 1;
                $groupFill  = self::PLATFORM_COLORS[$i % count(self::PLATFORM_COLORS)];

                $vals = [
                    round((float) ($weeklySalesByRoot[$wk][$rid]                ?? 0), 2),
                                    (float) ($wRow['root_orders'][$rid]                   ?? 0),
                                    (float) ($wRow['root_qty'][$rid]                      ?? 0),
                    round((float) ($weeklyReturnsByRoot[$wk][$rid]['amount']    ?? 0), 2),
                                    (float) ($weeklyReturnsByRoot[$wk][$rid]['order_qty'] ?? 0),
                                    (float) ($weeklyReturnsByRoot[$wk][$rid]['item_qty']  ?? 0),
                ];

                foreach ($vals as $j => $val) {
                    $sheet->setCellValueByColumnAndRow($groupStart + $j, $r, $val);
                    $platformTotals[$rid][$j] += $val;
                }
                $startLtr = Coordinate::stringFromColumnIndex($groupStart);
                $endLtr   = Coordinate::stringFromColumnIndex($groupEnd);
                $sheet->getStyle("{$startLtr}{$r}:{$endLtr}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($groupFill);
                $sheet->getStyle("{$startLtr}{$r}:{$endLtr}{$r}")->getFont()->getColor()->setARGB('FF000000');
            }

            $totalRetPcs += $retPcs;
            $totalRetGbp += $retGbp;
            $totalOrders += $weekOrders;
            $totalItems  += $weekItems;

            $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $fixedStartCol, $fixedEndCol, $r, self::CLR_SEC_ALT);
            $r++;
        }

        $totalSales     = (float) ($totals['sales'] ?? 0);
        $totalSpend     = (float) ($totals['spent'] ?? 0);
        $pctTotalRetPcs = $totalItems > 0 ? $totalRetPcs / $totalItems : 0;
        $pctTotalRetGbp = $totalSales > 0 ? $totalRetGbp / $totalSales : 0;

        $sheet->setCellValueByColumnAndRow($fixedStartCol + 0, $r, 'Total');
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 1, $r, round($totalSales, 2));
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 2, $r, round($totalSpend, 2));
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 3, $r, $totalOrders);
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 4, $r, $totalItems);
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 5, $r, $totalRetPcs);
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 6, $r, $pctTotalRetPcs);
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 7, $r, round($totalRetGbp, 2));
        $sheet->setCellValueByColumnAndRow($fixedStartCol + 8, $r, $pctTotalRetGbp);
        foreach ($rootPlatforms as $i => $root) {
            $groupStart = $platformStartCol + $i * $childCount;
            foreach ($platformTotals[$root['id']] as $j => $val) {
                $write = in_array($j, [0, 3]) ? round((float) $val, 2) : $val;
                $sheet->setCellValueByColumnAndRow($groupStart + $j, $r, $write);
            }
        }

        $this->fillSecRange($sheet, $anc, $wbLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
        $wbSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $wbLastCol, $wbSecStart, $wbSecEnd);

        $moneyFmt  = '#,##0.00';
        $pctFmt    = '0.00%';
        $dataStart = $headerRow2 + 1;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($fixedStartCol + 1) . $dataStart . ':' . Coordinate::stringFromColumnIndex($fixedStartCol + 2) . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($fixedStartCol + 7) . $dataStart . ':' . Coordinate::stringFromColumnIndex($fixedStartCol + 7) . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($fixedStartCol + 6) . $dataStart . ':' . Coordinate::stringFromColumnIndex($fixedStartCol + 6) . $wbSecEnd)->getNumberFormat()->setFormatCode($pctFmt);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($fixedStartCol + 8) . $dataStart . ':' . Coordinate::stringFromColumnIndex($fixedStartCol + 8) . $wbSecEnd)->getNumberFormat()->setFormatCode($pctFmt);
        for ($ci = $platformStartCol; $ci <= $wbLastCol; $ci += $childCount) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($ci)     . $dataStart . ':' . Coordinate::stringFromColumnIndex($ci)     . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($ci + 3) . $dataStart . ':' . Coordinate::stringFromColumnIndex($ci + 3) . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        }
        }

        if ($includeDailyReport) {
            $sheet->getStyle('C' . $dataStartRow . ':C' . $lastMainRow)->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle('E' . $dataStartRow . ':E' . $lastMainRow)->getNumberFormat()->setFormatCode($moneyFmt);
            if ($dataEndRow >= $dataStartRow) {
                $sheet->getStyle('D' . $dataStartRow . ':D' . $dataEndRow)->getNumberFormat()->setFormatCode('0.00%');
            }
            if ($numAllPlatCols > 0) {
                $pStart = Coordinate::stringFromColumnIndex($platBaseCol);
                $pEnd   = Coordinate::stringFromColumnIndex($platBaseCol + $numAllPlatCols - 1);
                $sheet->getStyle("{$pStart}{$dataStartRow}:{$pEnd}{$lastMainRow}")->getNumberFormat()->setFormatCode($moneyFmt);
            }
            $mainRange = 'A1:' . Coordinate::stringFromColumnIndex($mainLastCol) . $lastMainRow;
            $sheet->getStyle($mainRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        if ($includeDailyReport) {
            $sheet->getColumnDimension('A')->setWidth(10);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(14);
            for ($ci = $platBaseCol; $ci < $platBaseCol + $numAllPlatCols; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
            }
            for ($ci = $rsBase; $ci <= $mainLastCol; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth(10);
            }
            for ($ci = $rsRootOrderBase; $ci <= $rsOrdersCol; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
            }
            for ($ci = $rsQtyRootBase; $ci <= $rsQtyCol; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
            }
            $sheet->freezePane('C' . $dataStartRow);
        }
        if ($includeReturnBreakdown || $includeWeeklyBreakdown) {
            $secLastCol = max($wbLastCol, $retLastCol);
            $sheet->getColumnDimensionByColumn($anc)->setWidth(18);
            for ($ci = $anc + 1; $ci <= $secLastCol; $ci++) {
                $sheet->getColumnDimensionByColumn($ci)->setWidth(12);
            }
        }
        if ($includeReturnBreakdown && $retSecStart > 0) {
            $sheet->getRowDimension($retSecStart)->setRowHeight(22);
        }
        if ($includeWeeklyBreakdown && $wbSecStart > 0) {
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

