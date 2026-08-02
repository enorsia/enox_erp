<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EcomTrackerDashboardSheetExport implements FromCollection, WithHeadings, WithTitle, WithEvents, WithCustomStartCell
{
    private const CLR_HDR_BG = 'FF009966';

    private const CLR_HDR_FG = 'FFFFFFFF';

    private const CLR_ROW_ALT = 'FFF0FAF5';

    private const CLR_BORDER_OUTLINE = 'FF009966';

    private const CLR_BORDER_INNER = 'FFB0B0B0';

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private string $title,
        private array $rows,
        private string $rangeLabel = '',
        private string $reportPrefix = 'ECOM TRACKER DASHBOARD',
    ) {}

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection(): Collection
    {
        if ($this->rows === []) {
            return collect([['No data for selected period']]);
        }

        return collect($this->rows)->map(function (array $row) {
            return collect($row)->values()->all();
        });
    }

    public function headings(): array
    {
        if ($this->rows === []) {
            return ['Message'];
        }

        return array_map(
            fn (string $key) => str($key)->headline()->toString(),
            array_keys($this->rows[0]),
        );
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colCount = max(1, count($this->headings()));
                $endCol = Coordinate::stringFromColumnIndex($colCount);
                $columnKeys = $this->rows === [] ? ['message'] : array_keys($this->rows[0]);

                $this->applyHeaderRows(
                    $sheet,
                    $endCol,
                    strtoupper($this->reportPrefix).' — '.strtoupper($this->title),
                    $this->rangeLabel,
                );
                $this->applyHeadingStyle($sheet, $endCol, $columnKeys);
                $this->applyDataStyle($sheet, $endCol, $columnKeys);
                $this->applyColumnWidths($sheet, $columnKeys);
            },
        ];
    }

    private function applyHeaderRows($sheet, string $endCol, string $title, string $rangeLabel): void
    {
        $appName = config('app.name', 'ENOX ERP');
        $generated = 'Generated: '.now()->format('d M Y H:i');
        $info = [
            $appName,
            $title,
            $rangeLabel !== '' ? "{$rangeLabel} · {$generated}" : $generated,
        ];

        foreach ($info as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$endCol}{$row}");
        }

        $sheet->getStyle("A1:{$endCol}3")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(18)->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(20);
    }

    /**
     * @param  array<int, string>  $columnKeys
     */
    private function applyHeadingStyle($sheet, string $endCol, array $columnKeys): void
    {
        $sheet->getStyle("A6:{$endCol}6")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CLR_HDR_BG]],
            'font' => ['bold' => true, 'color' => ['argb' => self::CLR_HDR_FG]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(28);

        foreach ($columnKeys as $idx => $key) {
            if (! $this->isLeftAlignedKey($key)) {
                continue;
            }

            $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->getStyle("{$excelCol}6")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    /**
     * @param  array<int, string>  $columnKeys
     */
    private function applyDataStyle($sheet, string $endCol, array $columnKeys): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 7) {
            $sheet->freezePane('A7');

            return;
        }

        $sheet->getStyle("A7:{$endCol}{$highestRow}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => self::CLR_BORDER_INNER],
                ],
            ],
        ]);

        $sheet->getStyle("A6:{$endCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => self::CLR_BORDER_OUTLINE],
                ],
            ],
        ]);

        for ($row = 7; $row <= $highestRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$endCol}{$row}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(self::CLR_ROW_ALT);
            }
        }

        foreach ($columnKeys as $idx => $key) {
            $excelCol = Coordinate::stringFromColumnIndex($idx + 1);

            if ($this->isLeftAlignedKey($key)) {
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
            } elseif ($this->isNumericKey($key)) {
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($this->isMoneyKey($key)) {
                    $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                } elseif ($this->isPercentKey($key)) {
                    $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.0');
                }
            }
        }

        $sheet->freezePane('A7');
    }

    /**
     * @param  array<int, string>  $columnKeys
     */
    private function applyColumnWidths($sheet, array $columnKeys): void
    {
        $widths = [
            'metric' => 30,
            'value' => 18,
            'formatted' => 20,
            'stage' => 24,
            'sessions' => 16,
            'percent_of_top' => 18,
            'drop_off_percent' => 20,
            'date' => 16,
            'purchases' => 14,
            'conversion_rate' => 18,
            'category' => 28,
            'product' => 38,
            'code' => 18,
            'product_code' => 18,
            'sku' => 18,
            'color' => 20,
            'views' => 14,
            'add_to_cart' => 14,
            'purchases' => 12,
            'revenue' => 16,
            'sale' => 16,
            'adds' => 14,
            'sale_items' => 14,
            'sale_amount' => 16,
            'add_rate' => 14,
            'viewed' => 14,
            'purchased' => 14,
            'session' => 20,
            'last_item' => 34,
            'coupon' => 18,
            'cart_value' => 16,
            'total' => 16,
            'idle' => 14,
            'source' => 22,
            'medium' => 18,
            'location' => 30,
            'device' => 20,
            'share' => 12,
            'signal' => 16,
            'visitor_id' => 28,
            'session_count' => 14,
            'orders' => 12,
            'total_stay' => 16,
            'avg_stay' => 16,
            'first_seen' => 20,
            'last_active' => 20,
            'browser' => 18,
            'segment' => 22,
            'duration_bucket' => 18,
            'unique_visitors' => 18,
            'size' => 12,
            'qty' => 12,
            'add_to_cart' => 14,
        ];

        foreach ($columnKeys as $idx => $key) {
            $heading = str($key)->headline()->toString();
            $width = $widths[$key] ?? 18;
            $width = max($width, min(42, strlen($heading) + 6));

            $sheet->getColumnDimensionByColumn($idx + 1)->setWidth($width);
        }
    }

    private function isLeftAlignedKey(string $key): bool
    {
        return in_array($key, [
            'metric',
            'stage',
            'name',
            'category',
            'product',
            'color',
            'sku',
            'session',
            'last_item',
            'coupon',
            'detail',
            'source',
            'medium',
            'location',
            'label',
            'device',
            'idle',
            'activity_url',
            'signal',
            'signal_label',
            'code',
            'product_code',
            'date',
            'visitor_id',
            'browser',
            'segment',
            'duration_bucket',
            'first_seen',
            'last_active',
            'total_stay',
            'avg_stay',
            'size',
        ], true);
    }

    private function isNumericKey(string $key): bool
    {
        return in_array($key, [
            'value',
            'cart_value',
            'total',
            'views',
            'add_to_cart',
            'purchases',
            'sale_items',
            'sale_amount',
            'revenue',
            'sale',
            'sessions',
            'percent_of_top',
            'drop_off_percent',
            'add_rate',
            'conversion_rate',
            'viewed',
            'purchased',
            'share',
            'session_count',
            'orders',
            'unique_visitors',
            'qty',
            'add_to_cart',
        ], true) || str_contains($key, 'rate') || str_contains($key, 'percent');
    }

    private function isMoneyKey(string $key): bool
    {
        return in_array($key, ['revenue', 'sale', 'sale_amount', 'value', 'cart_value', 'total'], true);
    }

    private function isPercentKey(string $key): bool
    {
        return in_array($key, [
            'add_rate',
            'conversion_rate',
            'percent_of_top',
            'drop_off_percent',
            'share',
        ], true) || str_contains($key, 'rate') || str_contains($key, 'percent');
    }
}
