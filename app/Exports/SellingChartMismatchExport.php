<?php

namespace App\Exports;

use App\ApiServices\SellingChartApiService;
use App\Models\EcommerceProduct;
use App\Models\LookupName;
use App\Models\POHistory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class SellingChartMismatchExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private $items;
    private $sellingChartApiService;
    private $endCol = "K";

    public function __construct($items)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $this->items = $items;
        // dd($this->items);
        $this->sellingChartApiService = app(SellingChartApiService::class);
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function normalizeRange($range)
    {
        $range = strtolower($range);

        $range = str_replace(
            ['years', 'year', 'yrs', 'yr', 'months', 'month', 'mths', 'mth', 'to'],
            ['', '', '', '', '', '', '', '', ' '],
            $range
        );

        // normalize separators
        $range = str_replace(['-', '–', '—'], ' ', $range);

        // extract fractions + decimals + numbers
        preg_match_all(
            '/\d+(?:\.\d+)?\s*\/\s*\d+(?:\.\d+)?|\d+(?:\.\d+)?/',
            $range,
            $matches
        );

        $sizes = $matches[0] ?? [];

        // clean spaces
        $sizes = array_map(fn($v) => str_replace(' ', '', $v), $sizes);

        // keep only first & last if more than 2
        if (count($sizes) > 2) {
            $sizes = [reset($sizes), end($sizes)];
        }
        return $sizes;
    }

    public function collection()
    {
        $rows = collect();

        $styleNames = collect($this->items)->pluck('design_no')->unique()->toArray();

        $lookupData = $this->sellingChartApiService->getLookupResponse([], $styleNames);
        $styles = collect($lookupData)->map(fn($item) => (object) $item);
        $styleByKey = $styles->keyBy(fn ($p) => $p->name);
        $styleIds = $styles->pluck('id')->toArray();

        $phData = $this->sellingChartApiService->getPoHistoryResponse($styleIds);
        $poHistories = collect($phData)->map(fn($item) => (object) $item);

        $poHistorySizeMap = [];
        $poHistoryMap = [];
        foreach ($poHistories as $po) {
            $skey = $po->style_design_id . '|' . $po->size_name;
            $key = $po->style_design_id;
            $poHistorySizeMap[$skey] = $po;
            $poHistoryMap[$key] = $po;
        }

        $ecommerceProducts = $this->sellingChartApiService->getEcomProducts([
            'designNos' => $styleNames
        ]);
        $ecomProducts = $ecommerceProducts->keyBy(fn($item) => $item['style']['name'] ?? null);

        $sl = 1;

        foreach ($this->items as $itemId => $item) {
            $ecommerceProduct = $ecomProducts[$item->design_no] ?? null;

            foreach ($item->sellingChartPrices as $ch_price) {
                $row = [
                    $sl,
                    $item->department_name,
                    $ecommerceProduct['sku'] ?? '',
                    $item->design_no,
                ];
                $getSizeNames = null;
                if ($ch_price->range) $getSizeNames = $this->normalizeRange($ch_price->range);
                $style = $styleByKey->get($item->design_no);
                $poH = null;
                if ($style && $getSizeNames) {
                    foreach ($getSizeNames as $sizeName) {
                        $key = $style->id . '|' . $sizeName;
                        if (isset($poHistorySizeMap[$key])) {
                            $poH = $poHistorySizeMap[$key];
                            break;
                        }
                    }
                } else {
                    $key = $style?->id;
                    // dd($poHistoryMap[$key]->toArray());
                    $poH = isset($poHistoryMap[$key]) && $style ? $poHistoryMap[$key] : null;
                }

                if ((float)$ch_price->price_fob != $poH?->unit_price || (float)$ch_price->confirm_selling_price != $poH?->selling_price) {
                    // dd($item->design_no,$ch_price->toArray(), $poH);
                    $rows->push(array_merge($row, [
                        $ch_price->color_code,
                        $ch_price->color_name,
                        $ch_price->range ?? null,
                        $ch_price->price_fob,
                        $poH?->unit_price ?? 0,
                        $ch_price->confirm_selling_price ?? 0,
                        $poH?->selling_price ?? 0,
                    ]));

                    $sl += 1;
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'SL.',
            'Department',
            'Ecom Sku',
            'Design No',
            'Color Code',
            'Color Name',
            'Range',
            'Selling Chart - FOB($)',
            'Enox - FOB($)',
            'Selling Chart - Confirm Selling Price (£)',
            'Enox - Confirm Selling Price (£)',
        ];
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $fromToDate = '';
                $companyInfo = [
                    'PFD ENORSIA UK LTD',
                    'Price Mismatch Report',
                    $fromToDate
                ];

                foreach ($companyInfo as $index => $info) {
                    $rowNumber = $index + 1; // Rows 1 to 3
                    $sheet->setCellValue("A{$rowNumber}", $info);
                    $sheet->mergeCells("A{$rowNumber}:L{$rowNumber}");
                }

                // Apply styles to rows 1-3
                $sheet->getStyle('A1:L3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16, // Adjust font size (e.g., 16 for slightly bigger)
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, // Center align
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A6:{$this->endCol}6")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12, // Adjust font size
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => 'FFB6C1', // Light pink background color (you can change to another color)
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, // Center align
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'bitems' => [
                        'outline' => [
                            'bitemstyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                ]);


                $rowCount = 7; // Start from row 2 (after headings)

                foreach ($this->items as $item) {
                    $pricesCount = count($item->sellingChartPrices);

                    $lastPriceRow = $rowCount + $pricesCount - 1;
                    // $sheet->getStyle("A{$lastPriceRow}:{$this->endCol}{$lastPriceRow}")->applyFromArray([
                    //     'borders' => [
                    //         'bottom' => [
                    //             'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    //             'color' => ['argb' => '00000000'],
                    //         ],
                    //     ],
                    // ]);

                    // Move to the next group of rows for the next order
                    $rowCount += $pricesCount;
                }

                $startRow = 7;
                $lastPrIceRowUp = $sheet->getHighestRow();
                $sheet->getStyle("T{$startRow}:{$this->endCol}{$lastPrIceRowUp}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

                // Add Total Quantity and Total Price
                $lastPriceRow = $rowCount - 1;


                $sheet->getStyle("Q" . ($lastPriceRow + 1) . ":{$this->endCol}" . ($lastPriceRow + 1))->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14, // Bigger font size
                    ]
                ]);

                // Center align all headings
                $sheet->getStyle("A6:{$this->endCol}6")->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Center align the data cells
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A6:{$this->endCol}{$highestRow}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->freezePane('A7');
            },
        ];
    }
}
