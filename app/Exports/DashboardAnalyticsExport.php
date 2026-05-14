<?php

namespace App\Exports;

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
    // ── Colour palette ──────────────────────────────────────────────
    private const CLR_TITLE    = 'FF00B0F0'; // bright cyan – title row
    private const CLR_HEADER   = 'FF00B0F0'; // fixed / right-section headers
    private const CLR_PLATFORM = 'FFD9E1F2'; // platform group name rows
    private const CLR_COLLABEL = 'FFB8CCE4'; // Cost / Sales label row
    private const CLR_WEEK     = 'FFFFC000'; // week label in col A (amber)
    private const CLR_TOTAL    = 'FFBDD7EE'; // total rows (light blue)
    private const CLR_BUDGET   = 'FFE2EFDA'; // budget rows (light green)
    private const CLR_FORE     = 'FFFFEB9C'; // forecasting (light yellow)
    private const CLR_ROAS     = 'FFFCE4D6'; // ROI row (peach)
    private const CLR_WHITE    = 'FFFFFFFF';
    private const CLR_ALT      = 'FFF2F2F2'; // alternate data row
    private const CLR_SECTION  = 'FFFFE699'; // weekly / return section headers
    private const CLR_RETURN   = 'FFFCE4D6'; // return reason rows

    // Fixed column indices (1-based)
    private const COL_WEEK  = 1; // A – week label (merged per week)
    private const COL_DATE  = 2; // B – date
    private const COL_SALES = 3; // C – Daily Sales
    private const COL_ROAS  = 4; // D – Daily ROAS%
    private const COL_SPEND = 5; // E – Daily Spend

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private array  $months,
        private array  $label,
    ) {}

    public function download(DashboardAnalyticsService $service): StreamedResponse
    {
        $export = $service->getDailyExportData($this->dateFrom, $this->dateTo, $this->months);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        // ── Unpack service data ──────────────────────────────────────
        $columnData        = $export['column_data'];
        $columns           = $columnData['columns'];
        $headerLevels      = $columnData['header_levels'];
        $platMaxDepth      = $columnData['max_depth'];

        $rootPlatforms     = $export['root_platforms'];
        $rows              = $export['rows'];
        $summaryRows       = $export['summary_rows'];
        $weeklyRows        = $export['weekly_rows'];
        $returnReasonData  = $export['return_reason_data'];
        $totals            = $export['totals'];

        // ── Main column index mapping ────────────────────────────────
        $platBaseCol = self::COL_SPEND + 1;   // first platform column (1-based)
        $numPlatCols = count($columns);

        $platColMap = [];
        foreach ($columns as $i => $col) {
            $platColMap["{$col['platform_id']}_{$col['type']}"] = $platBaseCol + $i;
        }

        $rightBase     = $platBaseCol + $numPlatCols;
        $orderTotalCol = $rightBase;
        $colIdx        = $rightBase + 1;

        $rootOrderCols = [];
        foreach ($rootPlatforms as $root) {
            $rootOrderCols[$root['id']] = $colIdx++;
        }
        $qtyTotalCol = $colIdx++;
        $rootQtyCols = [];
        foreach ($rootPlatforms as $root) {
            $rootQtyCols[$root['id']] = $colIdx++;
        }
        $kidsCol   = $colIdx++;
        $femaleCol = $colIdx++;
        $maleCol   = $colIdx++;
        $mainLastCol = $colIdx - 1;   // last column of the MAIN data area (title ends here)

        // ── Return-reason section extra columns ──────────────────────
        // Placed to the right of the main area, 1 column gap
        $retLabelCol  = $mainLastCol + 2;        // "Reason" label
        $retRootCols  = [];
        $retColIdx    = $retLabelCol + 1;
        foreach ($rootPlatforms as $root) {
            $retRootCols[$root['id']] = $retColIdx++;
        }
        $retKidsCol   = $retColIdx++;
        $retFemaleCol = $retColIdx++;
        $retMaleCol   = $retColIdx++;
        $sheetLastCol = $retColIdx - 1;  // absolute last column on the sheet

        // ── Row positions ────────────────────────────────────────────
        // Row 1          : Title (C1 : mainLastCol)
        // Rows 2 … 1+D   : Platform-name hierarchy rows  (D = platMaxDepth, ≥ 0)
        // Row 2+D        : Cost/Sales label row  (= col-label row)
        //                  When platMaxDepth = 0 the col-label row IS row 2 (single header)
        // Row 3+D …      : Data rows
        $firstHdrRow = 2;
        if ($platMaxDepth > 0) {
            $colLabelRow  = $firstHdrRow + $platMaxDepth;   // e.g. depth=1 → row 3
        } else {
            $colLabelRow  = $firstHdrRow;                   // single header row
        }
        $dataStartRow = $colLabelRow + 1;

        // ── Row 1: Title  (starts at C, leaves A/B empty) ────────────
        $titleStr  = 'Tracking digital Marketing COST VS Allocation – ' . ($this->label['label'] ?? '');
        $titleStartCol = Coordinate::stringFromColumnIndex(self::COL_SALES); // "C"
        $titleEndCol   = Coordinate::stringFromColumnIndex($mainLastCol);
        $sheet->setCellValue($titleStartCol . '1', $titleStr);
        $sheet->mergeCells("{$titleStartCol}1:{$titleEndCol}1");
        $this->styleTitle($sheet, "{$titleStartCol}1:{$titleEndCol}1");

        // ── Fixed + right-section column headers ─────────────────────
        // (all merged vertically from firstHdrRow → colLabelRow when there is hierarchy)
        $fixedHdrs = [
            self::COL_WEEK  => 'week',
            self::COL_DATE  => 'Date',
            self::COL_SALES => 'Daily Sales',
            self::COL_ROAS  => 'Daily ROAS',
            self::COL_SPEND => 'Daily Spend',
        ];
        foreach ($fixedHdrs as $ci => $lbl) {
            $cl = Coordinate::stringFromColumnIndex($ci);
            $sheet->setCellValue($cl . $firstHdrRow, $lbl);
            if ($colLabelRow > $firstHdrRow) {
                $sheet->mergeCells("{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
            }
            $this->applyHeaderStyle($sheet, "{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
        }

        $rightHdrs = [[$orderTotalCol, 'Total Orders']];
        foreach ($rootPlatforms as $root) {
            $rightHdrs[] = [$rootOrderCols[$root['id']], $root['name'] . ' Orders'];
        }
        $rightHdrs[] = [$qtyTotalCol, 'Total QTY'];
        foreach ($rootPlatforms as $root) {
            $rightHdrs[] = [$rootQtyCols[$root['id']], $root['name'] . ' QTY'];
        }
        $rightHdrs = array_merge($rightHdrs, [
            [$kidsCol,   'Kids'],
            [$femaleCol, 'Female'],
            [$maleCol,   'Male'],
        ]);
        foreach ($rightHdrs as [$ci, $lbl]) {
            $cl = Coordinate::stringFromColumnIndex($ci);
            $sheet->setCellValue($cl . $firstHdrRow, $lbl);
            if ($colLabelRow > $firstHdrRow) {
                $sheet->mergeCells("{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
            }
            $this->applyHeaderStyle($sheet, "{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
        }

        // ── Platform hierarchy header rows ───────────────────────────
        foreach ($headerLevels as $level => $levelCells) {
            $excelRow = $firstHdrRow + $level;
            foreach ($levelCells as $cell) {
                $startCi = $platBaseCol + $cell['col_offset'];
                $startCl = Coordinate::stringFromColumnIndex($startCi);
                $endCi   = $startCi + $cell['col_span'] - 1;
                $endCl   = Coordinate::stringFromColumnIndex($endCi);
                $endRow  = $excelRow + $cell['row_span'] - 1;

                $sheet->setCellValue($startCl . $excelRow, $cell['label']);
                if ($endCi > $startCi || $endRow > $excelRow) {
                    $sheet->mergeCells("{$startCl}{$excelRow}:{$endCl}{$endRow}");
                }
                $this->applyPlatformGroupStyle($sheet, "{$startCl}{$excelRow}:{$endCl}{$endRow}");
            }
        }

        // ── Cost / Sales label row ────────────────────────────────────
        foreach ($columns as $i => $col) {
            $ci  = $platBaseCol + $i;
            $cl  = Coordinate::stringFromColumnIndex($ci);
            $lbl = $col['type'] === 'cost' ? 'Cost' : 'Sales';
            $sheet->setCellValue($cl . $colLabelRow, $lbl);
            $sheet->getStyle($cl . $colLabelRow)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_COLLABEL);
            $sheet->getStyle($cl . $colLabelRow)->getFont()->setBold(true);
            $sheet->getStyle($cl . $colLabelRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Row heights for header rows
        for ($hr = $firstHdrRow; $hr <= $colLabelRow; $hr++) {
            $sheet->getRowDimension($hr)->setRowHeight(28);
        }

        // ── Data rows ─────────────────────────────────────────────────
        $r          = $dataStartRow;
        $weekRanges = [];
        $prevWeek   = null;

        foreach ($rows as $row) {
            $weekNum = $row['week'];
            if ($weekNum !== $prevWeek) {
                $sheet->setCellValue('A' . $r, 'week ' . $weekNum);
                $weekRanges[$weekNum] = ['start' => $r, 'end' => $r];
                $prevWeek = $weekNum;
            } else {
                $weekRanges[$weekNum]['end'] = $r;
            }

            $sheet->setCellValue('B' . $r, Carbon::parse($row['date'])->format('d-M-Y'));
            $sheet->setCellValue('C' . $r, $row['total_sales']);
            $sheet->setCellValue('D' . $r, $row['roas']);
            $sheet->setCellValue('E' . $r, $row['total_spent']);

            foreach ($columns as $col) {
                $pid = $col['platform_id'];
                $typ = $col['type'];
                $ci  = $platColMap["{$pid}_{$typ}"];
                $val = $typ === 'cost'
                    ? ($row['platform'][$pid]['cost']  ?? 0)
                    : ($row['platform'][$pid]['sales'] ?? 0);
                $sheet->setCellValueByColumnAndRow($ci, $r, $val);
            }

            $sheet->setCellValueByColumnAndRow($orderTotalCol, $r, $row['total_orders']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $rootOrderCols[$root['id']], $r,
                    $row['root_groups'][$root['id']]['orders'] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $row['total_qty']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $rootQtyCols[$root['id']], $r,
                    $row['root_groups'][$root['id']]['qty'] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $row['kids']);
            $sheet->setCellValueByColumnAndRow($femaleCol, $r, $row['female']);
            $sheet->setCellValueByColumnAndRow($maleCol,   $r, $row['male']);

            if ($r % 2 === 0) {
                $this->fillRow($sheet, $r, $mainLastCol, self::CLR_ALT);
            }

            $r++;
        }
        $dataEndRow = $r - 1;

        // Merge week-label cells in column A
        foreach ($weekRanges as $wRange) {
            if ($wRange['end'] > $wRange['start']) {
                $sheet->mergeCells('A' . $wRange['start'] . ':A' . $wRange['end']);
                $sheet->getStyle('A' . $wRange['start'])->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP);
            }
            $this->fillRow($sheet, $wRange['start'], 1, self::CLR_WEEK, true);
        }

        // ── Main summary rows ─────────────────────────────────────────
        $summaryColorMap = [
            'total_spend'    => self::CLR_TOTAL,
            'roi'            => self::CLR_ROAS,
            'total_budget'   => self::CLR_BUDGET,
            'balance_budget' => self::CLR_BUDGET,
            'average_daily'  => self::CLR_WHITE,
            'total_sale'     => self::CLR_TOTAL,
            'forecasting'    => self::CLR_FORE,
        ];

        foreach ($summaryRows as $key => $sRow) {
            $color = $summaryColorMap[$key] ?? self::CLR_WHITE;
            $sheet->setCellValue('B' . $r, $sRow['label']);

            if ($sRow['col_c'] !== null) $sheet->setCellValue('C' . $r, $sRow['col_c']);
            if ($sRow['col_e'] !== null) {
                $sheet->setCellValue('E' . $r, $sRow['col_e']);
                if (!empty($sRow['col_e_format'])) {
                    $sheet->getStyle('E' . $r)->getNumberFormat()->setFormatCode($sRow['col_e_format']);
                }
            }

            foreach ($sRow['platform'] as $colKey => $value) {
                if (!isset($platColMap[$colKey])) continue;
                $ci = $platColMap[$colKey];
                $sheet->setCellValueByColumnAndRow($ci, $r, $value);
                if (!empty($sRow['platform_formats'][$colKey])) {
                    $sheet->getStyleByColumnAndRow($ci, $r)->getNumberFormat()
                        ->setFormatCode($sRow['platform_formats'][$colKey]);
                }
            }

            if ($sRow['total_orders'] !== null)
                $sheet->setCellValueByColumnAndRow($orderTotalCol, $r, $sRow['total_orders']);
            if (!empty($sRow['root_orders'])) {
                foreach ($sRow['root_orders'] as $rootId => $val) {
                    if (isset($rootOrderCols[$rootId]))
                        $sheet->setCellValueByColumnAndRow($rootOrderCols[$rootId], $r, $val);
                }
            }
            if ($sRow['total_qty'] !== null)
                $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $sRow['total_qty']);
            if (!empty($sRow['root_qty'])) {
                foreach ($sRow['root_qty'] as $rootId => $val) {
                    if (isset($rootQtyCols[$rootId]))
                        $sheet->setCellValueByColumnAndRow($rootQtyCols[$rootId], $r, $val);
                }
            }
            if ($sRow['kids']   !== null) $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $sRow['kids']);
            if ($sRow['female'] !== null) $sheet->setCellValueByColumnAndRow($femaleCol, $r, $sRow['female']);
            if ($sRow['male']   !== null) $sheet->setCellValueByColumnAndRow($maleCol,   $r, $sRow['male']);

            $this->fillRow($sheet, $r, $mainLastCol, $color);
            $this->setBold($sheet, 'B' . $r);
            $r++;
        }
        $lastMainRow = $r - 1;

        // ══════════════════════════════════════════════════════════════
        // ── BOTTOM SECTION: Weekly breakdown + Return-reason ──────────
        // ══════════════════════════════════════════════════════════════

        $r++;   // blank row gap

        // ── Section A: Weekly breakdown ───────────────────────────────
        // Uses the SAME main-area column positions so values sit under their headers

        $weekSectionStart = $r;

        // Section header (row $r)
        $this->setCellBold($sheet, 'B' . $r, 'Weekly Breakdown');
        $sheet->setCellValue('C'  . $r, 'Weekly Sales');
        $sheet->setCellValue('E'  . $r, 'Weekly Spend');
        $sheet->setCellValueByColumnAndRow($orderTotalCol, $r, 'Total Orders');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($rootOrderCols[$root['id']], $r, $root['name']);
        }
        $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, 'Total QTY');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($rootQtyCols[$root['id']], $r, $root['name'] . ' QTY');
        }
        $sheet->setCellValueByColumnAndRow($kidsCol,   $r, 'Kids');
        $sheet->setCellValueByColumnAndRow($femaleCol, $r, 'Female');
        $sheet->setCellValueByColumnAndRow($maleCol,   $r, 'Male');
        $this->fillRow($sheet, $r, $mainLastCol, self::CLR_SECTION);
        $r++;

        // Per-week rows
        foreach ($weeklyRows as $wRow) {
            $sheet->setCellValue('B' . $r, $wRow['label']);
            $sheet->setCellValue('C' . $r, $wRow['sales']);
            $sheet->setCellValue('E' . $r, $wRow['spend']);
            $sheet->setCellValueByColumnAndRow($orderTotalCol, $r, $wRow['orders']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $rootOrderCols[$root['id']], $r,
                    $wRow['root_orders'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $wRow['qty']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $rootQtyCols[$root['id']], $r,
                    $wRow['root_qty'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $wRow['kids']);
            $sheet->setCellValueByColumnAndRow($femaleCol, $r, $wRow['female']);
            $sheet->setCellValueByColumnAndRow($maleCol,   $r, $wRow['male']);

            if ($r % 2 === 0) $this->fillRow($sheet, $r, $mainLastCol, self::CLR_ALT);
            $r++;
        }

        // Weekly total row
        $sheet->setCellValue('B' . $r, 'Total');
        $sheet->setCellValue('C' . $r, $totals['sales']);
        $sheet->setCellValue('E' . $r, $totals['spent']);
        $sheet->setCellValueByColumnAndRow($orderTotalCol, $r, $totals['orders']);
        foreach ($rootPlatforms as $root) {
            $rootId    = $root['id'];
            $rootTotal = 0;
            foreach ($weeklyRows as $wRow) {
                $rootTotal += ($wRow['root_orders'][$rootId] ?? 0);
            }
            $sheet->setCellValueByColumnAndRow($rootOrderCols[$rootId], $r, $rootTotal);
        }
        $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $totals['qty']);
        $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $totals['kids']);
        $sheet->setCellValueByColumnAndRow($femaleCol, $r, $totals['female']);
        $sheet->setCellValueByColumnAndRow($maleCol,   $r, $totals['male']);
        $this->fillRow($sheet, $r, $mainLastCol, self::CLR_TOTAL);
        $this->setBold($sheet, 'B' . $r);
        $weekSectionEnd = $r;
        $r++;

        // ── Section B: Return-reason breakdown ────────────────────────
        // Placed to the right of the main area (columns retLabelCol …)
        // Rows are interleaved with the weekly section: same sectionStart row

        $reasons = $returnReasonData['reasons'];
        $retSectionRow = $weekSectionStart;  // Start at same row as weekly section header

        // Section header (same row as weekly header)
        $sheet->setCellValue(
            Coordinate::stringFromColumnIndex($retLabelCol) . $retSectionRow,
            'Return Breakdown'
        );
        $this->setBold($sheet, Coordinate::stringFromColumnIndex($retLabelCol) . $retSectionRow);
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow(
                $retRootCols[$root['id']], $retSectionRow,
                $root['name'] . ' Returns'
            );
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,   $retSectionRow, 'Kids');
        $sheet->setCellValueByColumnAndRow($retFemaleCol, $retSectionRow, 'Female');
        $sheet->setCellValueByColumnAndRow($retMaleCol,   $retSectionRow, 'Male');
        // Fill section header for return columns
        $retHdrRange = Coordinate::stringFromColumnIndex($retLabelCol) . $retSectionRow . ':'
                     . Coordinate::stringFromColumnIndex($retMaleCol)  . $retSectionRow;
        $sheet->getStyle($retHdrRange)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_SECTION);
        $sheet->getStyle($retHdrRange)->getFont()->setBold(true);
        $retSectionRow++;

        // Return-reason data rows
        foreach ($reasons as $reason) {
            $sheet->setCellValueByColumnAndRow($retLabelCol, $retSectionRow, $reason['name']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $retRootCols[$root['id']], $retSectionRow,
                    $reason['by_root'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($retKidsCol,   $retSectionRow, $reason['kids']);
            $sheet->setCellValueByColumnAndRow($retFemaleCol, $retSectionRow, $reason['female']);
            $sheet->setCellValueByColumnAndRow($retMaleCol,   $retSectionRow, $reason['male']);
            if ($retSectionRow % 2 === 0) {
                $retRange = Coordinate::stringFromColumnIndex($retLabelCol) . $retSectionRow . ':'
                          . Coordinate::stringFromColumnIndex($retMaleCol)  . $retSectionRow;
                $sheet->getStyle($retRange)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::CLR_ALT);
            }
            $retSectionRow++;
        }

        // Return totals row
        $sheet->setCellValueByColumnAndRow($retLabelCol, $retSectionRow, 'Total');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow(
                $retRootCols[$root['id']], $retSectionRow,
                $returnReasonData['totals_by_root'][$root['id']] ?? 0
            );
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,   $retSectionRow, $returnReasonData['totals_kids']);
        $sheet->setCellValueByColumnAndRow($retFemaleCol, $retSectionRow, $returnReasonData['totals_female']);
        $sheet->setCellValueByColumnAndRow($retMaleCol,   $retSectionRow, $returnReasonData['totals_male']);
        $retTotRange = Coordinate::stringFromColumnIndex($retLabelCol) . $retSectionRow . ':'
                     . Coordinate::stringFromColumnIndex($retMaleCol)  . $retSectionRow;
        $sheet->getStyle($retTotRange)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_TOTAL);
        $sheet->getStyle($retTotRange)->getFont()->setBold(true);

        $lastRow = max($r - 1, $retSectionRow);

        // ── Number formats ────────────────────────────────────────────
        $moneyFmt = '#,##0.00';
        $sheet->getStyle('C' . $dataStartRow . ':C' . $lastRow)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle('E' . $dataStartRow . ':E' . $lastRow)->getNumberFormat()->setFormatCode($moneyFmt);
        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle('D' . $dataStartRow . ':D' . $dataEndRow)
                ->getNumberFormat()->setFormatCode('0.00%');
        }
        if ($numPlatCols > 0) {
            $pStart = Coordinate::stringFromColumnIndex($platBaseCol);
            $pEnd   = Coordinate::stringFromColumnIndex($platBaseCol + $numPlatCols - 1);
            $sheet->getStyle("{$pStart}{$dataStartRow}:{$pEnd}{$lastMainRow}")
                ->getNumberFormat()->setFormatCode($moneyFmt);
        }

        // ── Borders ───────────────────────────────────────────────────
        // Main area (including title, headers, data, and summary)
        $mainRange = 'A1:' . Coordinate::stringFromColumnIndex($mainLastCol) . $lastMainRow;
        $sheet->getStyle($mainRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Weekly section borders
        $wSecRange = 'A' . $weekSectionStart . ':' . Coordinate::stringFromColumnIndex($mainLastCol) . $weekSectionEnd;
        $sheet->getStyle($wSecRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Return section borders
        $retBorderRange = Coordinate::stringFromColumnIndex($retLabelCol) . $weekSectionStart . ':'
                        . Coordinate::stringFromColumnIndex($retMaleCol) . $retSectionRow;
        $sheet->getStyle($retBorderRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ── Column widths ─────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);
        for ($ci = $platBaseCol; $ci <= $sheetLastCol; $ci++) {
            $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
        }

        // ── Freeze panes ──────────────────────────────────────────────
        $sheet->freezePane('C' . $dataStartRow);

        // ── Stream ────────────────────────────────────────────────────
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

    // ── Style helpers ──────────────────────────────────────────────

    private function styleTitle($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FF1F3864');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);
    }

    private function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_HEADER);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FF1F3864');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function applyPlatformGroupStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_PLATFORM);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FF1F3864');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function fillRow($sheet, int $row, int $lastColIdx, string $argb, bool $singleColOnly = false): void
    {
        $range = $singleColOnly
            ? 'A' . $row
            : 'A' . $row . ':' . Coordinate::stringFromColumnIndex($lastColIdx) . $row;
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($argb);
    }

    private function setBold($sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }

    private function setCellBold($sheet, string $cell, string $value): void
    {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }
}

