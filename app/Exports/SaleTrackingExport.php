<?php

namespace App\Exports;

use App\Services\AdsPerformanceExportColumns;
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
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\AxisText;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class SaleTrackingExport
{
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

    private const MONTH_BG_COLORS = [
        'FFFFFFFF',
        'FFE0F5EB',
    ];

    private const PLAT_COLORS = [
        'FF1A73E8','FFE37400','FF34A853','FFEA4335',
        'FF9334E6','FF00897B','FFFF6D00','FF0097A7',
    ];

    private const COLUMNS = [
        'A' => ['label' => 'Sl. No',            'width' => 7],
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
        'T' => ['label' => 'ROI',               'width' => 11],
        'U' => ['label' => 'ROAS',              'width' => 10],
    ];
    private const LAST_COL = 'U';

    private AdsPerformanceExportColumns $exportColumns;

    public function __construct(
        private array $filters = [],
        private array $tables = [],
        private array $columnSelection = [],
    ) {
        $this->exportColumns = new AdsPerformanceExportColumns();
    }

    public function download(SaleTrackingService $service): StreamedResponse
    {
        Log::info('SaleTrackingExport: download() started', [
            'filters' => $this->filters,
        ]);

        try {
        $records = $service->getExportQuery($this->filters)->get();

        $platformIds = $records->pluck('sale_platform_id')->filter()->unique()->values()->toArray();
        $monthKeys   = $records->map(fn ($r) => optional($r->month)->format('Y-m'))
                               ->filter()->unique()->values()->toArray();

        $saleLookup   = $service->getSaleDataForExport($platformIds, $monthKeys);
        $returnLookup = $service->getReturnDataForExport($monthKeys);

        $prevMonthTotalRevenue = null;
        $sortedMonthKeys = collect($monthKeys)->sort()->values()->toArray();
        if (!empty($sortedMonthKeys) && !empty($platformIds)) {
            $firstMk   = $sortedMonthKeys[0];
            $prevMk    = Carbon::parse($firstMk . '-01')->subMonth()->format('Y-m');
            $prevTotal = $service->getPrevMonthRevenueForGrowth($platformIds, $prevMk);
            $prevMonthTotalRevenue = $prevTotal;
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Ad Performance');

        if ($records->isEmpty()) {
            $sheet->setCellValue('A1', 'No data found for the selected filters.');
        } else {
            $this->writeSheet($sheet, $records, $saleLookup, $returnLookup, $prevMonthTotalRevenue);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'Ad Performance Tracking - ' . now()->format('d M Y') . '.xlsx';

        Log::info('SaleTrackingExport: download() spreadsheet built successfully', [
            'filename'      => $filename,
            'record_count'  => $records->count(),
            'filters'       => $this->filters,
        ]);

        return new StreamedResponse(function () use ($spreadsheet) {
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->setIncludeCharts(true);
                $writer->save($tempFile);

                $this->postProcessChartLabels($tempFile);

                readfile($tempFile);
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);

        } catch (\Throwable $e) {
            Log::error('SaleTrackingExport: download() failed', [
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'filters' => $this->filters,
            ]);
            throw $e;
        }
    }

    private function applyHeaderRows($sheet, string $title, string $lastCol = self::LAST_COL): void
    {
        $appName = config('app.name', 'ENOX ERP');
        foreach ([$appName, $title, 'Generated: ' . now()->format('d M Y H:i')] as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:" . $lastCol . "{$row}");
        }
        $sheet->getStyle('A1:' . $lastCol . '3')->applyFromArray([
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

    private function postProcessChartLabels(string $filePath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CHECKCONS) !== true) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('#^xl/charts/chart\d+\.xml$#i', $name)) {
                continue;
            }

            $xml = $zip->getFromName($name);
            if ($xml === false) {
                continue;
            }

            $patched = $this->patchChartXml($xml);
            if ($patched !== $xml) {
                $zip->addFromString($name, $patched);
            }
        }

        $zip->close();
    }

    private function patchChartXml(string $xml): string
    {
        if (strpos($xml, '<c:barDir val="col"/>') === false) {
            return $xml;
        }

        if (strpos($xml, '<c:showVal val="1"/>') === false) {
            return $xml;
        }

        $rotVal = '-5400000';

        $xml = preg_replace_callback(
            '/<c:dLbls>(.*?)<\/c:dLbls>/s',
            function (array $m) use ($rotVal): string {
                $inner = $m[1];

                if (strpos($inner, '<c:showVal val="1"/>') === false) {
                    return $m[0];
                }

                foreach ([
                    'showLegendKey' => '0',
                    'showCatName'   => '0',
                    'showSerName'   => '0',
                ] as $flag => $defaultVal) {
                    if (strpos($inner, "<c:{$flag}") === false) {
                        $inner .= "<c:{$flag} val=\"{$defaultVal}\"/>";
                    }
                }

                if (strpos($inner, '<c:txPr>') !== false) {
                    $inner = preg_replace(
                        '/<a:bodyPr(?![^>]*\brot=)([^>]*)\/>/',
                        '<a:bodyPr rot="' . $rotVal . '"$1/>',
                        $inner
                    );
                    $inner = preg_replace(
                        '/<a:bodyPr(?![^>]*\brot=)([^>]*)>/',
                        '<a:bodyPr rot="' . $rotVal . '"$1>',
                        $inner
                    );
                } else {
                    $txPr = '<c:txPr>'
                          . '<a:bodyPr rot="' . $rotVal . '"/>'
                          . '<a:lstStyle/>'
                          . '<a:p><a:pPr><a:defRPr b="0"/></a:pPr></a:p>'
                          . '</c:txPr>';

                    $inner = str_replace('<c:showVal', $txPr . '<c:showVal', $inner);
                }

                return '<c:dLbls>' . $inner . '</c:dLbls>';
            },
            $xml
        );

        if (strpos($xml, '<c:manualLayout>') === false
            && strpos($xml, '<c:plotArea>') !== false) {

            $manualLayout = '<c:layout>'
                . '<c:manualLayout>'
                . '<c:layoutTarget val="inner"/>'
                . '<c:xMode val="factor"/>'
                . '<c:yMode val="factor"/>'
                . '<c:x val="0.08"/>'
                . '<c:y val="0.20"/>'
                . '<c:w val="0.86"/>'
                . '<c:h val="0.58"/>'
                . '</c:manualLayout>'
                . '</c:layout>';

            $xml = str_replace('<c:plotArea>', '<c:plotArea>' . $manualLayout, $xml);
        }

        return (string) $xml;
    }

    private function writeSheet($sheet, $records, array $saleLookup, array $returnLookup, ?float $prevMonthTotalRevenue = null): void
    {
        Log::info('SaleTrackingExport: writeSheet() started', [
            'record_count' => $records->count(),
        ]);

        try {
        $sheetName = 'Ad Performance';
        $moneyFmt  = '#,##0.00';
        $pctFmt    = '0.00%';
        $numFmt    = '#,##0';

        $monthGroups  = [];
        $platformData = [];

        foreach ($records as $rec) {
            $mk         = optional($rec->month)->format('Y-m') ?? 'unknown';
            $platName   = $rec->salePlatform?->name ?? '—';
            $monthLabel = optional($rec->month)->format('M Y') ?? $mk;
            $platId     = $rec->sale_platform_id;

            $netCost = (float) ($saleLookup[$platId][$mk]['net_cost']    ?? 0);
            $revenue = (float) ($saleLookup[$platId][$mk]['revenue']     ?? 0);
            $orders  = (int)   ($saleLookup[$platId][$mk]['orders']      ?? 0);
            $prods   = (int)   ($saleLookup[$platId][$mk]['quantities']  ?? 0);
            $adsTax  = (float) ($rec->ads_tax_payments ?? 0);

            if (!isset($monthGroups[$mk])) {
                $monthGroups[$mk] = ['label' => $monthLabel, 'entries' => []];
            }
            $monthGroups[$mk]['entries'][] = [
                'rec'      => $rec,
                'net_cost' => $netCost,
                'revenue'  => $revenue,
                'ads_tax'  => $adsTax,
                'orders'   => $orders,
                'products' => $prods,
            ];

            if (!isset($platformData[$platName])) {
                $platformData[$platName] = ['platform' => $rec->salePlatform, 'months' => []];
            }
            if (!isset($platformData[$platName]['months'][$mk])) {
                $platformData[$platName]['months'][$mk] = [
                    'label' => $monthLabel, 'reach' => 0, 'impressions' => 0,
                    'clicks' => 0, 'sessions' => 0, 'engaged_sessions' => 0,
                    'users' => 0, 'orders' => 0,
                ];
            }
            $platformData[$platName]['months'][$mk]['reach']            += (int) ($rec->reach ?? 0);
            $platformData[$platName]['months'][$mk]['impressions']      += (int) ($rec->impressions ?? 0);
            $platformData[$platName]['months'][$mk]['clicks']           += (int) ($rec->clicks ?? 0);
            $platformData[$platName]['months'][$mk]['sessions']         += (int) ($rec->sessions ?? 0);
            $platformData[$platName]['months'][$mk]['engaged_sessions'] += (int) ($rec->engaged_sessions ?? 0);
            $platformData[$platName]['months'][$mk]['users']            += (int) ($rec->users ?? 0);
            $platformData[$platName]['months'][$mk]['orders']           += $orders;
        }

        ksort($monthGroups);
        foreach ($platformData as &$platEntry) {
            ksort($platEntry['months']);
        }
        unset($platEntry);

        $monthTotals = [];
        foreach ($monthGroups as $mk => $group) {
            $tc   = array_sum(array_column($group['entries'], 'ads_tax'));
            $tr   = array_sum(array_column($group['entries'], 'revenue'));
            $tt   = $returnLookup[$mk] ?? 0;
            $nr   = $tr - $tt;
            $roas = $tc > 0 ? round(($tr / $tc) * 100, 4) : null;
            $roi  = $roas !== null ? (int) round($roas) : null;
            $monthTotals[$mk] = [
                'total_cost'    => $tc,
                'total_revenue' => $tr,
                'total_return'  => $tt,
                'net_revenue'   => $nr,
                'roas'          => $roas,
                'roi'           => $roi,
            ];
        }

        $monthAgg = [];
        foreach ($monthGroups as $mk => $group) {
            $mt = $monthTotals[$mk];
            $monthAgg[$mk] = [
                'label'       => $group['label'],
                'revenue'     => $mt['total_revenue'],
                'total_cost'  => $mt['total_cost'],
                'net_revenue' => $mt['net_revenue'],
                'orders'      => array_sum(array_column($group['entries'], 'orders')),
                'clicks'      => array_sum(array_map(fn ($e) => (int) ($e['rec']->clicks         ?? 0), $group['entries'])),
                'impressions' => array_sum(array_map(fn ($e) => (int) ($e['rec']->impressions    ?? 0), $group['entries'])),
                'roi_sum'     => $mt['roi']  !== null ? $mt['roi']  : 0,
                'roi_count'   => $mt['roi']  !== null ? 1 : 0,
                'roas_sum'    => $mt['roas'] !== null ? $mt['roas'] : 0,
                'roas_count'  => $mt['roas'] !== null ? 1 : 0,
            ];
        }

        $includePerformance   = $this->includesTable(AdsPerformanceExportColumns::AD_PERFORMANCE);
        $includeSummaryTable  = $this->includesTable(AdsPerformanceExportColumns::MONTHLY_SUMMARY);
        $includeOverviewCharts = $this->includesTable(AdsPerformanceExportColumns::OVERVIEW_CHARTS)
            && $this->exportColumns->hasOverviewChartSelection($this->columnSelection);
        $includePlatformTables = $this->includesTable(AdsPerformanceExportColumns::PLATFORM_ENGAGEMENT)
            && $this->exportColumns->hasPlatformEngagementSelection($this->columnSelection);
        $includePlatformCharts = $this->includesTable(AdsPerformanceExportColumns::PLATFORM_CHARTS)
            && $this->exportColumns->hasPlatformChartSelection($this->columnSelection);
        $includeSummaryData   = $includeSummaryTable || $includeOverviewCharts;
        $includePlatformData  = $includePlatformTables || $includePlatformCharts;

        $perfCols = $includePerformance
            ? $this->exportColumns->filterDefs(
                AdsPerformanceExportColumns::AD_PERFORMANCE,
                $this->exportColumns->performanceDefs(),
                $this->columnSelection,
            )
            : [];

        $useFixedSummaryLayout = $includeOverviewCharts;
        $summaryCols = [];
        if ($includeSummaryData && !empty($monthAgg)) {
            $summaryCols = ($includeSummaryTable && !$useFixedSummaryLayout)
                ? $this->exportColumns->filterDefs(
                    AdsPerformanceExportColumns::MONTHLY_SUMMARY,
                    $this->exportColumns->summaryDefs(),
                    $this->columnSelection,
                )
                : $this->exportColumns->summaryDefs();
        }

        $headerLastCol = $this->resolveHeaderLastCol(
            $includePerformance && $perfCols !== [],
            $perfCols,
            $includeSummaryData && $summaryCols !== [],
            $summaryCols,
            $useFixedSummaryLayout,
            $includePlatformData,
        );

        $this->applyHeaderRows($sheet, 'Enorsia Digital Ad Performance Tracking', $headerLastCol);

        $totalsRow    = 4;
        $freezePane   = null;
        $summaryStart = 5;

        if ($includePerformance && $perfCols !== []) {
            $lastCol    = $this->columnLetter(count($perfCols) - 1);
            $freezePane = $this->columnLetter(min(3, count($perfCols) - 1)) . '7';
            $totalsRow  = $this->writePerformanceTable(
                $sheet,
                $monthGroups,
                $monthTotals,
                $perfCols,
                $prevMonthTotalRevenue,
                $moneyFmt,
                $pctFmt,
                $numFmt,
                $lastCol,
            );
            $sheet->getStyle('A1:' . $lastCol . $totalsRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            if ($freezePane) {
                $sheet->freezePane($freezePane);
            }
            $summaryStart = $totalsRow + 3;
        }

        $summaryEnd  = $summaryStart;
        $overviewEnd = $summaryStart;

        if ($includeSummaryData && !empty($monthAgg) && $summaryCols !== []) {
            $summaryEnd = $this->writeMonthlySummary(
                $sheet,
                $monthAgg,
                $summaryStart,
                $summaryCols,
                $includeSummaryTable,
                $useFixedSummaryLayout,
            );
        }

        if ($includeOverviewCharts && $summaryEnd > $summaryStart) {
            $chartStart  = $summaryEnd + 3;
            $monthCount  = count($monthAgg);
            $overviewEnd = $chartStart;
            if ($monthCount >= 1) {
                $overviewEnd = $this->writeOverviewCharts(
                    $sheet,
                    $sheetName,
                    $summaryStart,
                    $summaryEnd,
                    $monthCount,
                    $chartStart,
                    $this->exportColumns->selectedOverviewCharts($this->columnSelection),
                );
            }
        } else {
            $overviewEnd = $summaryEnd;
        }

        if ($includePlatformData) {
            $platformStart = ($overviewEnd > 5) ? $overviewEnd + 3 : 5;
            $this->writePlatformSections(
                $sheet,
                $sheetName,
                $platformData,
                $platformStart,
                $includePlatformTables,
                $includePlatformCharts,
                $this->exportColumns->selectedPlatformChartIds($this->columnSelection),
            );
        }

        Log::info('SaleTrackingExport: writeSheet() completed successfully', [
            'record_count' => $records->count(),
        ]);

        } catch (\Throwable $e) {
            Log::error('SaleTrackingExport: writeSheet() failed', [
                'error'        => $e->getMessage(),
                'class'        => get_class($e),
                'file'         => $e->getFile(),
                'line'         => $e->getLine(),
                'trace'        => $e->getTraceAsString(),
                'record_count' => $records->count(),
            ]);
            throw $e;
        }
    }

    private const SUMMARY_FIXED_LETTERS = [
        'month'       => 'B',
        'revenue'     => 'C',
        'total_cost'  => 'D',
        'net_revenue' => 'E',
        'orders'      => 'F',
        'clicks'      => 'G',
        'impressions' => 'H',
        'avg_roi'     => 'I',
        'avg_roas'    => 'J',
    ];

    private function writeMonthlySummary($sheet, array $monthAgg, int $startRow, array $activeCols, bool $showTitle = true, bool $fixedLayout = false): int
    {
        if (empty($monthAgg) || empty($activeCols)) {
            return $startRow;
        }

        $letters = [];
        if ($fixedLayout) {
            foreach ($activeCols as $col) {
                if (isset(self::SUMMARY_FIXED_LETTERS[$col['key']])) {
                    $letters[$col['key']] = self::SUMMARY_FIXED_LETTERS[$col['key']];
                }
            }
            $usedLetters = array_values($letters);
            sort($usedLetters);
            $firstLetter = $usedLetters[0] ?? 'B';
            $lastLetter  = $usedLetters[count($usedLetters) - 1] ?? 'J';
        } else {
            foreach ($activeCols as $i => $col) {
                $letters[$col['key']] = $this->columnLetter($i);
            }
            $firstLetter = $this->columnLetter(0);
            $lastLetter  = $this->columnLetter(count($activeCols) - 1);
        }

        if ($showTitle) {
            $sheet->setCellValue($firstLetter . $startRow, 'Monthly Performance Summary');
            $sheet->mergeCells($firstLetter . $startRow . ':' . $lastLetter . $startRow);
            $sheet->getStyle($firstLetter . $startRow)->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::CLR_SEC_FG);
            $sheet->getStyle($firstLetter . $startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SEC_BG);
            $sheet->getStyle($firstLetter . $startRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($startRow)->setRowHeight(22);
            $hdrRow = $startRow + 1;
        } else {
            $hdrRow = $startRow;
        }

        foreach ($activeCols as $col) {
            if (!isset($letters[$col['key']])) {
                continue;
            }
            $letter = $letters[$col['key']];
            $sheet->setCellValue($letter . $hdrRow, $col['header']);
            $sheet->getColumnDimension($letter)->setWidth($this->summaryColumnWidth($col['key']));
        }

        $sheet->getStyle($firstLetter . $hdrRow . ':' . $lastLetter . $hdrRow)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUMHDR_BG);
        $sheet->getStyle($firstLetter . $hdrRow . ':' . $lastLetter . $hdrRow)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_SUMHDR_FG);
        $sheet->getStyle($firstLetter . $hdrRow . ':' . $lastLetter . $hdrRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($hdrRow)->setRowHeight(22);

        $r = $hdrRow + 1;
        $sl = 0;
        $sumRev = $sumCost = $sumNet = $sumOrd = $sumClk = $sumImp = 0.0;

        foreach ($monthAgg as $agg) {
            $avgRoi  = $agg['roi_count']  > 0 ? round($agg['roi_sum']  / $agg['roi_count'],  4) : null;
            $avgRoas = $agg['roas_count'] > 0 ? round($agg['roas_sum'] / $agg['roas_count'], 4) : null;

            if ($showTitle && ($sl % 2) === 1) {
                $sheet->getStyle($firstLetter . $r . ':' . $lastLetter . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_ALT);
            }

            $values = [
                'month'       => $agg['label'],
                'revenue'     => $agg['revenue'],
                'total_cost'  => $agg['total_cost'],
                'net_revenue' => $agg['net_revenue'],
                'orders'      => $agg['orders'],
                'clicks'      => $agg['clicks'],
                'impressions' => $agg['impressions'],
                'avg_roi'     => $avgRoi !== null ? $avgRoi / 100 : null,
                'avg_roas'    => $avgRoas,
            ];

            foreach ($activeCols as $col) {
                if (!isset($letters[$col['key']])) {
                    continue;
                }
                $sheet->setCellValue($letters[$col['key']] . $r, $values[$col['key']] ?? null);
            }

            $numericStart = $letters['revenue'] ?? $firstLetter;
            $sheet->getStyle($numericStart . $r . ':' . $lastLetter . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sumRev  += $agg['revenue'];
            $sumCost += $agg['total_cost'];
            $sumNet  += $agg['net_revenue'];
            $sumOrd  += $agg['orders'];
            $sumClk  += $agg['clicks'];
            $sumImp  += $agg['impressions'];
            $sl++;
            $r++;
        }

        if ($showTitle) {
            $totals = [
                'month'       => 'TOTAL',
                'revenue'     => $sumRev ?: null,
                'total_cost'  => $sumCost ?: null,
                'net_revenue' => $sumNet ?: null,
                'orders'      => $sumOrd ?: null,
                'clicks'      => $sumClk ?: null,
                'impressions' => $sumImp ?: null,
            ];
            foreach ($activeCols as $col) {
                if (!array_key_exists($col['key'], $totals) || !isset($letters[$col['key']])) {
                    continue;
                }
                $sheet->setCellValue($letters[$col['key']] . $r, $totals[$col['key']]);
            }
            $sheet->getStyle($firstLetter . $r . ':' . $lastLetter . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
            $sheet->getStyle($firstLetter . $r . ':' . $lastLetter . $r)->getFont()->setBold(true);
            $numericStart = $letters['revenue'] ?? $firstLetter;
            $sheet->getStyle($numericStart . $r . ':' . $lastLetter . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        if ($showTitle) {
            $sheet->getStyle($firstLetter . $startRow . ':' . $lastLetter . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $dataStart = $hdrRow + 1;
        $dataEnd   = $showTitle ? $r : $r - 1;
        foreach (['revenue', 'total_cost', 'net_revenue'] as $key) {
            if (isset($letters[$key])) {
                $sheet->getStyle($letters[$key] . $dataStart . ':' . $letters[$key] . $dataEnd)->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        foreach (['orders', 'clicks', 'impressions'] as $key) {
            if (isset($letters[$key])) {
                $sheet->getStyle($letters[$key] . $dataStart . ':' . $letters[$key] . $dataEnd)->getNumberFormat()->setFormatCode('#,##0');
            }
        }
        if (isset($letters['avg_roi'])) {
            $sheet->getStyle($letters['avg_roi'] . $dataStart . ':' . $letters['avg_roi'] . $dataEnd)->getNumberFormat()->setFormatCode('0.00%');
        }
        if (isset($letters['avg_roas'])) {
            $sheet->getStyle($letters['avg_roas'] . $dataStart . ':' . $letters['avg_roas'] . $dataEnd)->getNumberFormat()->setFormatCode('0.00');
        }

        return $showTitle ? $r : $dataEnd;
    }

    private function writeOverviewCharts($sheet, string $sn, int $summaryStart, int $summaryEnd, int $mc, int $chartTopRow, array $selectedCharts = []): int
    {
        $dataStart = $summaryStart + 2;
        $dataEnd   = $summaryEnd - 1;
        if ($dataEnd < $dataStart) return $chartTopRow;

        if ($selectedCharts === []) {
            $selectedCharts = array_column($this->exportColumns->overviewChartDefs(), 'key');
        }

        $chartH = max(28, $mc + 4);

        $row2Top    = $chartTopRow + $chartH + 2;
        $row2Bottom = $row2Top + $chartH;

        $xLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            "'$sn'" . '!$B$' . $dataStart . ':$B$' . $dataEnd, null, $mc)];

        $rowBottom = $chartTopRow;

        if (in_array(AdsPerformanceExportColumns::OVERVIEW_CHART_REVENUE, $selectedCharts, true)) {
            $c1 = $this->buildBarChart($sn, 'Revenue vs Total Cost vs Net Revenue',
                [['col'=>'C','label'=>'Revenue (£)'],['col'=>'D','label'=>'Total Cost (£)'],['col'=>'E','label'=>'Net Revenue (£)']],
                $xLabels, $dataStart, $dataEnd, $mc);
            $c1->setTopLeftPosition('A' . $chartTopRow);
            $c1->setBottomRightPosition('K' . ($chartTopRow + $chartH));
            $sheet->addChart($c1);
            $rowBottom = max($rowBottom, $chartTopRow + $chartH);
        }

        if (in_array(AdsPerformanceExportColumns::OVERVIEW_CHART_ORDERS, $selectedCharts, true)) {
            $c2 = $this->buildBarChart($sn, 'Orders by Month',
                [['col'=>'F','label'=>'Orders']],
                $xLabels, $dataStart, $dataEnd, $mc);
            $c2->setTopLeftPosition('L' . $chartTopRow);
            $c2->setBottomRightPosition('V' . ($chartTopRow + $chartH));
            $sheet->addChart($c2);
            $rowBottom = max($rowBottom, $chartTopRow + $chartH);
        }

        if (in_array(AdsPerformanceExportColumns::OVERVIEW_CHART_ROI, $selectedCharts, true)) {
            $c3 = $this->buildLineChart($sn, title: 'Avg ROI by Month',
                series: [['col'=>'I','label'=>'Avg ROI']],
                xLabels: $xLabels, ds: $dataStart, de: $dataEnd, mc: $mc);
            $c3->setTopLeftPosition('A' . $row2Top);
            $c3->setBottomRightPosition('K' . $row2Bottom);
            $sheet->addChart($c3);
            $rowBottom = max($rowBottom, $row2Bottom);
        }

        if (in_array(AdsPerformanceExportColumns::OVERVIEW_CHART_ROAS, $selectedCharts, true)) {
            $c4 = $this->buildLineChart($sn, 'Avg ROAS by Month',
                [['col'=>'J','label'=>'Avg ROAS']],
                $xLabels, $dataStart, $dataEnd, $mc);
            $c4->setTopLeftPosition('L' . $row2Top);
            $c4->setBottomRightPosition('V' . $row2Bottom);
            $sheet->addChart($c4);
            $rowBottom = max($rowBottom, $row2Bottom);
        }

        return $rowBottom;
    }

    private function writePlatformSections($sheet, string $sheetName, array $platformData, int $startRow, bool $includeTables = true, bool $includeCharts = true, array $selectedChartPlatformIds = []): void
    {
        if (empty($platformData)) return;
        if (!$includeTables && !$includeCharts) return;

        $numFmt = '#,##0';
        $plan   = $this->buildPlatformSectionPlan($platformData, $includeTables, $includeCharts, $selectedChartPlatformIds);

        if ($plan === []) {
            return;
        }

        $anyTable = (bool) array_filter($plan, fn (array $item) => $item['show_table']);

        $r = $startRow;
        if ($anyTable) {
            $this->writePlatformSectionTitle(
                $sheet,
                $startRow,
                'Per-Platform Engagement — Reach · Impressions · Clicks (Monthly)',
            );
            $r = $startRow + 2;
        }

        foreach ($plan as $platName => $item) {
            $platEntry = $item['entry'];
            $showTable = $item['show_table'];
            $showChart = $item['show_chart'];
            $colMap    = $item['col_map'];
            $months    = $item['months'];

            $lastDataCol = array_key_last($colMap);
            $monthCount  = count($months);

            $chartStartCol = $showTable
                ? chr(ord($lastDataCol) + 2)
                : 'B';
            $chartEndCol = chr(ord($chartStartCol) + 12);

            $minChartRows = max(25, $monthCount * 4 + 12);

            $titleRow = $r;
            if ($showTable) {
                $sheet->setCellValue('B' . $r, $platName);
                $sheet->mergeCells('B' . $r . ':' . $lastDataCol . $r);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HDR_BG);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                    ->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::CLR_HDR_FG);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($r)->setRowHeight(22);
                $r++;
            }

            $hdrRow = $r;
            $sheet->setCellValue('B' . $r, 'Month');
            foreach ($colMap as $col => $def) {
                $sheet->setCellValue($col . $r, $def['label']);
            }
            if ($showTable) {
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUMHDR_BG);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_SUMHDR_FG);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($r)->setRowHeight(20);
            }
            $r++;

            $dataStart = $r;
            $si        = 0;
            $totals    = array_fill_keys(array_keys($colMap), 0);

            foreach ($months as $m) {
                if ($showTable && ($si % 2 === 1)) {
                    $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_ALT);
                }
                $sheet->setCellValue('B' . $r, $m['label']);
                foreach ($colMap as $col => $def) {
                    $val = $m[$def['key']] ?? 0;
                    $sheet->setCellValue($col . $r, $val ?: null);
                    $totals[$col] += $val;
                }
                if ($showTable) {
                    $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getNumberFormat()->setFormatCode($numFmt);
                }
                $si++;
                $r++;
            }

            $dataEnd = $r - 1;

            if ($showTable) {
                $sheet->setCellValue('B' . $r, 'TOTAL');
                foreach ($colMap as $col => $def) {
                    $sheet->setCellValue($col . $r, $totals[$col] ?: null);
                }
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
                $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getFont()->setBold(true);
                $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getNumberFormat()->setFormatCode($numFmt);
                $sheet->getStyle('B' . $titleRow . ':' . $lastDataCol . $r)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            $tableEndRow = $r;

            $chartTopRow    = $titleRow;
            $chartBottomRow = $showTable
                ? max($r, $titleRow + $minChartRows)
                : $titleRow + $minChartRows;

            if ($showChart && $monthCount >= 1 && $dataEnd >= $dataStart) {
                $chart = $this->buildPlatformChartDynamic(
                    $sheetName, $platName, $hdrRow, $dataStart, $dataEnd, $monthCount, $colMap
                );
                $chart->setTopLeftPosition($chartStartCol . $chartTopRow);
                $chart->setBottomRightPosition($chartEndCol . $chartBottomRow);
                $sheet->addChart($chart);

                if (!$showTable) {
                    $this->hideSheetRows($sheet, $hdrRow, $tableEndRow);
                }

                $r = $chartBottomRow + 3;
            } else {
                $r = $tableEndRow + 3;
            }
        }
    }

    private function buildPlatformSectionPlan(
        array $platformData,
        bool $includeTables,
        bool $includeCharts,
        array $selectedChartPlatformIds,
    ): array {
        $plan = [];

        foreach ($platformData as $platName => $platEntry) {
            $platform = $platEntry['platform'];
            $months   = $platEntry['months'];

            if (empty($months)) {
                continue;
            }

            $fullColMap = $this->platformMetricColumnMap($platform);
            if ($fullColMap === []) {
                continue;
            }

            $platformId      = (int) ($platform?->id ?? 0);
            $selectedMetrics = ($includeTables && $platformId > 0)
                ? $this->exportColumns->selectedPlatformMetrics($this->columnSelection, $platformId)
                : [];
            $showTable       = $includeTables && $selectedMetrics !== [];
            $showChart       = $includeCharts
                && $platformId > 0
                && ($selectedChartPlatformIds === [] || in_array($platformId, $selectedChartPlatformIds, true));

            if (!$showTable && !$showChart) {
                continue;
            }

            $colMap = $fullColMap;
            if ($showTable) {
                $filtered = array_values(array_filter(
                    $fullColMap,
                    fn ($def) => in_array($def['key'], $selectedMetrics, true),
                ));
                $colMap = [];
                $nextCol = 'C';
                foreach ($filtered as $def) {
                    $colMap[$nextCol] = $def;
                    $nextCol = chr(ord($nextCol) + 1);
                }
                if ($colMap === []) {
                    continue;
                }
            }

            ksort($months);

            $plan[$platName] = [
                'entry'      => $platEntry,
                'show_table' => $showTable,
                'show_chart' => $showChart,
                'col_map'    => $colMap,
                'months'     => array_values($months),
            ];
        }

        return $plan;
    }

    private function platformMetricColumnMap($platform): array
    {
        $showReach    = $platform ? (bool) $platform->track_reach           : true;
        $showImp      = $platform ? (bool) $platform->track_impressions      : true;
        $showClicks   = $platform ? (bool) $platform->track_clicks           : true;
        $showSessions = $platform ? (bool) $platform->track_sessions         : true;
        $showEngaged  = $platform ? (bool) $platform->track_engaged_sessions : true;
        $showUsers    = $platform ? (bool) $platform->track_users            : true;

        $colMap  = [];
        $nextCol = 'C';
        foreach ([
            'reach'            => ['Reach',            $showReach],
            'impressions'      => ['Impressions',       $showImp],
            'clicks'           => ['Clicks',            $showClicks],
            'sessions'         => ['Sessions',          $showSessions],
            'engaged_sessions' => ['Engaged Sessions',  $showEngaged],
            'users'            => ['Users',             $showUsers],
        ] as $key => [$label, $show]) {
            if ($show) {
                $colMap[$nextCol] = ['label' => $label, 'key' => $key];
                $nextCol = chr(ord($nextCol) + 1);
            }
        }

        return $colMap;
    }

    private function writePlatformSectionTitle($sheet, int $row, string $title): void
    {
        $sheet->setCellValue('B' . $row, $title);
        $sheet->mergeCells('B' . $row . ':W' . $row);
        $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::CLR_TITLE_FG);
        $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $sheet->getStyle('B' . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(26);
    }

    private function hideSheetRows($sheet, int $fromRow, int $toRow): void
    {
        for ($row = $fromRow; $row <= $toRow; $row++) {
            $sheet->getRowDimension($row)->setVisible(false);
        }
    }

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

        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setShowLegendKey(false);
        $layout->setShowCatName(false);
        $layout->setShowSerName(false);

        $xAxis = new Axis();
        if ($mc > 5) {
            $xAxis->setAxisOption('textRotation', '-45');
        }

        return new Chart($title, new Title($title),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea($layout, [$dataSeries]), true, 0, null, null, $xAxis);
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

        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setShowLegendKey(false);
        $layout->setShowCatName(false);
        $layout->setShowSerName(false);

        $xAxis = new Axis();
        if ($mc > 5) {
            $xAxis->setAxisOption('textRotation', '-45');
        }

        return new Chart($title, new Title($title),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea($layout, [$dataSeries]), true, 0, null, null, $xAxis);
    }

    private function buildPlatformChartDynamic(string $sn, string $platName, int $hdrRow, int $dataStart, int $dataEnd, int $mc, array $colMap): Chart
    {
        $qsn     = "'$sn'";
        $xLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            $qsn . '!$B$' . $dataStart . ':$B$' . $dataEnd, null, $mc)];

        $labels = [];
        $values = [];
        $idx    = 0;
        foreach ($colMap as $col => $def) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
                $qsn . '!$' . $col . '$' . $hdrRow, null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $qsn . '!$' . $col . '$' . $dataStart . ':$' . $col . '$' . $dataEnd, null, $mc);
            $idx++;
        }

        $ds = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, $idx - 1),
            $labels,
            $xLabels,
            $values
        );
        $ds->setPlotDirection(DataSeries::DIRECTION_BAR);

        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setShowCatName(false);
        $layout->setShowSerName(false);
        $layout->setShowLegendKey(false);

        return new Chart(
            $platName,
            new Title($platName . ' — Engagement Metrics'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea($layout, [$ds]), true, 0, null, null
        );
    }

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

        $layout = new Layout();
        $layout->setShowVal(true);

        return new Chart(
            $platName,
            new Title($platName . ' — Reach / Impressions / Clicks'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea($layout, [$ds]), true, 0, null, null
        );
    }

    private function includesTable(string $key): bool
    {
        return in_array($key, $this->tables, true);
    }

    private function columnIndex(string $letter): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letter)) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index - 1;
    }

    private function maxColumnLetter(array $letters): string
    {
        $maxLetter = 'A';
        $maxIndex  = 0;

        foreach ($letters as $letter) {
            $index = $this->columnIndex($letter);
            if ($index > $maxIndex) {
                $maxIndex  = $index;
                $maxLetter = strtoupper($letter);
            }
        }

        return $maxLetter;
    }

    private function resolveHeaderLastCol(
        bool $includePerformance,
        array $perfCols,
        bool $includeSummary,
        array $summaryCols,
        bool $fixedSummaryLayout,
        bool $includePlatformData,
    ): string {
        $letters = ['J'];

        if ($includePerformance && $perfCols !== []) {
            $letters[] = $this->columnLetter(count($perfCols) - 1);
        }

        if ($includeSummary && $summaryCols !== []) {
            if ($fixedSummaryLayout) {
                $letters[] = 'J';
            } else {
                $letters[] = $this->columnLetter(max(0, count($summaryCols) - 1));
            }
        }

        if ($includePlatformData) {
            $letters[] = 'W';
        }

        return $this->maxColumnLetter($letters);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n      = $index + 1;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n      = intdiv($n, 26);
        }

        return $letter;
    }

    private function performanceColumnWidth(string $key): int
    {
        return match ($key) {
            'sl'               => 7,
            'month_label'      => 13,
            'platform'         => 28,
            'engaged_sessions' => 16,
            'sales_growth'     => 14,
            'total_revenue'    => 16,
            'total_return'     => 15,
            'net_revenue'      => 15,
            default            => in_array($key, ['net_cost', 'ads_tax', 'total_cost', 'revenue'], true) ? 14 : 11,
        };
    }

    private function summaryColumnWidth(string $key): int
    {
        return match ($key) {
            'month' => 13,
            'impressions' => 14,
            default => 12,
        };
    }

    private function writePerformanceTable(
        $sheet,
        array $monthGroups,
        array $monthTotals,
        array $activeCols,
        ?float $prevMonthTotalRevenue,
        string $moneyFmt,
        string $pctFmt,
        string $numFmt,
        string $lastCol,
    ): int {
        $letters = [];
        foreach ($activeCols as $i => $col) {
            $letters[$col['key']] = $this->columnLetter($i);
        }

        $hdrRow = 6;
        foreach ($activeCols as $i => $col) {
            $letter = $this->columnLetter($i);
            $sheet->setCellValue($letter . $hdrRow, $col['header']);
            $sheet->getColumnDimension($letter)->setWidth($this->performanceColumnWidth($col['key']));
        }

        $hdrRange = 'A' . $hdrRow . ':' . $lastCol . $hdrRow;
        $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HDR_BG);
        $sheet->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_HDR_FG);
        $sheet->getStyle($hdrRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension($hdrRow)->setRowHeight(32);

        $r                = 7;
        $sl               = 1;
        $monthIndex       = 0;
        $prevMonthRevenue = $prevMonthTotalRevenue;
        $grandTotalReturn = 0.0;
        $mergeKeys        = ['month_label', 'total_cost', 'sales_growth', 'total_revenue', 'total_return', 'net_revenue', 'roi', 'roas'];
        $moneyKeys        = ['net_cost', 'ads_tax', 'total_cost', 'revenue', 'total_revenue', 'total_return', 'net_revenue'];
        $numKeys          = ['reach', 'impressions', 'clicks', 'sessions', 'engaged_sessions', 'users', 'orders', 'products'];
        $firstNumeric     = $letters['reach'] ?? $letters['net_cost'] ?? $letters['revenue'] ?? $lastCol;

        foreach ($monthGroups as $mk => $group) {
            $monthStartRow = $r;
            $monthLabel    = $group['label'];
            $monthBg       = self::MONTH_BG_COLORS[$monthIndex % count(self::MONTH_BG_COLORS)];
            $mt            = $monthTotals[$mk];

            foreach ($group['entries'] as $entry) {
                $rec = $entry['rec'];
                $rowValues = [
                    'sl'               => $sl++,
                    'month_label'      => $monthLabel,
                    'platform'         => $rec->salePlatform?->name ?? '—',
                    'reach'            => $rec->reach,
                    'impressions'      => $rec->impressions,
                    'clicks'           => $rec->clicks,
                    'sessions'         => $rec->sessions,
                    'engaged_sessions' => $rec->engaged_sessions,
                    'users'            => $rec->users,
                    'net_cost'         => $entry['net_cost'] ?: null,
                    'ads_tax'          => $entry['ads_tax'] ?: null,
                    'orders'           => $entry['orders'] ?: null,
                    'products'         => $entry['products'] ?: null,
                    'revenue'          => $entry['revenue'] ?: null,
                ];

                foreach ($activeCols as $col) {
                    $key = $col['key'];
                    if (!isset($letters[$key]) || in_array($key, $mergeKeys, true)) {
                        continue;
                    }
                    $sheet->setCellValue($letters[$key] . $r, $rowValues[$key] ?? null);
                }

                $sheet->getStyle('A' . $r . ':' . $lastCol . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($monthBg);
                $sheet->getStyle($firstNumeric . $r . ':' . $lastCol . $r)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $r++;
            }

            $monthEndRow = $r - 1;
            $grandTotalReturn += $mt['total_return'];
            $ms = $monthStartRow;
            $me = $monthEndRow;

            $mergeValues = $this->performanceMonthMergeValues($mt, $prevMonthRevenue);
            foreach (['total_cost', 'total_revenue', 'total_return', 'net_revenue', 'roas', 'roi', 'sales_growth'] as $key) {
                if (isset($letters[$key])) {
                    $sheet->setCellValue($letters[$key] . $ms, $mergeValues[$key]);
                }
            }

            if (isset($letters['month_label'])) {
                $sheet->setCellValue($letters['month_label'] . $ms, $monthLabel);
                if ($monthEndRow > $monthStartRow) {
                    $sheet->mergeCells($letters['month_label'] . $ms . ':' . $letters['month_label'] . $me);
                }
                $sheet->getStyle($letters['month_label'] . $ms)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            foreach ($mergeKeys as $key) {
                if (!isset($letters[$key]) || $key === 'month_label') {
                    continue;
                }
                if ($monthEndRow > $monthStartRow) {
                    $sheet->mergeCells($letters[$key] . $ms . ':' . $letters[$key] . $me);
                }
                $sheet->getStyle($letters[$key] . $ms)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $prevMonthRevenue = (float) ($mt['total_revenue'] ?? 0);
            $monthIndex++;
        }

        $dataEndRow = $r - 1;
        $totalsRow  = $r;
        $grandMerge = $this->performanceGrandMergeValues($monthTotals, $grandTotalReturn);

        if (isset($letters['platform'])) {
            $sheet->setCellValue($letters['platform'] . $r, 'TOTAL');
        }

        foreach ($activeCols as $col) {
            $key = $col['key'];
            if (!isset($letters[$key]) || in_array($key, ['sl', 'month_label', 'platform', 'sales_growth', 'roi'], true)) {
                continue;
            }
            if ($key === 'total_cost') {
                $sheet->setCellValue($letters[$key] . $r, $grandMerge['total_cost']);
                continue;
            }
            if ($key === 'total_revenue') {
                $sheet->setCellValue($letters[$key] . $r, $grandMerge['total_revenue']);
                continue;
            }
            if ($key === 'total_return') {
                $sheet->setCellValue($letters[$key] . $r, $grandMerge['total_return']);
                continue;
            }
            if ($key === 'net_revenue') {
                $sheet->setCellValue($letters[$key] . $r, $grandMerge['net_revenue']);
                continue;
            }
            if ($key === 'roas') {
                $sheet->setCellValue($letters[$key] . $r, $grandMerge['roas']);
                continue;
            }
            $sheet->setCellValue($letters[$key] . $r, "=SUM({$letters[$key]}7:{$letters[$key]}{$dataEndRow})");
        }

        if (isset($letters['roi'])) {
            $sheet->setCellValue($letters['roi'] . $r, $grandMerge['roi']);
        }

        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TOTAL);
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_TOTAL_FG);
        $sheet->getStyle($firstNumeric . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($dataEndRow >= 7) {
            foreach ($moneyKeys as $key) {
                if (isset($letters[$key])) {
                    $sheet->getStyle($letters[$key] . '7:' . $letters[$key] . $totalsRow)->getNumberFormat()->setFormatCode($moneyFmt);
                }
            }
            foreach ($numKeys as $key) {
                if (isset($letters[$key])) {
                    $sheet->getStyle($letters[$key] . '7:' . $letters[$key] . $totalsRow)->getNumberFormat()->setFormatCode($numFmt);
                }
            }
            if (isset($letters['sales_growth'])) {
                $sheet->getStyle($letters['sales_growth'] . '7:' . $letters['sales_growth'] . $totalsRow)->getNumberFormat()->setFormatCode($pctFmt);
            }
            if (isset($letters['roi'])) {
                $sheet->getStyle($letters['roi'] . '7:' . $letters['roi'] . $totalsRow)->getNumberFormat()->setFormatCode('0"%"');
            }
            if (isset($letters['roas'])) {
                $sheet->getStyle($letters['roas'] . '7:' . $letters['roas'] . $totalsRow)->getNumberFormat()->setFormatCode('0.00"%"');
            }
        }

        return $totalsRow;
    }

    private function performanceMonthMergeValues(array $mt, ?float $prevRevenue): array
    {
        $currentRevenue = (float) ($mt['total_revenue'] ?? 0);
        $totalCost      = (float) ($mt['total_cost'] ?? 0);
        $totalReturn    = (float) ($mt['total_return'] ?? 0);

        if ($prevRevenue !== null && $prevRevenue > 0) {
            $salesGrowth = ($currentRevenue - $prevRevenue) / $prevRevenue;
        } else {
            $salesGrowth = 0.0;
        }

        return [
            'total_cost'    => $totalCost ?: null,
            'total_revenue' => $currentRevenue ?: null,
            'total_return'  => $totalReturn ?: null,
            'net_revenue'   => ($mt['net_revenue'] ?? ($currentRevenue - $totalReturn)) ?: null,
            'roas'          => $mt['roas'] ?? ($totalCost > 0 ? round(($currentRevenue / $totalCost) * 100, 4) : null),
            'roi'           => $mt['roi'] ?? (isset($mt['roas']) && $mt['roas'] !== null ? (int) round($mt['roas']) : null),
            'sales_growth'  => $salesGrowth,
        ];
    }

    private function performanceGrandMergeValues(array $monthTotals, float $grandTotalReturn): array
    {
        $grandTotalCost    = array_sum(array_map(fn (array $mt) => (float) ($mt['total_cost'] ?? 0), $monthTotals));
        $grandTotalRevenue = array_sum(array_map(fn (array $mt) => (float) ($mt['total_revenue'] ?? 0), $monthTotals));
        $grandNetRevenue   = $grandTotalRevenue - $grandTotalReturn;
        $grandRoas         = $grandTotalCost > 0 ? round(($grandTotalRevenue / $grandTotalCost) * 100, 4) : null;

        return [
            'total_cost'    => $grandTotalCost ?: null,
            'total_revenue' => $grandTotalRevenue ?: null,
            'total_return'  => $grandTotalReturn ?: null,
            'net_revenue'   => $grandNetRevenue ?: null,
            'roas'          => $grandRoas,
            'roi'           => $grandRoas !== null ? (int) round($grandRoas) : null,
        ];
    }
}
