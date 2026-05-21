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
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\AxisText;
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

    // Cycling background colours for main-table month groups — two-colour even/odd
    private const MONTH_BG_COLORS = [
        'FFFFFFFF',   // white  (even months)
        'FFE0F5EB',   // light green (odd months)
    ];

    // Per-platform header colour cycle
    private const PLAT_COLORS = [
        'FF1A73E8','FFE37400','FF34A853','FFEA4335',
        'FF9334E6','FF00897B','FFFF6D00','FF0097A7',
    ];

    // ── Main data column definitions ────────────────────────────
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

    public function __construct(private array $filters = []) {}

    // ─────────────────────────────────────────────────────────────
    //  DOWNLOAD
    // ─────────────────────────────────────────────────────────────

    public function download(SaleTrackingService $service): StreamedResponse
    {
        $records = $service->getExportQuery($this->filters)->get();

        // ── Pre-fetch DailySale / DailyReturn aggregates via service ──
        $platformIds = $records->pluck('sale_platform_id')->filter()->unique()->values()->toArray();
        $monthKeys   = $records->map(fn ($r) => optional($r->month)->format('Y-m'))
                               ->filter()->unique()->values()->toArray();

        $saleLookup   = $service->getSaleDataForExport($platformIds, $monthKeys);
        $returnLookup = $service->getReturnDataForExport($monthKeys);

        // ── Compute previous-month revenue for Sales Growth % of the FIRST exported month ──
        // When a filter window is active, the first exported month has no $prevQRow, but we can
        // compare it against the month immediately before the window (even if that month is not
        // shown in the export).  We try DailySale first, then fall back to DailyAdPerformance.revenue
        // via the service so we always have a baseline even when DailySale records are absent.
        $prevMonthTotalRevenue = null;
        $sortedMonthKeys = collect($monthKeys)->sort()->values()->toArray();
        if (!empty($sortedMonthKeys) && !empty($platformIds)) {
            $firstMk   = $sortedMonthKeys[0];
            $prevMk    = Carbon::parse($firstMk . '-01')->subMonth()->format('Y-m');
            $prevTotal = $service->getPrevMonthRevenueForGrowth($platformIds, $prevMk);
            // Store regardless of whether it is 0 — writeSheet will write 0% when it's 0
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
        $filename = 'ad-tracking-' . now()->format('Y-m-d') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            // Save to a temp file so we can post-process the chart XML
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->setIncludeCharts(true);
                $writer->save($tempFile);

                // Inject data-label rotation into vertical-bar charts to prevent overlapping text
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
    }

    // ─────────────────────────────────────────────────────────────
    //  POST-PROCESS: inject data-label text rotation into chart XML
    //  XLSX is a ZIP archive; we open it, patch every chart*.xml
    //  that is a vertical clustered bar chart (barDir val="col")
    //  so that the value labels are rotated -45° and never overlap.
    // ─────────────────────────────────────────────────────────────

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

    /**
     * For every vertical clustered bar chart (<c:barDir val="col"/>)
     * that already has data-labels shown (<c:showVal val="1"/>):
     *
     *  1. Injects a <c:txPr> block with a rotated <a:bodyPr> so bar-top
     *     numbers are angled and don't crowd each other horizontally.
     *
     *  2. Adds the required showLegendKey/showCatName/showSerName flags.
     *
     *  3. Injects a <c:manualLayout> inside <c:plotArea> that pushes the
     *     plot area's top edge down by ~20% of the chart height, creating
     *     a guaranteed gap between the chart title and the tallest bar so
     *     rotated value labels can never overlap the title text.
     */
    private function patchChartXml(string $xml): string
    {
        // Only touch vertical bar charts
        if (strpos($xml, '<c:barDir val="col"/>') === false) {
            return $xml;
        }

        // Only process charts that currently show values
        if (strpos($xml, '<c:showVal val="1"/>') === false) {
            return $xml;
        }

        // -45 degrees expressed in OOXML units (degrees × 60 000)
        $rotVal = '-5400000';

        // ── 1 & 2: data-label rotation + required flags ───────────────
        $xml = preg_replace_callback(
            '/<c:dLbls>(.*?)<\/c:dLbls>/s',
            function (array $m) use ($rotVal): string {
                $inner = $m[1];

                // Only rotate labels in blocks that actually show values
                if (strpos($inner, '<c:showVal val="1"/>') === false) {
                    return $m[0];
                }

                // ── 1. Ensure all required boolean flags are present ──────
                foreach ([
                    'showLegendKey' => '0',
                    'showCatName'   => '0',
                    'showSerName'   => '0',
                ] as $flag => $defaultVal) {
                    if (strpos($inner, "<c:{$flag}") === false) {
                        $inner .= "<c:{$flag} val=\"{$defaultVal}\"/>";
                    }
                }

                // ── 2. Inject / update txPr with body rotation ────────────
                if (strpos($inner, '<c:txPr>') !== false) {
                    // txPr already exists — add rot to a:bodyPr if missing
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
                    // No txPr at all — insert one right before <c:showVal
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

        // ── 3. Inject plot-area manual layout ────────────────────────────
        // Push the inner plot area down 20 % from the chart top.
        // This reserves space for the chart title AND gives the rotated
        // value labels above each bar room to breathe without touching
        // the title text — regardless of how few or how many months are shown.
        //
        //  y = 0.20  →  top of bars starts 20 % from the chart top edge
        //  h = 0.58  →  plot area height; leaves 22 % for x-axis + legend
        //  x = 0.08  →  left offset for y-axis labels
        //  w = 0.86  →  plot area width
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

    // ─────────────────────────────────────────────────────────────
    //  SHEET WRITER — main entry point
    // ─────────────────────────────────────────────────────────────

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param \Illuminate\Support\Collection                $records      DailyAdPerformance records
     * @param array                                         $saleLookup   [platform_id][Y-m] => ['net_cost','revenue']
     * @param array                                         $returnLookup [Y-m] => total_return
     *
     * Business rules (all equations also embedded as Excel formulas):
     *  – Net Cost  (J)          = DailySale.spent SUM per platform per month            (PHP value)
     *  – Ads Tax   (K)          = DailyAdPerformance.ads_tax_payments                   (PHP value)
     *  – Total Cost     (L) ← MERGED per month  = =SUM(K_start:K_end)                  (Excel formula)
     *  – Revenue   (P)          = DailySale.sales SUM per platform per month            (PHP value)
     *  – Total Revenue  (Q) ← MERGED per month  = =SUM(P_start:P_end)                  (Excel formula)
     *  – Total Return   (R) ← MERGED per month  = DailyReturn.return_amount SUM        (PHP value)
     *  – Sales Growth % (O) ← MERGED per month  = =(Q_curr−Q_prev)/Q_prev              (Excel formula)
     *  – Net Revenue    (S) ← MERGED per month  = =IFERROR(Q−R,"")                     (Excel formula)
     *  – ROAS           (U) ← MERGED per month  = =IFERROR((Q/L)*100,"")               (Excel formula)
     *  – ROI            (T) ← MERGED per month  = =IFERROR(ROUND(U,0),"")              (Excel formula)
     */
    private function writeSheet($sheet, $records, array $saleLookup, array $returnLookup, ?float $prevMonthTotalRevenue = null): void
    {
        $sheetName = 'Ad Performance';
        $moneyFmt  = '#,##0.00';
        $pctFmt    = '0.00%';
        $numFmt    = '#,##0';

        // ── Phase 1: Group records; augment with DailySale-derived values ──────
        $monthGroups  = [];   // [Y-m => ['label'=>string, 'entries'=>[...]]]
        $platformData = [];   // for per-platform engagement sections (charts)

        foreach ($records as $rec) {
            $mk         = optional($rec->month)->format('Y-m') ?? 'unknown';
            $platName   = $rec->salePlatform?->name ?? '—';
            $monthLabel = optional($rec->month)->format('M Y') ?? $mk;
            $platId     = $rec->sale_platform_id;

            // Net Cost, Revenue, Orders and Products come from DailySale
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

            // Per-platform engagement data (engagement metrics only — no financial changes)
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

        // ── Phase 2: Compute month-level financials (used for summary table + return lookup) ──
        // Note: Total Cost = SUM(Ads Tax) only per month (not net_cost + ads_tax)
        // Note: ROAS = (Total Revenue / Total Cost) × 100 ; ROI = ROUND(ROAS)
        $monthTotals = [];
        foreach ($monthGroups as $mk => $group) {
            $tc   = array_sum(array_column($group['entries'], 'ads_tax'));  // Only Ads Tax
            $tr   = array_sum(array_column($group['entries'], 'revenue'));
            $tt   = $returnLookup[$mk] ?? 0;
            $nr   = $tr - $tt;
            $roas = $tc > 0 ? round(($tr / $tc) * 100, 4) : null;         // ROAS = (Rev/Cost)×100
            $roi  = $roas !== null ? (int) round($roas) : null;            // ROI  = ROUND(ROAS)
            $monthTotals[$mk] = [
                'total_cost'    => $tc,
                'total_revenue' => $tr,
                'total_return'  => $tt,
                'net_revenue'   => $nr,
                'roas'          => $roas,
                'roi'           => $roi,
            ];
        }

        // ── Phase 3: Build monthAgg for the Monthly Summary table ─────────────
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

        // ── Row 1: Title ──────────────────────────────────────
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

        // ── Phase 4: Data rows ────────────────────────────────
        $r          = 3;
        $sl         = 1;
        $monthIndex = 0;
        $prevQRow   = null;   // Row number of previous month's merged Q cell (for Sales Growth formula)
        $grandTotalReturn = 0.0;

        // Columns merged per-month: value/formula in first row, blank rows merged into it
        // O (Sales Growth%), L (Total Cost), Q (Total Revenue), R (Total Return),
        // S (Net Revenue), T (ROI), U (ROAS)
        $mergedCols = ['O', 'L', 'Q', 'R', 'S', 'T', 'U'];

        foreach ($monthGroups as $mk => $group) {
            $monthStartRow = $r;
            $monthLabel    = $group['label'];
            $monthBg       = self::MONTH_BG_COLORS[$monthIndex % count(self::MONTH_BG_COLORS)];
            $mt            = $monthTotals[$mk];

            // ── Write per-platform rows (J, K, M, N, P are per-row; merged cols written after) ──
            foreach ($group['entries'] as $entry) {
                $rec = $entry['rec'];

                $sheet->setCellValue('A' . $r, $sl++);
                $sheet->setCellValue('B' . $r, $monthLabel);
                $sheet->setCellValue('C' . $r, $rec->salePlatform?->name ?? '—');
                $sheet->setCellValue('D' . $r, $rec->reach);
                $sheet->setCellValue('E' . $r, $rec->impressions);
                $sheet->setCellValue('F' . $r, $rec->clicks);
                $sheet->setCellValue('G' . $r, $rec->sessions);
                $sheet->setCellValue('H' . $r, $rec->engaged_sessions);
                $sheet->setCellValue('I' . $r, $rec->users);
                // J: Net Cost — from DailySale.spent (PHP value)
                $sheet->setCellValue('J' . $r, $entry['net_cost'] ?: null);
                // K: Ads Tax — from DailyAdPerformance.ads_tax_payments (PHP value)
                $sheet->setCellValue('K' . $r, $entry['ads_tax']  ?: null);
                // L: Total Cost — Excel formula written after inner loop (merged cell)
                $sheet->setCellValue('M' . $r, $entry['orders']   ?: null);
                $sheet->setCellValue('N' . $r, $entry['products'] ?: null);
                // O: Sales Growth % — Excel formula written after inner loop (merged cell)
                // P: Revenue — from DailySale.sales (PHP value)
                $sheet->setCellValue('P' . $r, $entry['revenue']  ?: null);
                // Q, R, S, T, U: Excel formulas / PHP value written after inner loop (merged cells)

                $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($monthBg);
                $sheet->getStyle('D' . $r . ':' . self::LAST_COL . $r)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $r++;
            }

            $monthEndRow = $r - 1;

            // Accumulate grand total_return (once per month — not per platform row)
            $grandTotalReturn += $mt['total_return'];

            // ── Write merged-column values / Excel formulas into the first row of the month ──
            $ms = $monthStartRow;  // shorthand
            $me = $monthEndRow;

            // L: Total Cost = SUM of Ads Tax for all platforms in this month
            $sheet->setCellValue('L' . $ms, "=SUM(K{$ms}:K{$me})");

            // Q: Total Revenue = SUM of Revenue for all platforms in this month
            $sheet->setCellValue('Q' . $ms, "=SUM(P{$ms}:P{$me})");

            // R: Total Return — PHP value (DailyReturn, no sheet reference possible)
            $sheet->setCellValue('R' . $ms, $mt['total_return'] ?: null);

            // O: Sales Growth %
            if ($prevQRow !== null) {
                // Month 2+ : Excel formula comparing against previous month's Q cell in the sheet
                $sheet->setCellValue('O' . $ms, "=IFERROR((Q{$ms}-Q{$prevQRow})/Q{$prevQRow},\"\")");
            } else {
                // First month in the export — compare against the month BEFORE the filter window.
                // $prevMonthTotalRevenue is 0.0 when no prior data exists at all.
                $firstMonthRevenue = (float) array_sum(array_column($group['entries'], 'revenue'));
                if ($prevMonthTotalRevenue !== null && $prevMonthTotalRevenue > 0) {
                    // Prior-month baseline found → compute real growth %
                    $sheet->setCellValue('O' . $ms, ($firstMonthRevenue - $prevMonthTotalRevenue) / $prevMonthTotalRevenue);
                } else {
                    // No prior data (truly the first tracked month) → show 0 %
                    $sheet->setCellValue('O' . $ms, 0.0);
                }
            }

            // S: Net Revenue = Total Revenue − Total Return
            $sheet->setCellValue('S' . $ms, "=IFERROR(Q{$ms}-R{$ms},\"\")");

            // U: ROAS = (Total Revenue / Total Cost) × 100
            $sheet->setCellValue('U' . $ms, "=IFERROR((Q{$ms}/L{$ms})*100,\"\")");

            // T: ROI = ROUND(ROAS, 0)
            $sheet->setCellValue('T' . $ms, "=IFERROR(ROUND(U{$ms},0),\"\")");

            // Track this month's Q row for next month's Sales Growth formula
            $prevQRow = $ms;

            // ── Merge column B (month label) ──────────────────────
            if ($monthEndRow > $monthStartRow) {
                $sheet->mergeCells('B' . $ms . ':B' . $me);
            }
            $sheet->getStyle('B' . $ms)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            // ── Merge month-level columns (O, L, Q, R, S, T, U) ──────────────
            foreach ($mergedCols as $col) {
                if ($monthEndRow > $monthStartRow) {
                    $sheet->mergeCells($col . $ms . ':' . $col . $me);
                }
                $sheet->getStyle($col . $ms)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $monthIndex++;
        }

        $dataEndRow = $r - 1;

        // ── Grand Total row (all numeric columns use Excel formulas) ──────────
        $totalsRow = $r;

        $sheet->setCellValue('C' . $r, 'TOTAL');

        // D–I: engagement SUM formulas
        foreach (['D','E','F','G','H','I'] as $col) {
            $sheet->setCellValue($col . $r, "=SUM({$col}3:{$col}{$dataEndRow})");
        }
        // J: Net Cost total
        $sheet->setCellValue('J' . $r, "=SUM(J3:J{$dataEndRow})");
        // K: Ads Tax total
        $sheet->setCellValue('K' . $r, "=SUM(K3:K{$dataEndRow})");
        // L: Total Cost total = SUM of all Ads Tax = SUM(K)
        $sheet->setCellValue('L' . $r, "=SUM(K3:K{$dataEndRow})");
        // M, N: order / product totals
        $sheet->setCellValue('M' . $r, "=SUM(M3:M{$dataEndRow})");
        $sheet->setCellValue('N' . $r, "=SUM(N3:N{$dataEndRow})");
        // O: no meaningful total for Sales Growth %
        // P: Revenue total
        $sheet->setCellValue('P' . $r, "=SUM(P3:P{$dataEndRow})");
        // Q: Total Revenue total = SUM of all platform revenues
        $sheet->setCellValue('Q' . $r, "=SUM(P3:P{$dataEndRow})");
        // R: Total Return total — PHP value (grand sum of monthly returns)
        $sheet->setCellValue('R' . $r, $grandTotalReturn ?: null);
        // S: Net Revenue total
        $sheet->setCellValue('S' . $r, "=IFERROR(Q{$r}-R{$r},\"\")");
        // U: ROAS total = (Total Revenue / Total Cost) × 100
        $sheet->setCellValue('U' . $r, "=IFERROR((Q{$r}/L{$r})*100,\"\")");
        // T: ROI total = ROUND(ROAS)
        $sheet->setCellValue('T' . $r, "=IFERROR(ROUND(U{$r},0),\"\")");

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
            // O: Sales Growth %  → percentage format
            $sheet->getStyle('O3:O' . $totalsRow)->getNumberFormat()->setFormatCode($pctFmt);
            // T: ROI  → integer with literal % suffix (e.g. 50%) — "%" in quotes avoids ×100
            $sheet->getStyle('T3:T' . $totalsRow)->getNumberFormat()->setFormatCode('0"%"');
            // U: ROAS → 2-decimal with literal % suffix (e.g. 49.65%) — no ×100 multiplication
            $sheet->getStyle('U3:U' . $totalsRow)->getNumberFormat()->setFormatCode('0.00"%"');
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
        $overviewEnd  = $chartStart; // fallback when no charts
        if ($monthCount >= 1) {
            $overviewEnd = $this->writeOverviewCharts($sheet, $sheetName, $summaryStart, $summaryEnd, $monthCount, $chartStart);
        }

        // ── Per-Platform Reach/Impressions/Clicks Tables + Charts ──
        $platformStart = $overviewEnd + 3;
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
            'H' => 'Impressions', 'I' => 'Avg ROI', 'J' => 'Avg ROAS',
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
    //  Returns the spreadsheet row number directly below the last chart,
    //  so the caller can position subsequent content correctly.
    // ─────────────────────────────────────────────────────────────

    private function writeOverviewCharts($sheet, string $sn, int $summaryStart, int $summaryEnd, int $mc, int $chartTopRow): int
    {
        $dataStart = $summaryStart + 2;
        $dataEnd   = $summaryEnd - 1;
        if ($dataEnd < $dataStart) return $chartTopRow;

        // ── Chart height ────────────────────────────────────────────
        // When there are only a few months the bars are wide and tall relative to the
        // plot area, so the rotated value labels protrude well above the bar tops.
        // We need enough vertical room so those labels never overlap the chart title.
        //
        //  • Minimum of 38 rows (≈ 570 px at 15 px/row) ensures adequate headroom
        //    for 1–9 months where the floor otherwise kicks in.
        //  • Beyond ~10 months the per-month term takes over and scales naturally.
        $chartH = max(38, $mc * 4 + 8);

        // Row 1 of charts: top = $chartTopRow,  bottom = $chartTopRow + $chartH
        // Gap between rows: 2 rows
        // Row 2 of charts: top = $chartTopRow + $chartH + 2
        $row2Top    = $chartTopRow + $chartH + 2;
        $row2Bottom = $row2Top + $chartH;

        $xLabels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            "'$sn'" . '!$B$' . $dataStart . ':$B$' . $dataEnd, null, $mc)];

        $c1 = $this->buildBarChart($sn, 'Revenue vs Total Cost vs Net Revenue',
            [['col'=>'C','label'=>'Revenue (£)'],['col'=>'D','label'=>'Total Cost (£)'],['col'=>'E','label'=>'Net Revenue (£)']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c1->setTopLeftPosition('A' . $chartTopRow);
        $c1->setBottomRightPosition('K' . ($chartTopRow + $chartH));
        $sheet->addChart($c1);

        $c2 = $this->buildBarChart($sn, 'Orders by Month',
            [['col'=>'F','label'=>'Orders']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c2->setTopLeftPosition('L' . $chartTopRow);
        $c2->setBottomRightPosition('V' . ($chartTopRow + $chartH));
        $sheet->addChart($c2);

        $c3 = $this->buildLineChart($sn, title: 'Avg ROI by Month',
            series: [['col'=>'I','label'=>'Avg ROI']],
            xLabels: $xLabels, ds: $dataStart, de: $dataEnd, mc: $mc);
        $c3->setTopLeftPosition('A' . $row2Top);
        $c3->setBottomRightPosition('K' . $row2Bottom);
        $sheet->addChart($c3);

        $c4 = $this->buildLineChart($sn, 'Avg ROAS by Month',
            [['col'=>'J','label'=>'Avg ROAS']],
            $xLabels, $dataStart, $dataEnd, $mc);
        $c4->setTopLeftPosition('L' . $row2Top);
        $c4->setBottomRightPosition('V' . $row2Bottom);
        $sheet->addChart($c4);

        // Return the row just below the last chart row
        return $row2Bottom;
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

        // Section banner — starts at col B, wide enough to cover any chart area
        $sheet->setCellValue('B' . $startRow, 'Per-Platform Engagement — Reach · Impressions · Clicks (Monthly)');
        $sheet->mergeCells('B' . $startRow . ':W' . $startRow);
        $sheet->getStyle('B' . $startRow)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::CLR_TITLE_FG);
        $sheet->getStyle('B' . $startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TITLE_BG);
        $sheet->getStyle('B' . $startRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $r = $startRow + 2;

        foreach ($platformData as $platName => $platEntry) {
            $platform = $platEntry['platform'];
            $months   = $platEntry['months'];

            // Determine which columns to show for this platform
            $showReach    = $platform ? (bool)$platform->track_reach            : true;
            $showImp      = $platform ? (bool)$platform->track_impressions       : true;
            $showClicks   = $platform ? (bool)$platform->track_clicks            : true;
            $showSessions = $platform ? (bool)$platform->track_sessions          : true;
            $showEngaged  = $platform ? (bool)$platform->track_engaged_sessions  : true;
            $showUsers    = $platform ? (bool)$platform->track_users             : true;

            // Build column map: letter => [label, dataKey]
            $nextCol = 'C';
            $colMap  = [];
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
            $lastDataCol = empty($colMap) ? 'B' : chr(ord($nextCol) - 1);

            if (empty($colMap)) {
                // Platform has all columns disabled — skip engagement table
                continue;
            }

            // Only include platforms that have at least some engagement data
            $hasEngagement = false;
            foreach ($months as $m) {
                foreach (array_keys($colMap) as $colLetter) {
                    $key = $colMap[$colLetter]['key'];
                    if (($m[$key] ?? 0) > 0) { $hasEngagement = true; break 2; }
                }
            }
            if (!$hasEngagement) continue;

            $monthCount = count($months);

            // ── Dynamic chart column placement ───────────────────
            // Chart starts 2 columns after the last data column so there is one
            // blank spacer column between the table and the chart.
            $chartStartCol = chr(ord($lastDataCol) + 2);   // e.g. 'J' when lastDataCol='H'
            $chartEndCol   = chr(ord($chartStartCol) + 12); // 13-column wide chart area

            // Chart height:
            // • Horizontal bar charts give one "row" per month per metric series.
            //   Each month-row needs ~4 spreadsheet rows to render comfortably.
            // • Floor at 25 rows so even a single-month chart looks clean.
            $minChartRows = max(25, $monthCount * 4 + 12);
            $chartBottomRow = 0; // filled in after tableEndRow is known

            // ── Platform title row — spans table columns only, same style as main header ──
            $titleRow = $r;
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

            // ── Table header row ──────────────────────────────
            $hdrRow = $r;
            $sheet->setCellValue('B' . $r, 'Month');
            foreach ($colMap as $col => $def) {
                $sheet->setCellValue($col . $r, $def['label']);
            }
            $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUMHDR_BG);
            $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_SUMHDR_FG);
            $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(20);
            $r++;

            // ── Data rows ────────────────────────────────────
            $dataStart = $r;
            $si        = 0;
            $totals    = array_fill_keys(array_keys($colMap), 0);

            foreach ($months as $m) {
                $isAlt = ($si % 2 === 1);
                if ($isAlt) {
                    $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_ALT);
                }
                $sheet->setCellValue('B' . $r, $m['label']);
                foreach ($colMap as $col => $def) {
                    $val = $m[$def['key']] ?? 0;
                    $sheet->setCellValue($col . $r, $val ?: null);
                    $totals[$col] += $val;
                }
                $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getNumberFormat()->setFormatCode($numFmt);
                $si++;
                $r++;
            }

            $dataEnd = $r - 1;

            // ── Totals row ───────────────────────────────────
            $sheet->setCellValue('B' . $r, 'TOTAL');
            foreach ($colMap as $col => $def) {
                $sheet->setCellValue($col . $r, $totals[$col] ?: null);
            }
            $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
            $sheet->getStyle('B' . $r . ':' . $lastDataCol . $r)->getFont()->setBold(true);
            $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('C' . $r . ':' . $lastDataCol . $r)->getNumberFormat()->setFormatCode($numFmt);
            $tableEndRow = $r;

            // ── Borders ──────────────────────────────────────
            $sheet->getStyle('B' . $titleRow . ':' . $lastDataCol . $r)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // ── Chart — placed right after the table columns ──
            $chartBottomRow = max($r, $titleRow + $minChartRows);

            if ($monthCount >= 1 && $dataEnd >= $dataStart && !empty($colMap)) {
                $chart = $this->buildPlatformChartDynamic(
                    $sheetName, $platName, $hdrRow, $dataStart, $dataEnd, $monthCount, $colMap
                );
                $chart->setTopLeftPosition($chartStartCol . $titleRow);
                $chart->setBottomRightPosition($chartEndCol . $chartBottomRow);
                $sheet->addChart($chart);
            }

            // Advance row pointer past whichever is taller: table or chart
            $r = $chartBottomRow + 3;
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

        // Explicitly set all dLbls boolean flags so Excel doesn't consider the XML incomplete
        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setShowLegendKey(false);
        $layout->setShowCatName(false);
        $layout->setShowSerName(false);

        $xAxis = new Axis();
        if ($mc > 5) {
            // Rotate x-axis tick labels when there are many months to prevent overlap
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

        // Explicitly set all dLbls boolean flags so Excel doesn't consider the XML incomplete
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

    /**
     * Build platform bar chart: Reach + Impressions + Clicks per month
     *
     * Layout choice: horizontal bar chart (DIRECTION_BAR).
     * Each bar occupies its own horizontal lane so bar-end value labels
     * never overlap regardless of how many months or metrics are present.
     * Values are always shown.
     *
     * $colMap: [colLetter => ['label' => ..., 'key' => ...]]
     */
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
        // DIRECTION_BAR = horizontal bars:
        //   • Category labels (months) sit on the Y-axis — one per row, never crowded.
        //   • Value labels sit at the END of each bar on the X-axis — no overlap.
        $ds->setPlotDirection(DataSeries::DIRECTION_BAR);

        $layout = new Layout();
        $layout->setShowVal(true);          // always display bar-end values
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

        $layout = new Layout();
        $layout->setShowVal(true);

        return new Chart(
            $platName,
            new Title($platName . ' — Reach / Impressions / Clicks'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea($layout, [$ds]), true, 0, null, null
        );
    }
}
