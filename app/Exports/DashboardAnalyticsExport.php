<?php

namespace App\Exports;

use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardAnalyticsExport
{
    // Colour palette (matches Excel original)
    private const CLR_TITLE  = 'FF00B0F0'; // bright cyan header
    private const CLR_HEADER = 'FF00B0F0'; // column header
    private const CLR_WEEK   = 'FFFFC000'; // week label (amber)
    private const CLR_TOTAL  = 'FFBDD7EE'; // total row
    private const CLR_BUDGET = 'FFE2EFDA'; // budget row (light green)
    private const CLR_FORE   = 'FFFFEB9C'; // forecasting (light yellow)
    private const CLR_ROAS   = 'FFFCE4D6'; // ROI row
    private const CLR_WHITE  = 'FFFFFFFF';
    private const CLR_ALT    = 'FFF2F2F2'; // alternate row

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private array  $months,
        private array  $label,   // ['label' => string]
    ) {}

    public function download(DashboardAnalyticsService $service): StreamedResponse
    {
        $export = $service->getDailyExportData($this->dateFrom, $this->dateTo, $this->months);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        $platforms     = $export['platforms'];        // array of {id, name, parent_id}
        $rootPlatforms = $export['root_platforms'];   // top-level platforms
        $rows          = $export['rows'];
        $totals        = $export['totals'];
        $ptotals       = $export['platform_totals'];
        $budgets       = $export['budgets'];
        $avgDaily      = $export['avg_daily'];
        $forecast      = $export['forecast'];

        // ── Column layout ───────────────────────────────────────────
        // A=Week, B=Date, C=Daily Sales, D=ROAS%, E=Daily Spend
        // then per platform: CostCol, SalesCol  (2 cols each)
        // then: Total Orders, per-root Orders, Total QTY, per-root QTY, Kids, Female, Male
        $baseCol  = 5;  // columns A–E
        $platCols = []; // platform_id => [costColIdx, salesColIdx]
        $colIdx   = $baseCol + 1;

        foreach ($platforms as $p) {
            $platCols[$p['id']] = ['cost' => $colIdx, 'sales' => $colIdx + 1];
            $colIdx += 2;
        }

        $orderStartCol = $colIdx;
        $colIdx++;                             // Total Orders
        $rootOrderCols = [];
        foreach ($rootPlatforms as $root) {
            $rootOrderCols[$root['id']] = $colIdx++;
        }

        $qtyTotalCol  = $colIdx++;
        $rootQtyCols  = [];
        foreach ($rootPlatforms as $root) {
            $rootQtyCols[$root['id']] = $colIdx++;
        }

        $kidsCol   = $colIdx++;
        $femaleCol = $colIdx++;
        $maleCol   = $colIdx++;
        $totalCols = $colIdx - 1;  // last used column index

        // ── Row 1: Title ───────────────────────────────────────────
        $titleStr = 'Tracking digital Marketing COST VS Allocation – ' . ($this->label['label'] ?? '');
        $sheet->setCellValue('A1', $titleStr);
        $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($totalCols) . '1');
        $this->styleTitle($sheet, 'A1:' . Coordinate::stringFromColumnIndex($totalCols) . '1');

        // ── Row 2: Headers ─────────────────────────────────────────
        $r = 2;
        $sheet->setCellValue('A' . $r, 'Week');
        $sheet->setCellValue('B' . $r, 'Date');
        $sheet->setCellValue('C' . $r, 'Daily Sales');
        $sheet->setCellValue('D' . $r, 'Daily ROAS%');
        $sheet->setCellValue('E' . $r, 'Daily Spend');

        foreach ($platforms as $p) {
            $cc = $platCols[$p['id']]['cost'];
            $sc = $platCols[$p['id']]['sales'];
            $sheet->setCellValueByColumnAndRow($cc, $r, $p['name'] . ' Cost');
            $sheet->setCellValueByColumnAndRow($sc, $r, $p['name'] . ' Sales');
        }

        $sheet->setCellValueByColumnAndRow($orderStartCol, $r, 'Total Orders');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($rootOrderCols[$root['id']], $r, $root['name'] . ' Orders');
        }
        $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, 'Total QTY');
        foreach ($rootPlatforms as $root) {
            $sheet->setCellValueByColumnAndRow($rootQtyCols[$root['id']], $r, $root['name'] . ' QTY');
        }
        $sheet->setCellValueByColumnAndRow($kidsCol,   $r, 'Kids');
        $sheet->setCellValueByColumnAndRow($femaleCol, $r, 'Female');
        $sheet->setCellValueByColumnAndRow($maleCol,   $r, 'Male');

        $this->styleHeader($sheet, 'A2:' . Coordinate::stringFromColumnIndex($totalCols) . '2');

        // ── Data rows ──────────────────────────────────────────────
        $r           = 3;
        $dataStartRow = $r;
        $weekRanges  = []; // week_num => [firstRow, lastRow]
        $prevWeek    = null;

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
            $sheet->setCellValue('D' . $r, $row['roas'] / 100);  // percentage
            $sheet->setCellValue('E' . $r, $row['total_spent']);

            foreach ($platforms as $p) {
                $pd = $row['platform'][$p['id']] ?? ['cost' => 0, 'sales' => 0];
                $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['cost'],  $r, $pd['cost']);
                $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['sales'], $r, $pd['sales']);
            }

            $sheet->setCellValueByColumnAndRow($orderStartCol, $r, $row['total_orders']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow($rootOrderCols[$root['id']], $r, $row['root_groups'][$root['id']]['orders'] ?? 0);
            }
            $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $row['total_qty']);
            foreach ($rootPlatforms as $root) {
                $sheet->setCellValueByColumnAndRow($rootQtyCols[$root['id']], $r, $row['root_groups'][$root['id']]['qty'] ?? 0);
            }
            $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $row['kids']);
            $sheet->setCellValueByColumnAndRow($femaleCol, $r, $row['female']);
            $sheet->setCellValueByColumnAndRow($maleCol,   $r, $row['male']);

            // Alternate row shading
            if ($r % 2 === 0) {
                $this->fillRow($sheet, $r, $totalCols, self::CLR_ALT);
            }

            $r++;
        }
        $dataEndRow = $r - 1;

        // Merge week label cells in col A
        foreach ($weekRanges as $wn => $range) {
            if ($range['end'] > $range['start']) {
                $sheet->mergeCells('A' . $range['start'] . ':A' . $range['end']);
                $sheet->getStyle('A' . $range['start'])->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            }
            $this->fillRow($sheet, $range['start'], 1, self::CLR_WEEK, true);
        }

        // ── Summary rows ───────────────────────────────────────────
        // Row: Total Spend
        $sheet->setCellValue('B' . $r, 'Total Spend');
        $sheet->setCellValue('E' . $r, $totals['spent']);
        foreach ($platforms as $p) {
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['cost'],  $r, $ptotals[$p['id']]['cost']);
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['sales'], $r, $ptotals[$p['id']]['sales']);
        }
        $sheet->setCellValueByColumnAndRow($orderStartCol, $r, $totals['orders']);
        foreach ($rootPlatforms as $root) {
            $rootTotal = 0;
            foreach ($rows as $row) {
                $rootTotal += ($row['root_groups'][$root['id']]['orders'] ?? 0);
            }
            $sheet->setCellValueByColumnAndRow($rootOrderCols[$root['id']], $r, $rootTotal);
        }
        $sheet->setCellValueByColumnAndRow($qtyTotalCol, $r, $totals['qty']);
        $sheet->setCellValueByColumnAndRow($kidsCol,   $r, $totals['kids']);
        $sheet->setCellValueByColumnAndRow($femaleCol, $r, $totals['female']);
        $sheet->setCellValueByColumnAndRow($maleCol,   $r, $totals['male']);
        $this->fillRow($sheet, $r, $totalCols, self::CLR_TOTAL);
        $this->setBold($sheet, 'B' . $r);
        $totalSpendRow = $r++;

        // Row: ROI
        $sheet->setCellValue('B' . $r, 'ROI %');
        if ($totals['spent'] > 0) {
            $sheet->setCellValue('E' . $r, $totals['sales'] / $totals['spent']);
            $sheet->getStyle('E' . $r)->getNumberFormat()->setFormatCode('0.00%');
        }
        foreach ($platforms as $p) {
            $pc = $ptotals[$p['id']]['cost'];
            $ps = $ptotals[$p['id']]['sales'];
            if ($pc > 0) {
                $ci = $platCols[$p['id']]['cost'];
                $sheet->setCellValueByColumnAndRow($ci, $r, $ps / $pc);
                $sheet->getStyleByColumnAndRow($ci, $r)->getNumberFormat()->setFormatCode('0.00%');
            }
        }
        $this->fillRow($sheet, $r, $totalCols, self::CLR_ROAS);
        $this->setBold($sheet, 'B' . $r);
        $r++;

        // Row: Total Budget
        $sheet->setCellValue('B' . $r, 'Total Budget');
        $totalBudget = array_sum($budgets);
        $sheet->setCellValue('E' . $r, $totalBudget);
        foreach ($platforms as $p) {
            $b = $budgets[$p['id']] ?? 0;
            if ($b > 0) {
                $bc = $platCols[$p['id']]['cost'];
                $sheet->setCellValueByColumnAndRow($bc, $r, $b);
            }
        }
        $this->fillRow($sheet, $r, $totalCols, self::CLR_BUDGET);
        $this->setBold($sheet, 'B' . $r);
        $budgetRow = $r++;

        // Row: Balance Budget
        $sheet->setCellValue('B' . $r, 'Balance Budget');
        $totalBalance = $totalBudget - $totals['spent'];
        $sheet->setCellValue('E' . $r, $totalBalance);
        foreach ($platforms as $p) {
            $b    = $budgets[$p['id']] ?? 0;
            $cost = $ptotals[$p['id']]['cost'];
            $ci   = $platCols[$p['id']]['cost'];
            if ($b > 0 || $cost > 0) {
                $sheet->setCellValueByColumnAndRow($ci, $r, $b - $cost);
            }
        }
        $this->fillRow($sheet, $r, $totalCols, self::CLR_BUDGET);
        $this->setBold($sheet, 'B' . $r);
        $r++;

        // Row: Average Daily Sales
        $sheet->setCellValue('B' . $r, 'Average Sales Daily');
        $dayCount = max(1, $dataEndRow - $dataStartRow + 1);
        $sheet->setCellValue('C' . $r, $totals['sales'] / $dayCount);
        $sheet->setCellValue('E' . $r, $totals['spent'] / $dayCount);
        foreach ($platforms as $p) {
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['cost'],  $r, $ptotals[$p['id']]['cost']  / $dayCount);
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['sales'], $r, $ptotals[$p['id']]['sales'] / $dayCount);
        }
        $sheet->setCellValueByColumnAndRow($orderStartCol, $r, $totals['orders'] / $dayCount);
        $this->fillRow($sheet, $r, $totalCols, self::CLR_WHITE);
        $this->setBold($sheet, 'B' . $r);
        $r++;

        // Row: Total Sale
        $sheet->setCellValue('B' . $r, 'Total Sale');
        $sheet->setCellValue('C' . $r, $totals['sales']);
        foreach ($platforms as $p) {
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['sales'], $r, $ptotals[$p['id']]['sales']);
        }
        $this->fillRow($sheet, $r, $totalCols, self::CLR_TOTAL);
        $this->setBold($sheet, 'B' . $r);
        $r++;

        // Row: Forecasting
        $sheet->setCellValue('B' . $r, 'Forecasting (30 days)');
        $sheet->setCellValue('C' . $r, $forecast['sales']);
        $sheet->setCellValue('E' . $r, $forecast['spent']);
        foreach ($platforms as $p) {
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['cost'],  $r, ($ptotals[$p['id']]['cost']  / $dayCount) * 30);
            $sheet->setCellValueByColumnAndRow($platCols[$p['id']]['sales'], $r, ($ptotals[$p['id']]['sales'] / $dayCount) * 30);
        }
        $this->fillRow($sheet, $r, $totalCols, self::CLR_FORE);
        $this->setBold($sheet, 'B' . $r);
        $lastRow = $r;

        // ── Apply number formats and borders ───────────────────────
        $moneyFmt = '#,##0.00';
        $dataRange = 'C3:E' . $lastRow;
        $sheet->getStyle($dataRange)->getNumberFormat()->setFormatCode($moneyFmt);

        // Platform cost/sales columns
        foreach ($platforms as $p) {
            $cc = Coordinate::stringFromColumnIndex($platCols[$p['id']]['cost']);
            $sc = Coordinate::stringFromColumnIndex($platCols[$p['id']]['sales']);
            $sheet->getStyle("{$cc}3:{$cc}{$lastRow}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("{$sc}3:{$sc}{$lastRow}")->getNumberFormat()->setFormatCode($moneyFmt);
        }

        // ROAS column (D) as percentage
        $sheet->getStyle('D3:D' . $dataEndRow)->getNumberFormat()->setFormatCode('0.00%');

        // All borders
        $fullRange = 'A1:' . Coordinate::stringFromColumnIndex($totalCols) . $lastRow;
        $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ── Column widths ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);
        for ($ci = 6; $ci <= $totalCols; $ci++) {
            $sheet->getColumnDimensionByColumn($ci)->setWidth(13);
        }

        // ── Freeze panes ───────────────────────────────────────────
        $sheet->freezePane('C3');

        // ── Output ─────────────────────────────────────────────────
        $filename = 'analytics-' . str_replace(' ', '_', strtolower($this->label['label'] ?? 'report'))
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

    private function styleHeader($sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1F3864'));
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HEADER);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(32);
    }

    private function fillRow($sheet, int $row, int $lastColIdx, string $argb, bool $singleColOnly = false): void
    {
        if ($singleColOnly) {
            $range = 'A' . $row;
        } else {
            $range = 'A' . $row . ':' . Coordinate::stringFromColumnIndex($lastColIdx) . $row;
        }
        $sheet->getStyle($range)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB($argb);
    }

    private function setBold($sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getFont()->setBold(true);
    }
}

