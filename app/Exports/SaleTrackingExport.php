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

    public function __construct(private array $filters = []) {}

    public function download(SaleTrackingService $service): StreamedResponse
    {
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
        $filename = 'ad-tracking-' . now()->format('Y-m-d') . '.xlsx';

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
    }

    private function applyHeaderRows($sheet, string $title): void
    {
        $appName = config('app.name', 'ENOX ERP');
        foreach ([$appName, $title, 'Generated: ' . now()->format('d M Y H:i')] as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:" . self::LAST_COL . "{$row}");
        }
        $sheet->getStyle('A1:' . self::LAST_COL . '3')->applyFromArray([
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

        $this->applyHeaderRows($sheet, 'Enorsia Digital Ad Performance Tracking');

        foreach (self::COLUMNS as $col => $def) {
            $sheet->setCellValue($col . '6', $def['label']);
            $sheet->getColumnDimension($col)->setWidth($def['width']);
        }
        $hdrRange = 'A6:' . self::LAST_COL . '6';
        $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_HDR_BG);
        $sheet->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_HDR_FG);
        $sheet->getStyle($hdrRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension(6)->setRowHeight(32);

        $r          = 7;
        $sl         = 1;
        $monthIndex = 0;
        $prevQRow   = null;
        $grandTotalReturn = 0.0;

        $mergedCols = ['O', 'L', 'Q', 'R', 'S', 'T', 'U'];

        foreach ($monthGroups as $mk => $group) {
            $monthStartRow = $r;
            $monthLabel    = $group['label'];
            $monthBg       = self::MONTH_BG_COLORS[$monthIndex % count(self::MONTH_BG_COLORS)];
            $mt            = $monthTotals[$mk];

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
                $sheet->setCellValue('J' . $r, $entry['net_cost'] ?: null);
                $sheet->setCellValue('K' . $r, $entry['ads_tax']  ?: null);
                $sheet->setCellValue('M' . $r, $entry['orders']   ?: null);
                $sheet->setCellValue('N' . $r, $entry['products'] ?: null);
                $sheet->setCellValue('P' . $r, $entry['revenue']  ?: null);

                $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($monthBg);
                $sheet->getStyle('D' . $r . ':' . self::LAST_COL . $r)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $r++;
            }

            $monthEndRow = $r - 1;

            $grandTotalReturn += $mt['total_return'];

            $ms = $monthStartRow;
            $me = $monthEndRow;

            $sheet->setCellValue('L' . $ms, "=SUM(K{$ms}:K{$me})");

            $sheet->setCellValue('Q' . $ms, "=SUM(P{$ms}:P{$me})");

            $sheet->setCellValue('R' . $ms, $mt['total_return'] ?: null);

            if ($prevQRow !== null) {
                $sheet->setCellValue('O' . $ms, "=IFERROR((Q{$ms}-Q{$prevQRow})/Q{$prevQRow},\"\")");
            } else {
                $firstMonthRevenue = (float) array_sum(array_column($group['entries'], 'revenue'));
                if ($prevMonthTotalRevenue !== null && $prevMonthTotalRevenue > 0) {
                    $sheet->setCellValue('O' . $ms, ($firstMonthRevenue - $prevMonthTotalRevenue) / $prevMonthTotalRevenue);
                } else {
                    $sheet->setCellValue('O' . $ms, 0.0);
                }
            }

            $sheet->setCellValue('S' . $ms, "=IFERROR(Q{$ms}-R{$ms},\"\")");

            $sheet->setCellValue('U' . $ms, "=IFERROR((Q{$ms}/L{$ms})*100,\"\")");

            $sheet->setCellValue('T' . $ms, "=IFERROR(ROUND(U{$ms},0),\"\")");

            $prevQRow = $ms;

            if ($monthEndRow > $monthStartRow) {
                $sheet->mergeCells('B' . $ms . ':B' . $me);
            }
            $sheet->getStyle('B' . $ms)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

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

        $totalsRow = $r;

        $sheet->setCellValue('C' . $r, 'TOTAL');

        foreach (['D','E','F','G','H','I'] as $col) {
            $sheet->setCellValue($col . $r, "=SUM({$col}7:{$col}{$dataEndRow})");
        }
        $sheet->setCellValue('J' . $r, "=SUM(J7:J{$dataEndRow})");
        $sheet->setCellValue('K' . $r, "=SUM(K7:K{$dataEndRow})");
        $sheet->setCellValue('L' . $r, "=SUM(K7:K{$dataEndRow})");
        $sheet->setCellValue('M' . $r, "=SUM(M7:M{$dataEndRow})");
        $sheet->setCellValue('N' . $r, "=SUM(N7:N{$dataEndRow})");
        $sheet->setCellValue('P' . $r, "=SUM(P7:P{$dataEndRow})");
        $sheet->setCellValue('Q' . $r, "=SUM(P7:P{$dataEndRow})");
        $sheet->setCellValue('R' . $r, $grandTotalReturn ?: null);
        $sheet->setCellValue('S' . $r, "=IFERROR(Q{$r}-R{$r},\"\")");
        $sheet->setCellValue('U' . $r, "=IFERROR((Q{$r}/L{$r})*100,\"\")");
        $sheet->setCellValue('T' . $r, "=IFERROR(ROUND(U{$r},0),\"\")");

        $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_TOTAL);
        $sheet->getStyle('A' . $r . ':' . self::LAST_COL . $r)->getFont()->setBold(true)->getColor()->setARGB(self::CLR_TOTAL_FG);
        $sheet->getStyle('D' . $r . ':' . self::LAST_COL . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($dataEndRow >= 7) {
            foreach (['J','K','L','P','Q','R','S'] as $col) {
                $sheet->getStyle($col . '7:' . $col . $totalsRow)->getNumberFormat()->setFormatCode($moneyFmt);
            }
            foreach (['D','E','F','G','H','I','M','N'] as $col) {
                $sheet->getStyle($col . '7:' . $col . $totalsRow)->getNumberFormat()->setFormatCode($numFmt);
            }
            $sheet->getStyle('O7:O' . $totalsRow)->getNumberFormat()->setFormatCode($pctFmt);
            $sheet->getStyle('T7:T' . $totalsRow)->getNumberFormat()->setFormatCode('0"%"');
            $sheet->getStyle('U7:U' . $totalsRow)->getNumberFormat()->setFormatCode('0.00"%"');
        }

        $sheet->getStyle('A1:' . self::LAST_COL . $totalsRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('D7');

        $summaryStart = $totalsRow + 3;
        $summaryEnd   = $this->writeMonthlySummary($sheet, $monthAgg, $summaryStart);

        $chartStart   = $summaryEnd + 3;
        $monthCount   = count($monthAgg);
        $overviewEnd  = $chartStart;
        if ($monthCount >= 1) {
            $overviewEnd = $this->writeOverviewCharts($sheet, $sheetName, $summaryStart, $summaryEnd, $monthCount, $chartStart);
        }

        $platformStart = $overviewEnd + 3;
        $this->writePlatformSections($sheet, $sheetName, $platformData, $platformStart);
    }

    private function writeMonthlySummary($sheet, array $monthAgg, int $startRow): int
    {
        if (empty($monthAgg)) return $startRow;

        $sheet->setCellValue('B' . $startRow, 'Monthly Performance Summary');
        $sheet->mergeCells('B' . $startRow . ':J' . $startRow);
        $sheet->getStyle('B' . $startRow)->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::CLR_SEC_FG);
        $sheet->getStyle('B' . $startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SEC_BG);
        $sheet->getStyle('B' . $startRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($startRow)->setRowHeight(22);

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

        $sheet->setCellValue('B' . $r, 'TOTAL');
        foreach (['C' => $sumRev, 'D' => $sumCost, 'E' => $sumNet,
                  'F' => $sumOrd, 'G' => $sumClk,  'H' => $sumImp] as $col => $val) {
            $sheet->setCellValue($col . $r, $val ?: null);
        }
        $sheet->getStyle('B' . $r . ':J' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CLR_SUM_TOTAL);
        $sheet->getStyle('B' . $r . ':J' . $r)->getFont()->setBold(true);
        $sheet->getStyle('C' . $r . ':J' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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

    private function writeOverviewCharts($sheet, string $sn, int $summaryStart, int $summaryEnd, int $mc, int $chartTopRow): int
    {
        $dataStart = $summaryStart + 2;
        $dataEnd   = $summaryEnd - 1;
        if ($dataEnd < $dataStart) return $chartTopRow;

        $chartH = max(28, $mc + 4);

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

        return $row2Bottom;
    }

    private function writePlatformSections($sheet, string $sheetName, array $platformData, int $startRow): void
    {
        if (empty($platformData)) return;

        $numFmt   = '#,##0';

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

            $showReach    = $platform ? (bool)$platform->track_reach            : true;
            $showImp      = $platform ? (bool)$platform->track_impressions       : true;
            $showClicks   = $platform ? (bool)$platform->track_clicks            : true;
            $showSessions = $platform ? (bool)$platform->track_sessions          : true;
            $showEngaged  = $platform ? (bool)$platform->track_engaged_sessions  : true;
            $showUsers    = $platform ? (bool)$platform->track_users             : true;

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
                continue;
            }

            $hasEngagement = false;
            foreach ($months as $m) {
                foreach (array_keys($colMap) as $colLetter) {
                    $key = $colMap[$colLetter]['key'];
                    if (($m[$key] ?? 0) > 0) { $hasEngagement = true; break 2; }
                }
            }
            if (!$hasEngagement) continue;

            $monthCount = count($months);

            $chartStartCol = chr(ord($lastDataCol) + 2);
            $chartEndCol   = chr(ord($chartStartCol) + 12);

            $minChartRows = max(25, $monthCount * 4 + 12);
            $chartBottomRow = 0;

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

            $sheet->getStyle('B' . $titleRow . ':' . $lastDataCol . $r)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $chartBottomRow = max($r, $titleRow + $minChartRows);

            if ($monthCount >= 1 && $dataEnd >= $dataStart && !empty($colMap)) {
                $chart = $this->buildPlatformChartDynamic(
                    $sheetName, $platName, $hdrRow, $dataStart, $dataEnd, $monthCount, $colMap
                );
                $chart->setTopLeftPosition($chartStartCol . $titleRow);
                $chart->setBottomRightPosition($chartEndCol . $chartBottomRow);
                $sheet->addChart($chart);
            }

            $r = $chartBottomRow + 3;
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
}
