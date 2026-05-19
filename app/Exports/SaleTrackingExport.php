<?php

namespace App\Exports;

use App\Services\SaleTrackingService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleTrackingExport
{
    // ── Colour palette ──────────────────────────────────────────
    private const CLR_TITLE_BG   = 'FF005C3E';
    private const CLR_TITLE_FG   = 'FFFFFFFF';
    private const CLR_HDR_BG     = 'FF009966';
    private const CLR_HDR_FG     = 'FFFFFFFF';
    private const CLR_ROW_ALT    = 'FFF0FAF5';
    private const CLR_TOTAL      = 'FFB3E6CC';
    private const CLR_TOTAL_FG   = 'FF003D2B';
    private const CLR_SEC_BG     = 'FF003D2B';
    private const CLR_SEC_FG     = 'FFFFFFFF';
    private const CLR_SUMHDR_BG  = 'FF52B08C';
    private const CLR_SUMHDR_FG  = 'FFFFFFFF';
    private const CLR_SUM_ALT    = 'FFE6F3F0';
    private const CLR_SUM_TOTAL  = 'FFCCEEDD';

    // Per-platform header colour cycle
    private const PLAT_COLORS = [
        'FF1A73E8','FFE37400','FF34A853','FFEA4335',
        'FF9334E6','FF00897B','FFFF6D00','FF0097A7',
    ];

    // ── Main data column definitions ────────────────────────────
    private const COLUMNS = [
        'A' => ['label' => 'Sl. No',           'width' => 7],
        'B' => ['label' => 'Month',             'width' => 13],
        'C' => ['label' => 'Platform',          'width' => 28],
        'D' => ['label' => 'Reach',             'width' => 13],
        'E' => ['label' => 'Impressions',       'width' => 14],
        'F' => ['label' => 'Clicks',            'width' => 11],
        'G' => ['label' => 'Sessions',          'width' => 11],
        'H' => ['label' => 'Engaged Sessions',  'width' => 16],
        'I' => ['label' => 'Users',             'width' => 10],
        'J' => ['label' => 'Net Cost (£)',      'width' => 14],
        'K' => ['label' => 'Ads Tax (£)',       'width' => 14],
        'L' => ['label' => 'Total Cost (£)',    'width' => 14],
        'M' => ['label' => 'Orders',            'width' => 10],
        'N' => ['label' => 'Products',          'width' => 10],
        'O' => ['label' => 'Sales Growth %',    'width' => 14],
        'P' => ['label' => 'Revenue (£)',       'width' => 14],
        'Q' => ['label' => 'Total Revenue (£)', 'width' => 16],
        'R' => ['label' => 'Total Return (£)',  'width' => 15],
        'S' => ['label' => 'Net Revenue (£)',   'width' => 15],
        'T' => ['label' => 'ROI (%)',           'width' => 11],
        'U' => ['label' => 'ROAS',              'width' => 10],
    ];
    private const LAST_COL = 'U';

    public function __construct(private array $filters = []) {}

    // ─────────────────────────────────────────────────────────────
    //  DOWNLOAD
    // ─────────────────────────────────────────────────────────────

    public function download(SaleTrackingService $service): StreamedResponse
    {
        $records = $service->getExportQuery($this->filters)->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Ad Performance');

        if ($records->isEmpty()) {
            $sheet->setCellValue('A1', 'No data found for the selected filters.');
        } else {
            $this->writeSheet($sheet, $records);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'ad-tracking-' . now()->format('Y-m-d') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  SHEET WRITER — main entry point
    // ─────────────────────────────────────────────────────────────

    private function writeSheet($sheet, $records): void
    {
        $sheetName = 'Ad Performance';
        $moneyFmt  = '#,##0.00';
        $pctFmt    = '0.00%';
        $numFmt    = '#,##0';

        // ── Group records by month (preserves month-desc query order) ──
        $monthGroups   = [];  // [Y-m => [records]]
        $platformData  = [];  // [platName => [Y-m => [label,reach,imp,clicks,sessions,orders]]]
        $monthAgg      = [];  // [Y-m => aggregate for summary]

        foreach ($records as $rec) {
            $mk       = optional($rec->month)->format('Y-m') ?? 'unknown';
            $platName = $rec->salePlatform?->name ?? '—';
            $monthLabel = optional($rec->month)->format('M Y') ?? $mk;

            $monthGroups[$mk][] = $rec;

            // Per-platform data
            if (!isset($platformData[$platName][$mk])) {
                $platformData[$platName][$mk] = [
                    'label' => $monthLabel, 'reach' => 0, 'impressions' => 0,
                    'clicks' => 0, 'sessions' => 0, 'orders' => 0,
                ];
            }
            $platformData[$platName][$mk]['reach']       += (int)($rec->reach ?? 0);
            $platformData[$platName][$mk]['impressions'] += (int)($rec->impressions ?? 0);
            $platformData[$platName][$mk]['clicks']      += (int)($rec->clicks ?? 0);
            $platformData[$platName][$mk]['sessions']    += (int)($rec->sessions ?? 0);
            $platformData[$platName][$mk]['orders']      += (int)($rec->number_of_orders ?? 0);

            // Monthly aggregates
            if (!isset($monthAgg[$mk])) {
                $monthAgg[$mk] = [
                    'label' => $monthLabel, 'revenue' => 0, 'total_cost' => 0,
                    'net_revenue' => 0, 'orders' => 0, 'clicks' => 0, 'impressions' => 0,
                    'roi_sum' => 0, 'roi_count' => 0, 'roas_sum' => 0, 'roas_count' => 0,
                ];
            }
            $monthAgg[$mk]['revenue']     += (float)($rec->revenue ?? 0);
            $monthAgg[$mk]['total_cost']  += (float)($rec->total_cost ?? 0);
            $monthAgg[$mk]['net_revenue'] += (float)($rec->net_revenue ?? 0);
            $monthAgg[$mk]['orders']      += (int)($rec->number_of_orders ?? 0);
            $monthAgg[$mk]['clicks']      += (int)($rec->clicks ?? 0);
            $monthAgg[$mk]['impressions'] += (int)($rec->impressions ?? 0);
            if ($rec->roi  !== null) { $monthAgg[$mk]['roi_sum']   += (float)$rec->roi;  $monthAgg[$mk]['roi_count']++; }
            if ($rec->roas !== null) { $monthAgg[$mk]['roas_sum']  += (float)$rec->roas; $monthAgg[$mk]['roas_count']++; }
        }

        // ── Row 1: Title (CENTERED) ───────────────────────────
        $titleRange = 'A1:' . self::LAST_COL . '1';
        $sheet->setCellValue('A1', 'Enorsia Digital Ad Performance Tracking');
        $sheet->mergeCells($titleRange);
        $sheet->getStyle($titleRange)->getFont()->setBold(true)->setSize(13)->getColor()->setARGB(self::CLR_TITLE_FG);
        $sheet->getStyle($titleRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $sheet->getStyle($titleRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Row 2: Column headers ──────────────────────────────
        foreach (self::COLUMNS as $col => $def) {
            $sheet->setCellValue($col . '2', $def['label']);
            $sheet->getColumnDimension($col)->setWidth($def['width']);
        }
        $hdrRange = 'A2:' . self::LAST_COL . '2';
        $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HDR_BG);
        $sheet->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_HDR_FG);
        $sheet->getStyle($hdrRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(32);

        // ── Data rows (with month cell merging) ───────────────
        $r      = 3;
        $sl     = 1;
        $totals = array_fill_keys(
            ['reach','impressions','clicks','sessions','engaged_sessions','users',
             'net_cost','ads_tax_payments','total_cost','number_of_orders','number_of_products',
             'revenue','total_revenue','total_return','net_revenue'],
            0.0
        );

        foreach ($monthGroups as $mk => $monthRecs) {
            $monthStartRow = $r;
            $monthLabel    = optional($monthRecs[0]->month)->format('M Y') ?? '';

            foreach ($monthRecs as $rec) {
                $isAlt = ($r % 2 === 0);
                $sheet->setCellValue('A' . $r, $sl++);
                $sheet->setCellValue('B' . $r, $monthLabel);
                $sheet->setCellValue('C' . $r, $rec->salePlatform?->name ?? '—');
                $sheet->setCellValue('D' . $r, $rec->reach);
                $sheet->setCellValue('E' . $r, $rec->impressions);
                $sheet->setCellValue('F' . $r, $rec->clicks);
                $sheet->setCellValue('G' . $r, $rec->sessions);
                $sheet->setCellValue('H' . $r, $rec->engaged_sessions);
                $sheet->setCellValue('I' . $r, $rec->users);
                $sheet->setCellValue('J' . $r, $rec->net_cost);
                $sheet->setCellValue('K' . $r, $rec->ads_tax_payments);
                $sheet->setCellValue('L' . $r, $rec->total_cost);
                $sheet->setCellValue('M' . $r, $rec->number_of_orders);
                $sheet->setCellValue('N' . $r, $rec->number_of_products);
                $sheet->setCellValue('O' . $r, $rec->sales_grow_percent !== null ? $rec->sales_grow_percent / 100 : null);
                $sheet->setCellValue('P' . $r, $rec->revenue);
                $sheet->setCellValue('Q' . $r, $rec->total_revenue);
                $sheet->setCellValue('R' . $r, $rec->total_return);
                $sheet->setCellValue('S' . $r, $rec->net_revenue);
                $sheet->setCellValue('T' . $r, $rec->roi   !== null ? $rec->roi   / 100 : null);
                $sheet->setCellValue('U' . $r, $rec->roas);

                if ($isAlt) {
                    $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_ROW_ALT);
                }
                $sheet->getStyle('D' . $r . ':' . self::LAST_COL . $r)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                foreach (array_keys($totals) as $key) {
                    $totals[$key] += (float)($rec->$key ?? 0);
                }
                $r++;
            }

            $monthEndRow = $r - 1;

            // Merge & center month cell in column B
            if ($monthEndRow > $monthStartRow) {
                $sheet->mergeCells('B' . $monthStartRow . ':B' . $monthEndRow);
            }
            $sheet->getStyle('B' . $monthStartRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        $dataEndRow = $r - 1;

        // ── Totals row ─────────────────────────────────────────
        $sheet->setCellValue('C' . $r, 'TOTAL');
        $sheet->setCellValue('D' . $r, $totals['reach']              ?: null);
        $sheet->setCellValue('E' . $r, $totals['impressions']        ?: null);
        $sheet->setCellValue('F' . $r, $totals['clicks']             ?: null);
        $sheet->setCellValue('G' . $r, $totals['sessions']           ?: null);
        $sheet->setCellValue('H' . $r, $totals['engaged_sessions']   ?: null);
        $sheet->setCellValue('I' . $r, $totals['users']              ?: null);
        $sheet->setCellValue('J' . $r, $totals['net_cost']           ?: null);
        $sheet->setCellValue('K' . $r, $totals['ads_tax_payments']   ?: null);
        $sheet->setCellValue('L' . $r, $totals['total_cost']         ?: null);
        $sheet->setCellValue('M' . $r, $totals['number_of_orders']   ?: null);
        $sheet->setCellValue('N' . $r, $totals['number_of_products'] ?: null);
        $sheet->setCellValue('P' . $r, $totals['revenue']            ?: null);
        $sheet->setCellValue('Q' . $r, $totals['total_revenue']      ?: null);
        $sheet->setCellValue('R' . $r, $totals['total_return']       ?: null);
        $sheet->setCellValue('S' . $r, $totals['net_revenue']        ?: null);
        $totalsRow = $r;
        $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TOTAL);
        $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_TOTAL_FG);
        $sheet->getStyle('D' . $r . ':' . self::LAST_COL . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ── Number formats ─────────────────────────────────────
        if ($dataEndRow >= 3) {
            foreach (['J','K','L','P','Q','R','S'] as $col) {
                $sheet->getStyle($col . '3:' . $col . $totalsRow)->getNumberFormat()->setFormatCode($moneyFmt);
            }
            foreach (['D','E','F','G','H','I','M','N'] as $col) {
                $sheet->getStyle($col . '3:' . $col . $totalsRow)->getNumberFormat()->setFormatCode($numFmt);
            }
            foreach (['O','T'] as $col) {
                $sheet->getStyle($col . '3:' . $col . $totalsRow)->getNumberFormat()->setFormatCode($pctFmt);
            }
            $sheet->getStyle('U3:U' . $totalsRow)->getNumberFormat()->setFormatCode('0.00');
        }

        // ── Borders + freeze ───────────────────────────────────
        $sheet->getStyle('A1:' . self::LAST_COL . $totalsRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('D3');

        // ── Monthly Summary Table ──────────────────────────────
        $summaryStart = $totalsRow + 3;
        $summaryEnd   = $this->writeMonthlySummary($sheet, $monthAgg, $summaryStart);

        // ── Overview Charts (2×2 grid) ─────────────────────────
        $chartStart   = $summaryEnd + 3;
        $monthCount   = count($monthAgg);
        if ($monthCount >= 1) {
            $this->writeOverviewCharts($sheet, $sheetName, $summaryStart, $summaryEnd, $monthCount, $chartStart);
        }

        // ── Per-Platform Reach/Impressions/Clicks Tables + Charts ──
        $platformStart = $chartStart + 40; // after the 2-row overview charts
        $this->writePlatformSections($sheet, $sheetName, $platformData, $platformStart);
    }

    // ─────────────────────────────────────────────────────────────
    //  MONTHLY SUMMARY TABLE
    //  Cols: A=Month  B=Revenue  C=TotalCost  D=NetRevenue
    //        E=Orders  F=Clicks  G=Impressions  H=AvgROI%  I=AvgROAS
    // ─────────────────────────────────────────────────────────────

    private function writeMonthlySummary($sheet, array $monthAgg, int $startRow): int
    {
        if (empty($monthAgg)) return $startRow;

        // Section title — starts at col B, CENTERED
        $sheet->setCellValue('B' . $startRow, 'Monthly Performance Summary');
        $sheet->mergeCells('B' . $startRow . ':J' . $startRow);
        $sheet->getStyle('B' . $startRow)->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::CLR_SEC_FG);
        $sheet->getStyle('B' . $startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SEC_BG);
        $sheet->getStyle('B' . $startRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($startRow)->setRowHeight(22);

        // Headers — B=Month, C=Revenue … J=AvgROAS
        $hdrRow = $startRow + 1;
        foreach ([
            'B' => 'Month', 'C' => 'Revenue (£)', 'D' => 'Total Cost (£)',
            'E' => 'Net Revenue (£)', 'F' => 'Orders', 'G' => 'Clicks',
            'H' => 'Impressions', 'I' => 'Avg ROI (%)', 'J' => 'Avg ROAS',
        ] as $col => $label) {
            $sheet->setCellValue($col . $hdrRow, $label);
        }
        $sheet->getStyle('B' . $hdrRow . ':J' . $hdrRow)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUMHDR_BG);
        $sheet->getStyle('B' . $hdrRow . ':J' . $hdrRow)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_SUMHDR_FG);
        $sheet->getStyle('B' . $hdrRow . ':J' . $hdrRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($hdrRow)->setRowHeight(22);

        $r = $hdrRow + 1;
        $sl = 0;
        $sumRev = $sumCost = $sumNet = $sumOrd = $sumClk = $sumImp = 0.0;

        foreach ($monthAgg as $agg) {
            $avgRoi  = $agg['roi_count']  > 0 ? round($agg['roi_sum']  / $agg['roi_count'],  4) : null;
            $avgRoas = $agg['roas_count'] > 0 ? round($agg['roas_sum'] / $agg['roas_count'], 4) : null;

            if (($sl % 2) === 1) {
                $sheet->getStyle('B' . $r . ':J' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_ALT);
            }
            $sheet->setCellValue('B' . $r, $agg['label']);
            $sheet->setCellValue('C' . $r, $agg['revenue']);
            $sheet->setCellValue('D' . $r, $agg['total_cost']);
            $sheet->setCellValue('E' . $r, $agg['net_revenue']);
            $sheet->setCellValue('F' . $r, $agg['orders']);
            $sheet->setCellValue('G' . $r, $agg['clicks']);
            $sheet->setCellValue('H' . $r, $agg['impressions']);
            $sheet->setCellValue('I' . $r, $avgRoi  !== null ? $avgRoi  / 100 : null);
            $sheet->setCellValue('J' . $r, $avgRoas);
            $sheet->getStyle('C' . $r . ':J' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sumRev  += $agg['revenue'];    $sumCost += $agg['total_cost'];
            $sumNet  += $agg['net_revenue']; $sumOrd += $agg['orders'];
            $sumClk  += $agg['clicks'];     $sumImp  += $agg['impressions'];
            $sl++;  $r++;
        }

        // Totals row
        $sheet->setCellValue('B' . $r, 'TOTAL');
        foreach (['C' => $sumRev, 'D' => $sumCost, 'E' => $sumNet,
                  'F' => $sumOrd, 'G' => $sumClk,  'H' => $sumImp] as $col => $val) {
            $sheet->setCellValue($col . $r, $val ?: null);
        }
        $sheet->getStyle('B' . $r . ':J' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
        $sheet->getStyle('B' . $r . ':J' . $r)->getFont()->setBold(true);
        $sheet->getStyle('C' . $r . ':J' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Borders + number formats
        $sheet->getStyle('B' . $startRow . ':J' . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (['C','D','E'] as $c) {
            $sheet->getStyle($c . ($hdrRow+1) . ':' . $c . $r)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (['F','G','H'] as $c) {
            $sheet->getStyle($c . ($hdrRow+1) . ':' . $c . $r)->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle('I' . ($hdrRow+1) . ':I' . $r)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('J' . ($hdrRow+1) . ':J' . $r)->getNumberFormat()->setFormatCode('0.00');

        return $r;
    }

    // ─────────────────────────────────────────────────────────────
    //  OVERVIEW CHARTS  (2×2 grid — Revenue/Cost, Orders, ROI, ROAS)
    // ─────────────────────────────────────────────────────────────

    private function writeOverviewCharts($sheet, string $sn, int $summaryStart, int $summaryEnd, int $mc, int $chartTopRow): void
    {
        $dataStart = $summaryStart + 2;
        $dataEnd   = $summaryEnd - 1;
        if ($dataEnd < $dataStart) return;

        $xLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            "'$sn'" . '!$B$' . $dataStart . ':$B$' . $dataEnd, null, $mc)];

        $c1 = $this->buildBarChart($sn, 'Revenue vs Total Cost vs Net Revenue',
            [['col'=>'C','label'=>'Revenue (£)'],['col'=>'D','label'=>'Total Cost (£)'],['col'=>'E','label'=>'Net Revenue (£)']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c1->setTopLeftPosition('A' . $chartTopRow);
        $c1->setBottomRightPosition('K' . ($chartTopRow + 16));
        $sheet->addChart($c1);

        $c2 = $this->buildBarChart($sn, 'Orders by Month',
            [['col'=>'F','label'=>'Orders']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c2->setTopLeftPosition('L' . $chartTopRow);
        $c2->setBottomRightPosition('V' . ($chartTopRow + 16));
        $sheet->addChart($c2);

        $c3 = $this->buildLineChart($sn, 'Avg ROI (%) by Month',
            [['col'=>'I','label'=>'Avg ROI %']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c3->setTopLeftPosition('A' . ($chartTopRow + 18));
        $c3->setBottomRightPosition('K' . ($chartTopRow + 34));
        $sheet->addChart($c3);

        $c4 = $this->buildLineChart($sn, 'Avg ROAS by Month',
            [['col'=>'J','label'=>'Avg ROAS']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c4->setTopLeftPosition('L' . ($chartTopRow + 18));
        $c4->setBottomRightPosition('V' . ($chartTopRow + 34));
        $sheet->addChart($c4);
    }

    // ─────────────────────────────────────────────────────────────
    //  PER-PLATFORM SECTIONS
    //  Each platform: data table (A-F) + line chart beside (H-R)
    //  Table: Month | Reach | Impressions | Clicks | Sessions | Orders
    // ─────────────────────────────────────────────────────────────

    private function writePlatformSections($sheet, string $sheetName, array $platformData, int $startRow): void
    {
        if (empty($platformData)) return;

        $numFmt   = '#,##0';
        $colorIdx = 0;

        // Section banner — starts at col B
        $sheet->setCellValue('B' . $startRow, 'Per-Platform Engagement — Reach · Impressions · Clicks (Monthly)');
        $sheet->mergeCells('B' . $startRow . ':R' . $startRow);
        $sheet->getStyle('B' . $startRow)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::CLR_TITLE_FG);
        $sheet->getStyle('B' . $startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $sheet->getStyle('B' . $startRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $r = $startRow + 2;

        foreach ($platformData as $platName => $months) {
            // Only include platforms that have at least some reach/impressions/clicks data
            $hasEngagement = false;
            foreach ($months as $m) {
                if ($m['reach'] > 0 || $m['impressions'] > 0 || $m['clicks'] > 0) {
                    $hasEngagement = true;
                    break;
                }
            }
            if (!$hasEngagement) continue;

            $bgColor     = self::PLAT_COLORS[$colorIdx % count(self::PLAT_COLORS)];
            $monthCount  = count($months);
            $colorIdx++;

            // ── Platform title row — starts at col B ──────────
            $titleRow = $r;
            $sheet->setCellValue('B' . $r, $platName);
            $sheet->mergeCells('B' . $r . ':R' . $r);
            $sheet->getStyle('B' . $r)->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('B' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bgColor);
            $sheet->getStyle('B' . $r)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(22);
            $r++;

            // ── Table header row — B=Month C=Reach D=Impressions E=Clicks F=Orders ──
            $hdrRow = $r;
            foreach ([
                'B' => 'Month', 'C' => 'Reach', 'D' => 'Impressions',
                'E' => 'Clicks', 'F' => 'Orders',
            ] as $col => $label) {
                $sheet->setCellValue($col . $r, $label);
            }
            $sheet->getStyle('B' . $r . ':F' . $r)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUMHDR_BG);
            $sheet->getStyle('B' . $r . ':F' . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_SUMHDR_FG);
            $sheet->getStyle('B' . $r . ':F' . $r)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(20);
            $r++;

            // ── Data rows ──────────────────────────────────────
            $dataStart = $r;
            $si        = 0;
            $totReach  = $totImp = $totClk = $totOrd = 0;

            foreach ($months as $m) {
                $isAlt = ($si % 2 === 1);
                if ($isAlt) {
                    $sheet->getStyle('B' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_ALT);
                }
                $sheet->setCellValue('B' . $r, $m['label']);
                $sheet->setCellValue('C' . $r, $m['reach']       ?: null);
                $sheet->setCellValue('D' . $r, $m['impressions'] ?: null);
                $sheet->setCellValue('E' . $r, $m['clicks']      ?: null);
                $sheet->setCellValue('F' . $r, $m['orders']      ?: null);
                $sheet->getStyle('C' . $r . ':F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('C' . $r . ':F' . $r)->getNumberFormat()->setFormatCode($numFmt);

                $totReach += $m['reach'];  $totImp += $m['impressions'];
                $totClk   += $m['clicks']; $totOrd += $m['orders'];
                $si++;
                $r++;
            }

            $dataEnd = $r - 1;

            // ── Totals row ─────────────────────────────────────
            $sheet->setCellValue('B' . $r, 'TOTAL');
            $sheet->setCellValue('C' . $r, $totReach ?: null);
            $sheet->setCellValue('D' . $r, $totImp   ?: null);
            $sheet->setCellValue('E' . $r, $totClk   ?: null);
            $sheet->setCellValue('F' . $r, $totOrd   ?: null);
            $sheet->getStyle('B' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
            $sheet->getStyle('B' . $r . ':F' . $r)->getFont()->setBold(true);
            $sheet->getStyle('C' . $r . ':F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('C' . $r . ':F' . $r)->getNumberFormat()->setFormatCode($numFmt);
            $tableEndRow = $r;

            // ── Borders ────────────────────────────────────────
            $sheet->getStyle('B' . $titleRow . ':F' . $r)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // ── Chart to the right of the table ───────────────
            if ($monthCount >= 1 && $dataEnd >= $dataStart) {
                $chart = $this->buildPlatformChart(
                    $sheetName, $platName, $hdrRow, $dataStart, $dataEnd, $monthCount
                );
                // Chart spans H→R, from title row to total row
                $chart->setTopLeftPosition('H' . $titleRow);
                $chart->setBottomRightPosition('R' . $tableEndRow);
                $sheet->addChart($chart);
            }

            $r = $tableEndRow + 3; // gap between platforms
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  CHART BUILDERS
    // ─────────────────────────────────────────────────────────────

    private function buildBarChart(string $sn, string $title, array $series, array $xLabels, int $ds, int $de, int $mc): Chart
    {
        $qsn    = "'$sn'";
        $labels = [];
        $values = [];
        foreach ($series as $s) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                $qsn . '!$' . $s['col'] . '$' . ($ds - 1), null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $qsn . '!$' . $s['col'] . '$' . $ds . ':$' . $s['col'] . '$' . $de, null, $mc);
        }
        $dataSeries = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            range(0, count($series) - 1), $labels, $xLabels, $values);
        $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);
        return new Chart($title, new Title($title),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$dataSeries]), true, 0, null, null);
    }

    private function buildLineChart(string $sn, string $title, array $series, array $xLabels, int $ds, int $de, int $mc): Chart
    {
        $qsn    = "'$sn'";
        $labels = [];
        $values = [];
        foreach ($series as $s) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                $qsn . '!$' . $s['col'] . '$' . ($ds - 1), null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $qsn . '!$' . $s['col'] . '$' . $ds . ':$' . $s['col'] . '$' . $de, null, $mc);
        }
        $dataSeries = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD,
            range(0, count($series) - 1), $labels, $xLabels, $values);
        $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);
        return new Chart($title, new Title($title),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$dataSeries]), true, 0, null, null);
    }

    /**
     * Build platform bar chart: Reach + Impressions + Clicks per month
     * Table layout: B=Month, C=Reach, D=Impressions, E=Clicks, F=Orders
     */
    private function buildPlatformChart(string $sn, string $platName, int $hdrRow, int $dataStart, int $dataEnd, int $mc): Chart
    {
        $qsn     = "'$sn'";
        $xLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            $qsn . '!$B$' . $dataStart . ':$B$' . $dataEnd, null, $mc)];

        $series = [
            ['col' => 'C', 'label' => 'Reach'],
            ['col' => 'D', 'label' => 'Impressions'],
            ['col' => 'E', 'label' => 'Clicks'],
        ];
        $labels = [];
        $values = [];
        foreach ($series as $s) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                $qsn . '!$' . $s['col'] . '$' . $hdrRow, null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $qsn . '!$' . $s['col'] . '$' . $dataStart . ':$' . $s['col'] . '$' . $dataEnd, null, $mc);
        }

        $ds = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            range(0, 2), $labels, $xLabels, $values);
        $ds->setPlotDirection(DataSeries::DIRECTION_COL);

        return new Chart(
            $platName,
            new Title($platName . ' — Reach / Impressions / Clicks'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$ds]), true, 0, null, null
        );
    }
}
