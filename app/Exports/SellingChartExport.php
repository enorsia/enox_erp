<?php

namespace App\Exports;

use App\ApiServices\SellingChartApiService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Str;

class SellingChartExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private $items;
    private $endCol = "AF";
    private $sellingChartApiService;

    public function __construct($items)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $this->items = $items;
        $this->sellingChartApiService = app(SellingChartApiService::class);
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection()
    {
        $rows = collect();
        $styleNames = collect($this->items)->pluck('design_no')->unique()->toArray();

        $ecommerceProducts = $this->sellingChartApiService->getEcomProducts([
            'designNos' => $styleNames
        ]);

        $ecomProducts = $ecommerceProducts->keyBy(fn($item) => $item['style']['name'] ?? null);

        foreach ($this->items as $itemId => $item) {
            $ecommerceProduct = $ecomProducts[$item->design_no] ?? null;

            $row = [
                $itemId + 1,
                $item->department_name,
                $item->season_name,
                $item->phase_name,
                $item->initial_repeated_status,
                $item->product_launch_month,
                $item->category_name,
                $item->mini_category_name,
                $item->product_code,
                $ecommerceProduct['sku'] ?? '',
                $item->design_no,
                null,
                $item->product_description,
                $item->fabrication
            ];

            foreach ($item->sellingChartPrices as $ch_price) {
                // Create rows for each product

                $size = $ch_price->size ? $ch_price->size . ' ' . Str::productSize($ch_price->size, $item->department_id, 'uk') : null;

                $rows->push(array_merge($row, [
                    $ch_price->color_code,
                    $ch_price->color_name,
                    $size,
                    $ch_price->range ?? null,
                    $ch_price->po_order_qty,
                    $ch_price->price_fob,
                    $ch_price->unit_price,
                    $ch_price->confirm_selling_price ?? 0,
                    $ch_price->vat_price ?? 0,
                    $ch_price->vat_value ?? 0,
                    $ch_price->profit_margin ?? 0,
                    $ch_price->net_profit ?? 0,
                    $ch_price->discount ?? 0,
                    $ch_price->discount_selling_price ?? 0,
                    $ch_price->discount_vat_price ?? 0,
                    $ch_price->discount_vat_value ?? 0,
                    $ch_price->discount_profit_margin ?? 0,
                    $ch_price->discount_net_profit ?? 0,
                ]));

                $row = [
                    '',
                    $item->department_name,
                    $item->season_name,
                    $item->phase_name,
                    $item->initial_repeated_status,
                    $item->product_launch_month,
                    $item->category_name,
                    $item->mini_category_name,
                    $item->product_code,
                    $ecommerceProduct['sku'] ?? '',
                    $item->design_no,
                    null,
                    '',
                    ''
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'SL.',
            'Department',
            'Season',
            'Season Phase',
            'Initial/ Repeat Order',
            'Product Launch Month',
            'Product Category',
            'Mini Category',
            'Product Code',
            'Ecom Sku',
            'Design No',
            'Inspiration Image',
            'Product Description',
            'Fabrication',
            'Color Code',
            'Color Name',
            'Size (Age)',
            'Range',
            'PO Order Qty',
            'Price $ (FOB)',
            'Unit Price £',
            'Confirm Selling Price £',
            '20% Selling VAT',
            'Vat Value £',
            'Profit Margin %',
            'Net Profit £',
            'Discount %',
            'Discount Selling Price',
            '20% Selling Vat Dedact Price',
            'Discount Vat Value £',
            'Discount Profit Margin %',
            'Discount Net Profit',
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
                    'SELLING CHART REPORTS',
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

                    // for image show
                    $imagePath = public_path('upload/selling_images/' . $item->inspiration_image);

                    if ($item->inspiration_image && file_exists($imagePath)) {
                        $drawing = new Drawing();
                        $drawing->setName('Inspiration Image');
                        $drawing->setDescription('Inspiration Image');
                        $drawing->setPath($imagePath);
                        $drawing->setHeight(50);
                        $drawing->setCoordinates("L{$rowCount}");
                        $drawing->setWorksheet($sheet);

                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(5);
                    }

                    $sheet->getRowDimension($rowCount)->setRowHeight(max(50, 50));
                    // for image show

                    // if ($pricesCount > 1) {
                    //     // Merge cells for 'SL.:'
                    //     $sheet->mergeCells("A{$rowCount}:A" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("B{$rowCount}:B" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("C{$rowCount}:C" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("D{$rowCount}:D" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("E{$rowCount}:E" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("F{$rowCount}:F" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("G{$rowCount}:G" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("H{$rowCount}:H" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("I{$rowCount}:I" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("J{$rowCount}:J" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("K{$rowCount}:K" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("L{$rowCount}:L" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("M{$rowCount}:M" . ($rowCount + $pricesCount - 1));
                    //     $sheet->mergeCells("N{$rowCount}:N" . ($rowCount + $pricesCount - 1));
                    // }

                    // Add a thick black border after the last product row
                    $lastPriceRow = $rowCount + $pricesCount - 1;
                    $sheet->getStyle("A{$lastPriceRow}:{$this->endCol}{$lastPriceRow}")->applyFromArray([
                        'borders' => [
                            'bottom' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                                'color' => ['argb' => '00000000'],
                            ],
                        ],
                    ]);

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

                for ($char = ord('R'); $char <= ord('Z'); $char++) {
                    $cell = strtoupper(chr($char));
                    if ($cell == 'R') $sheet->setCellValue($cell . ($lastPriceRow + 1), "Total");

                    if ($cell == 'S') {
                        $sheet->setCellValue(
                            $cell . ($lastPriceRow + 1),
                            "=SUM(" . $cell . "7:" . $cell . $lastPriceRow . ")"
                        );
                    }
                    if ($cell == 'T') {
                        $sheet->setCellValue(
                            $cell . ($lastPriceRow + 1),
                            '="$ " & TEXT(SUM(' . $cell . '7:' . $cell . $lastPriceRow . '), "0.00")'
                        );
                    }
                    if (in_array($cell, ['Y'])) {
                        $sheet->setCellValue(
                            $cell  . ($lastPriceRow + 1),
                            '="" & TEXT(AVERAGE(' . $cell . '7:' . $cell . $lastPriceRow . '), "0.00") & " %"'
                        );
                    }
                    if (in_array($cell, ['U', 'V', 'W', 'X', 'Z'])) {
                        $sheet->setCellValue(
                            $cell . ($lastPriceRow + 1),
                            '="£ " & TEXT(SUM(' . $cell . '7:' . $cell . $lastPriceRow . '), "0.00")'
                        );
                    }
                }

                for ($char = ord('A'); $char <= ord('F'); $char++) {
                    $cell = strtoupper(chr($char));
                    if (in_array($cell, ['B', 'C', 'D', 'F'])) {
                        $cell = 'A' . strtoupper(chr($char));
                        $sheet->setCellValue(
                            $cell . ($lastPriceRow + 1),
                            '="£ " & TEXT(SUM(' . $cell . '7:' . $cell . $lastPriceRow . '), "0.00")'
                        );
                    }
                    if (in_array($cell, ['A', 'E'])) {
                        $cell = 'A' . strtoupper(chr($char));
                        $sheet->setCellValue(
                            $cell  . ($lastPriceRow + 1),
                            '="" & TEXT(AVERAGE(' . $cell . '7:' . $cell . $lastPriceRow . '), "0.00") & " %"'
                        );
                    }
                }


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
