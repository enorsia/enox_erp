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
    // ── Colour palette  (accent = #009966) ──────────────────────────
    private const CLR_ACCENT     = 'FF009966'; // accent green – A1/B1 cells
    private const CLR_TITLE_BG   = 'FF005C3E'; // very dark green – title row bg
    private const CLR_TITLE_FG   = 'FFFFFFFF'; // white – title text
    private const CLR_HDR_BG     = 'FF009966'; // accent – main fixed/col headers bg
    private const CLR_HDR_FG     = 'FFFFFFFF'; // white – header text
    private const CLR_PLAT_BG    = 'FF52B08C'; // medium green – platform group bg
    private const CLR_PLAT_FG    = 'FFFFFFFF'; // white – platform group text
    private const CLR_COLLABEL   = 'FFCCEEDD'; // light mint – Spend/Sales label row
    private const CLR_COLLABEL_FG= 'FF003D2B'; // very dark green – col-label text
    private const CLR_WEEK       = 'FFFFBF00'; // amber – week column label
    private const CLR_WEEK_FG    = 'FF3D2B00'; // dark amber text
    private const CLR_ROW_ALT    = 'FFF0FAF5'; // very light green – alternate rows
    private const CLR_TOTAL      = 'FFB3E6CC'; // green – total rows
    private const CLR_TOTAL_FG   = 'FF003D2B';
    private const CLR_BUDGET     = 'FFFFF9CC'; // light yellow – budget rows
    private const CLR_FORE       = 'FFFFF0AA'; // amber-yellow – forecasting row
    private const CLR_ROAS       = 'FFFFDDC0'; // light peach – ROI row
    private const CLR_WHITE      = 'FFFFFFFF';
    private const CLR_DARK_TEXT  = 'FF1A3A2A'; // dark green-black – default text
    // Bottom-section colours
    private const CLR_SEC_TITLE  = 'FF003D2B'; // very dark green – section title bg
    private const CLR_SEC_HDR    = 'FF009966'; // accent – section column header bg
    private const CLR_SEC_ALT    = 'FFF0FAF5'; // same as row alt

    // Fixed column indices (1-based)
    private const COL_WEEK  = 1; // A
    private const COL_DATE  = 2; // B
    private const COL_SALES = 3; // C
    private const COL_ROAS  = 4; // D
    private const COL_SPEND = 5; // E

    // All bottom sections start at column F = 6
    private const COL_SECTION_ANCHOR = 6; // F

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
        $columnData       = $export['column_data'];
        $columns          = $columnData['columns'];
        $headerLevels     = $columnData['header_levels'];
        $platMaxDepth     = $columnData['max_depth'];

        $rootPlatforms    = $export['root_platforms'];
        $rows             = $export['rows'];
        $summaryRows      = $export['summary_rows'];
        $weeklyRows       = $export['weekly_rows'];
        $returnReasonData = $export['return_reason_data'];
        $totals           = $export['totals'];

        $numRoots    = count($rootPlatforms);

        // ── Column indices ───────────────────────────────────────────
        $platBaseCol = self::COL_SPEND + 1;   // F = 6, first platform column
        $numPlatCols = count($columns);

        // Right section (after platform columns): per-root orders → total orders → per-root qty → total qty → Kids/Female/Male
        $rsBase          = $platBaseCol + $numPlatCols;
        $rsRootOrderBase = $rsBase;                         // per-root orders (numRoots cols)
        $rsOrdersCol     = $rsBase + $numRoots;             // Total Orders
        $rsQtyRootBase   = $rsBase + $numRoots + 1;         // per-root QTY (numRoots cols)
        $rsQtyCol        = $rsBase + 2 * $numRoots + 1;     // Total QTY
        $rsKidsCol       = $rsBase + 2 * $numRoots + 2;
        $rsFemaleCol     = $rsBase + 2 * $numRoots + 3;
        $rsMaleCol       = $rsBase + 2 * $numRoots + 4;
        $mainLastCol     = $rsMaleCol;

        // Root-column maps for right section
        $rsRootOrderCols = [];
        $rsRootQtyCols   = [];
        foreach ($rootPlatforms as $i => $root) {
            $rsRootOrderCols[$root['id']] = $rsRootOrderBase + $i;
            $rsRootQtyCols[$root['id']]   = $rsQtyRootBase   + $i;
        }

        $platColMap = [];
        foreach ($columns as $i => $col) {
            $platColMap["{$col['platform_id']}_{$col['type']}"] = $platBaseCol + $i;
        }

        // ── Row positions ────────────────────────────────────────────
        $firstHdrRow  = 2;
        $colLabelRow  = $platMaxDepth > 0 ? $firstHdrRow + $platMaxDepth : $firstHdrRow;
        $dataStartRow = $colLabelRow + 1;
        $hdrSpan      = $colLabelRow - $firstHdrRow + 1; // total header rows (for merging)

        // ── Row 1: Title (C1 : mainLastCol) + accent fill on A1/B1 ──
        $titleStr      = 'Tracking Digital Marketing COST VS Allocation – ' . ($this->label['label'] ?? '');
        $titleStartCol = Coordinate::stringFromColumnIndex(self::COL_SALES); // "C"
        $titleEndCol   = Coordinate::stringFromColumnIndex($mainLastCol);
        $sheet->setCellValue($titleStartCol . '1', $titleStr);
        $sheet->mergeCells("{$titleStartCol}1:{$titleEndCol}1");
        $this->styleTitle($sheet, "{$titleStartCol}1:{$titleEndCol}1");
        foreach (['A1', 'B1'] as $cell) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_ACCENT);
        }

        // ── Fixed column headers (A–E) ───────────────────────────────
        $fixedHdrs = [
            self::COL_WEEK  => 'Week',
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

        // ── Spend/Sales label row ────────────────────────────────────
        foreach ($columns as $i => $col) {
            $ci  = $platBaseCol + $i;
            $cl  = Coordinate::stringFromColumnIndex($ci);
            $lbl = $col['type'] === 'cost' ? 'Spend' : 'Sales';
            $sheet->setCellValue($cl . $colLabelRow, $lbl);
            $sheet->getStyle($cl . $colLabelRow)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_COLLABEL);
            $sheet->getStyle($cl . $colLabelRow)->getFont()->setBold(true)
                ->getColor()->setARGB(self::CLR_COLLABEL_FG);
            $sheet->getStyle($cl . $colLabelRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── Right-section headers (all merged vertically firstHdrRow→colLabelRow) ─
        $rsHdrDefs = [];
        foreach ($rootPlatforms as $root) {
            $sn = $this->shortName($root['name']);
            $rsHdrDefs[$rsRootOrderCols[$root['id']]] = $sn;
            $rsHdrDefs[$rsRootQtyCols[$root['id']]]   = $sn . ' QTY';
        }
        $rsHdrDefs[$rsOrdersCol]  = "No.\nof Order";
        $rsHdrDefs[$rsQtyCol]     = "No.\nof QTY";
        $rsHdrDefs[$rsKidsCol]    = 'Kids';
        $rsHdrDefs[$rsFemaleCol]  = 'Female';
        $rsHdrDefs[$rsMaleCol]    = 'Male';

        foreach ($rsHdrDefs as $ci => $lbl) {
            $cl = Coordinate::stringFromColumnIndex($ci);
            $sheet->setCellValue($cl . $firstHdrRow, $lbl);
            if ($colLabelRow > $firstHdrRow) {
                $sheet->mergeCells("{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
            }
            $this->applyHeaderStyle($sheet, "{$cl}{$firstHdrRow}:{$cl}{$colLabelRow}");
        }

        // Row heights for header rows
        for ($hr = $firstHdrRow; $hr <= $colLabelRow; $hr++) {
            $sheet->getRowDimension($hr)->setRowHeight(28);
        }

        // ── Data rows ────────────────────────────────────────────────
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
            $sheet->setCellValue('C' . $r, $row['total_sales']);
            $sheet->setCellValue('D' . $r, $row['roas']);
            $sheet->setCellValue('E' . $r, $row['total_spent']);

            // Platform cost/sales columns
            foreach ($columns as $col) {
                $pid = $col['platform_id'];
                $typ = $col['type'];
                $ci  = $platColMap["{$pid}_{$typ}"];
                $val = $typ === 'cost' ? ($row['platform'][$pid]['cost'] ?? 0)
                                       : ($row['platform'][$pid]['sales'] ?? 0);
                $sheet->setCellValueByColumnAndRow($ci, $r, $val);
            }

            // Right-section: per-root orders, total orders, per-root qty, total qty, kids/female/male
            foreach ($rootPlatforms as $root) {
                $rid = $root['id'];
                $sheet->setCellValueByColumnAndRow(
                    $rsRootOrderCols[$rid], $r, $row['root_groups'][$rid]['orders'] ?? 0
                );
                $sheet->setCellValueByColumnAndRow(
                    $rsRootQtyCols[$rid], $r, $row['root_groups'][$rid]['qty'] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($rsOrdersCol,  $r, $row['total_orders']);
            $sheet->setCellValueByColumnAndRow($rsQtyCol,     $r, $row['total_qty']);
            $sheet->setCellValueByColumnAndRow($rsKidsCol,    $r, $row['kids']);
            $sheet->setCellValueByColumnAndRow($rsFemaleCol,  $r, $row['female']);
            $sheet->setCellValueByColumnAndRow($rsMaleCol,    $r, $row['male']);

            if ($r % 2 === 0) {
                $this->fillRow($sheet, $r, $mainLastCol, self::CLR_ROW_ALT);
            }
            // Right-align numeric data
            $sheet->getStyle('C' . $r . ':' . Coordinate::stringFromColumnIndex($mainLastCol) . $r)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $r++;
        }
        $dataEndRow = $r - 1;

        // Merge week-label cells in column A – VERTICAL CENTER
        foreach ($weekRanges as $wRange) {
            if ($wRange['end'] > $wRange['start']) {
                $sheet->mergeCells('A' . $wRange['start'] . ':A' . $wRange['end']);
            }
            $sheet->getStyle('A' . $wRange['start'])->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)   // ← centered (not top)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $wRange['start'])->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::CLR_WEEK);
            $sheet->getStyle('A' . $wRange['start'])->getFont()->setBold(true)
                ->getColor()->setARGB(self::CLR_WEEK_FG);
        }

        // ── Summary rows ─────────────────────────────────────────────
        $summaryColorMap = [
            'average_daily'  => self::CLR_WHITE,
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
                $sheet->setCellValue('C' . $r, $sRow['col_c']);
                $sheet->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            if ($sRow['col_e'] !== null) {
                $sheet->setCellValue('E' . $r, $sRow['col_e']);
                $sheet->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if (!empty($sRow['col_e_format'])) {
                    $sheet->getStyle('E' . $r)->getNumberFormat()->setFormatCode($sRow['col_e_format']);
                }
            }
            foreach ($sRow['platform'] as $colKey => $value) {
                if (!isset($platColMap[$colKey])) continue;
                $ci = $platColMap[$colKey];
                $sheet->setCellValueByColumnAndRow($ci, $r, $value);
                $sheet->getStyleByColumnAndRow($ci, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                if (!empty($sRow['platform_formats'][$colKey])) {
                    $sheet->getStyleByColumnAndRow($ci, $r)->getNumberFormat()
                        ->setFormatCode($sRow['platform_formats'][$colKey]);
                }
            }

            // Right-section summary values (only rows that have them)
            if (!empty($sRow['total_orders'])) {
                $sheet->setCellValueByColumnAndRow($rsOrdersCol, $r, $sRow['total_orders']);
            }
            if (!empty($sRow['root_orders'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_orders'][$rid], $rsRootOrderCols[$rid])) {
                        $sheet->setCellValueByColumnAndRow($rsRootOrderCols[$rid], $r, $sRow['root_orders'][$rid]);
                    }
                }
            }
            if (!empty($sRow['total_qty'])) {
                $sheet->setCellValueByColumnAndRow($rsQtyCol, $r, $sRow['total_qty']);
            }
            if (!empty($sRow['root_qty'])) {
                foreach ($rootPlatforms as $root) {
                    $rid = $root['id'];
                    if (isset($sRow['root_qty'][$rid], $rsRootQtyCols[$rid])) {
                        $sheet->setCellValueByColumnAndRow($rsRootQtyCols[$rid], $r, $sRow['root_qty'][$rid]);
                    }
                }
            }
            if ($sRow['kids']   !== null) $sheet->setCellValueByColumnAndRow($rsKidsCol,   $r, $sRow['kids']);
            if ($sRow['female'] !== null) $sheet->setCellValueByColumnAndRow($rsFemaleCol,  $r, $sRow['female']);
            if ($sRow['male']   !== null) $sheet->setCellValueByColumnAndRow($rsMaleCol,    $r, $sRow['male']);

            $this->fillRow($sheet, $r, $mainLastCol, $color);
            $sheet->getStyle('B' . $r)->getFont()->setBold(true);
            $r++;
        }
        $lastMainRow = $r - 1;

        // ══════════════════════════════════════════════════════════════
        // ── BOTTOM SECTIONS  (all anchored at column F = 6) ──────────
        // ══════════════════════════════════════════════════════════════

        $r  += 4;   // 4 blank rows after summary
        $anc = self::COL_SECTION_ANCHOR; // 6 = F

        // ─────────────────────────────────────────────────────────────
        // SECTION 1 – Weekly Breakdown
        //   F=Week | G=Sales | H=Spend | I=Ret PCS | J=Ret GBP
        // ─────────────────────────────────────────────────────────────
        $wbCols = [
            'label'  => $anc,
            'sales'  => $anc + 1,
            'spend'  => $anc + 2,
            'retpcs' => $anc + 3,
            'retgbp' => $anc + 4,
        ];
        $wbLastCol  = $wbCols['retgbp'];
        $wbSecStart = $r;

        $this->writeSectionTitle($sheet, $anc, $wbLastCol, $r, 'Weekly Breakdown');
        $r++;
        $this->writeSectionHeaders($sheet, $anc, ['Week', 'Sales', 'Spend', 'Ret PCS', 'Ret GBP'], $r);
        $r++;

        foreach ($weeklyRows as $wRow) {
            $sheet->setCellValueByColumnAndRow($wbCols['label'],  $r, $wRow['label']);
            $sheet->setCellValueByColumnAndRow($wbCols['sales'],  $r, $wRow['sales']);
            $sheet->setCellValueByColumnAndRow($wbCols['spend'],  $r, $wRow['spend']);
            $sheet->setCellValueByColumnAndRow($wbCols['retpcs'], $r, $wRow['returns_pcs']);
            $sheet->setCellValueByColumnAndRow($wbCols['retgbp'], $r, $wRow['returns_gbp'] ?? 0);
            $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $anc, $wbLastCol, $r, self::CLR_SEC_ALT);
            $r++;
        }
        $totalRetPcs = array_sum(array_column($weeklyRows, 'returns_pcs'));
        $totalRetGbp = array_sum(array_column($weeklyRows, 'returns_gbp'));
        $sheet->setCellValueByColumnAndRow($wbCols['label'],  $r, 'Total');
        $sheet->setCellValueByColumnAndRow($wbCols['sales'],  $r, $totals['sales']);
        $sheet->setCellValueByColumnAndRow($wbCols['spend'],  $r, $totals['spent']);
        $sheet->setCellValueByColumnAndRow($wbCols['retpcs'], $r, $totalRetPcs);
        $sheet->setCellValueByColumnAndRow($wbCols['retgbp'], $r, $totalRetGbp);
        $this->fillSecRange($sheet, $anc, $wbLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $wbLastCol, $r);
        $wbSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $wbLastCol, $wbSecStart, $wbSecEnd);

        $moneyFmt   = '#,##0.00';
        $wbSalesLtr = Coordinate::stringFromColumnIndex($wbCols['sales']);
        $wbSpendLtr = Coordinate::stringFromColumnIndex($wbCols['spend']);
        $wbGbpLtr   = Coordinate::stringFromColumnIndex($wbCols['retgbp']);
        $sheet->getStyle($wbSalesLtr . ($wbSecStart + 2) . ':' . $wbSalesLtr . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle($wbSpendLtr . ($wbSecStart + 2) . ':' . $wbSpendLtr . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle($wbGbpLtr   . ($wbSecStart + 2) . ':' . $wbGbpLtr   . $wbSecEnd)->getNumberFormat()->setFormatCode($moneyFmt);
        $r++;

        // ─────────────────────────────────────────────────────────────
        // SECTION 2a – Total Order QTY  (order count per week) ───────
        //   F=Week | G=[root1] | G+1=[root2] | … | G+numRoots=Total Orders
        // ─────────────────────────────────────────────────────────────
        $r += 4;

        $toqRootStart = $anc + 1;           // per-root order cols
        $toqTotal     = $anc + 1 + $numRoots; // Total Orders col
        $toqLastCol   = $toqTotal;

        $toqRootCols = [];
        foreach ($rootPlatforms as $i => $root) {
            $toqRootCols[$root['id']] = $toqRootStart + $i;
        }

        $toqSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $toqLastCol, $r, 'Total Order QTY');
        $r++;

        // Column headers
        $sheet->setCellValueByColumnAndRow($anc,      $r, 'Week');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($toqRootCols[$root['id']], $r, $this->shortName($root['name']));
        }
        $sheet->setCellValueByColumnAndRow($toqTotal, $r, 'Total');
        $this->fillSecRange($sheet, $anc, $toqLastCol, $r, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $anc, $toqLastCol, $r);
        $r++;

        foreach ($weeklyRows as $wRow) {
            $sheet->setCellValueByColumnAndRow($anc,      $r, $wRow['label']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $toqRootCols[$root['id']], $r, $wRow['root_orders'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($toqTotal, $r, $wRow['orders']);
            $this->alignSecRow($sheet, $anc, $toqLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $anc, $toqLastCol, $r, self::CLR_SEC_ALT);
            $r++;
        }
        // Total row
        $sheet->setCellValueByColumnAndRow($anc,      $r, 'Total');
        foreach ($rootPlatforms as $root) {
            $rid = $root['id'];
            $sum = array_sum(array_column(array_column($weeklyRows, 'root_orders'), $rid));
            $sheet->setCellValueByColumnAndRow($toqRootCols[$rid], $r, $sum);
        }
        $sheet->setCellValueByColumnAndRow($toqTotal, $r, $totals['orders']);
        $this->fillSecRange($sheet, $anc, $toqLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $toqLastCol, $r);
        $toqSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $toqLastCol, $toqSecStart, $toqSecEnd);
        $r++;

        // ─────────────────────────────────────────────────────────────
        // SECTION 2b – Total Order Item QTY  (qty/pieces per week) ───
        //   F=Week | G=[root1 QTY] | … | G+numRoots=Total QTY | Kids | Female | Male
        // ─────────────────────────────────────────────────────────────
        $r += 4;

        $tiqRootStart = $anc + 1;
        $tiqTotal     = $anc + 1 + $numRoots;
        $tiqKids      = $anc + 2 + $numRoots;
        $tiqFemale    = $anc + 3 + $numRoots;
        $tiqMale      = $anc + 4 + $numRoots;
        $tiqLastCol   = $tiqMale;

        $tiqRootCols = [];
        foreach ($rootPlatforms as $i => $root) {
            $tiqRootCols[$root['id']] = $tiqRootStart + $i;
        }

        $tiqSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $tiqLastCol, $r, 'Total Order Item QTY');
        $r++;

        // Column headers
        $sheet->setCellValueByColumnAndRow($anc,       $r, 'Week');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($tiqRootCols[$root['id']], $r, $this->shortName($root['name']));
        }
        $sheet->setCellValueByColumnAndRow($tiqTotal,  $r, 'Total');
        $sheet->setCellValueByColumnAndRow($tiqKids,   $r, 'Kids');
        $sheet->setCellValueByColumnAndRow($tiqFemale, $r, 'Female');
        $sheet->setCellValueByColumnAndRow($tiqMale,   $r, 'Male');
        $this->fillSecRange($sheet, $anc, $tiqLastCol, $r, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $anc, $tiqLastCol, $r);
        $r++;

        foreach ($weeklyRows as $wRow) {
            $sheet->setCellValueByColumnAndRow($anc,       $r, $wRow['label']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $tiqRootCols[$root['id']], $r, $wRow['root_qty'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($tiqTotal,  $r, $wRow['qty']);
            $sheet->setCellValueByColumnAndRow($tiqKids,   $r, $wRow['kids']);
            $sheet->setCellValueByColumnAndRow($tiqFemale, $r, $wRow['female']);
            $sheet->setCellValueByColumnAndRow($tiqMale,   $r, $wRow['male']);
            $this->alignSecRow($sheet, $anc, $tiqLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $anc, $tiqLastCol, $r, self::CLR_SEC_ALT);
            $r++;
        }
        // Total row
        $sheet->setCellValueByColumnAndRow($anc,       $r, 'Total');
        foreach ($rootPlatforms as $root) {
            $rid = $root['id'];
            $sum = array_sum(array_column(array_column($weeklyRows, 'root_qty'), $rid));
            $sheet->setCellValueByColumnAndRow($tiqRootCols[$rid], $r, $sum);
        }
        $sheet->setCellValueByColumnAndRow($tiqTotal,  $r, $totals['qty']);
        $sheet->setCellValueByColumnAndRow($tiqKids,   $r, $totals['kids']);
        $sheet->setCellValueByColumnAndRow($tiqFemale, $r, $totals['female']);
        $sheet->setCellValueByColumnAndRow($tiqMale,   $r, $totals['male']);
        $this->fillSecRange($sheet, $anc, $tiqLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $tiqLastCol, $r);
        $tiqSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $tiqLastCol, $tiqSecStart, $tiqSecEnd);
        $r++;

        // ─────────────────────────────────────────────────────────────
        // SECTION 3 – Return Breakdown  (after 4 blank rows)
        //   F=Reason | G…=per-root | Kids | Female | Male
        // ─────────────────────────────────────────────────────────────
        $r += 4;

        $retLabelCol  = $anc;
        $retRootStart = $anc + 1;
        $retRootCols  = [];
        foreach ($rootPlatforms as $i => $root) {
            $retRootCols[$root['id']] = $retRootStart + $i;
        }
        $retKidsCol   = $retRootStart + $numRoots;
        $retFemaleCol = $retKidsCol + 1;
        $retMaleCol   = $retFemaleCol + 1;
        $retLastCol   = $retMaleCol;

        $retSecStart = $r;
        $this->writeSectionTitle($sheet, $anc, $retLastCol, $r, 'Return Breakdown');
        $r++;

        $sheet->setCellValueByColumnAndRow($retLabelCol, $r, 'Reason');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($retRootCols[$root['id']], $r, $this->shortName($root['name']));
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,   $r, 'Kids');
        $sheet->setCellValueByColumnAndRow($retFemaleCol, $r, 'Female');
        $sheet->setCellValueByColumnAndRow($retMaleCol,   $r, 'Male');
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $anc, $retLastCol, $r);
        $r++;

        foreach ($returnReasonData['reasons'] as $reason) {
            $sheet->setCellValueByColumnAndRow($retLabelCol, $r, $reason['name']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow(
                    $retRootCols[$root['id']], $r, $reason['by_root'][$root['id']] ?? 0
                );
            }
            $sheet->setCellValueByColumnAndRow($retKidsCol,   $r, $reason['kids']);
            $sheet->setCellValueByColumnAndRow($retFemaleCol, $r, $reason['female']);
            $sheet->setCellValueByColumnAndRow($retMaleCol,   $r, $reason['male']);
            $this->alignSecRow($sheet, $anc, $retLastCol, $r);
            if ($r % 2 === 0) $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_SEC_ALT);
            $r++;
        }
        $sheet->setCellValueByColumnAndRow($retLabelCol, $r, 'Total');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow(
                $retRootCols[$root['id']], $r, $returnReasonData['totals_by_root'][$root['id']] ?? 0
            );
        }
        $sheet->setCellValueByColumnAndRow($retKidsCol,   $r, $returnReasonData['totals_kids']);
        $sheet->setCellValueByColumnAndRow($retFemaleCol, $r, $returnReasonData['totals_female']);
        $sheet->setCellValueByColumnAndRow($retMaleCol,   $r, $returnReasonData['totals_male']);
        $this->fillSecRange($sheet, $anc, $retLastCol, $r, self::CLR_TOTAL, true);
        $this->alignSecRow($sheet, $anc, $retLastCol, $r);
        $retSecEnd = $r;
        $this->sectionBorder($sheet, $anc, $retLastCol, $retSecStart, $retSecEnd);

        // ── Number formats – main area ───────────────────────────────
        $sheet->getStyle('C' . $dataStartRow . ':C' . $lastMainRow)->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle('E' . $dataStartRow . ':E' . $lastMainRow)->getNumberFormat()->setFormatCode($moneyFmt);
        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle('D' . $dataStartRow . ':D' . $dataEndRow)->getNumberFormat()->setFormatCode('0.00%');
        }
        if ($numPlatCols > 0) {
            $pStart = Coordinate::stringFromColumnIndex($platBaseCol);
            $pEnd   = Coordinate::stringFromColumnIndex($platBaseCol + $numPlatCols - 1);
            $sheet->getStyle("{$pStart}{$dataStartRow}:{$pEnd}{$lastMainRow}")->getNumberFormat()->setFormatCode($moneyFmt);
        }

        // ── Borders – main area ──────────────────────────────────────
        $mainRange = 'A1:' . Coordinate::stringFromColumnIndex($mainLastCol) . $lastMainRow;
        $sheet->getStyle($mainRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ── Column widths ────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);
        for ($ci = $platBaseCol; $ci <= $platBaseCol + $numPlatCols - 1; $ci++) {
            $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
        }
        // Right section in main area
        for ($ci = $rsBase; $ci <= $mainLastCol; $ci++) {
            $sheet->getColumnDimensionByColumn($ci)->setWidth(10);
        }
        // Bottom sections: label col wider, value cols standard
        $maxSecLastCol = max($wbLastCol, $toqLastCol, $tiqLastCol, $retLastCol);
        $sheet->getColumnDimensionByColumn($anc)->setWidth(18); // F label col
        for ($ci = $anc + 1; $ci <= $maxSecLastCol; $ci++) {
            $sheet->getColumnDimensionByColumn($ci)->setWidth(12);
        }

        // ── Row height for section title rows ────────────────────────
        $sheet->getRowDimension($wbSecStart)->setRowHeight(22);
        $sheet->getRowDimension($toqSecStart)->setRowHeight(22);
        $sheet->getRowDimension($tiqSecStart)->setRowHeight(22);
        $sheet->getRowDimension($retSecStart)->setRowHeight(22);

        // ── Freeze panes ─────────────────────────────────────────────
        $sheet->freezePane('C' . $dataStartRow);

        // ── Stream ───────────────────────────────────────────────────
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

    // ══════════════════════════════════════════════════════════════════
    // ── Style helpers ─────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════

    private function styleTitle($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(13)->getColor()->setARGB(self::CLR_TITLE_FG);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);
    }

    private function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_HDR_BG);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_HDR_FG);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function applyPlatformGroupStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::CLR_PLAT_BG);
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_PLAT_FG);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function fillRow($sheet, int $row, int $lastColIdx, string $argb): void
    {
        $range = 'A' . $row . ':' . Coordinate::stringFromColumnIndex($lastColIdx) . $row;
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    private function fillSecRange($sheet, int $colStart, int $colEnd, int $row, string $argb, bool $bold = false): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $row
               . ':' . Coordinate::stringFromColumnIndex($colEnd) . $row;
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
        if ($bold) $sheet->getStyle($range)->getFont()->setBold(true);
    }

    private function writeSectionTitle($sheet, int $colStart, int $colEnd, int $row, string $title): void
    {
        $startLtr = Coordinate::stringFromColumnIndex($colStart);
        $endLtr   = Coordinate::stringFromColumnIndex($colEnd);
        $sheet->setCellValue($startLtr . $row, $title);
        $sheet->mergeCells("{$startLtr}{$row}:{$endLtr}{$row}");
        $sheet->getStyle("{$startLtr}{$row}:{$endLtr}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SEC_TITLE);
        $sheet->getStyle("{$startLtr}{$row}:{$endLtr}{$row}")->getFont()
            ->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("{$startLtr}{$row}:{$endLtr}{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function writeSectionHeaders($sheet, int $colStart, array $labels, int $row): void
    {
        foreach ($labels as $i => $lbl) {
            $sheet->setCellValueByColumnAndRow($colStart + $i, $row, $lbl);
        }
        $colEnd = $colStart + count($labels) - 1;
        $this->fillSecRange($sheet, $colStart, $colEnd, $row, self::CLR_SEC_HDR, true);
        $this->applySecHdrTextStyle($sheet, $colStart, $colEnd, $row);
    }

    private function applySecHdrTextStyle($sheet, int $colStart, int $colEnd, int $row): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $row
               . ':' . Coordinate::stringFromColumnIndex($colEnd) . $row;
        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function alignSecRow($sheet, int $colStart, int $colEnd, int $row): void
    {
        $sheet->getStyleByColumnAndRow($colStart, $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        if ($colEnd > $colStart) {
            $startLtr = Coordinate::stringFromColumnIndex($colStart + 1);
            $endLtr   = Coordinate::stringFromColumnIndex($colEnd);
            $sheet->getStyle("{$startLtr}{$row}:{$endLtr}{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    private function sectionBorder($sheet, int $colStart, int $colEnd, int $rowStart, int $rowEnd): void
    {
        $range = Coordinate::stringFromColumnIndex($colStart) . $rowStart
               . ':' . Coordinate::stringFromColumnIndex($colEnd) . $rowEnd;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function shortName(string $name): string
    {
        $name = preg_replace('/\s*(platform|marketplace|store)\s*/i', '', $name);
        $name = trim($name);
        return mb_strlen($name) > 10 ? mb_substr($name, 0, 9) . '.' : $name;
    }
}

