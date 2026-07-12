<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

class ReturnReasonTypeExport implements FromQuery, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell, WithMapping
{
    private int $rowIndex = 0;

    public function __construct(
        private Builder $query,
        private array $columns = []
    ) {}

    public function startCell(): string { return 'A6'; }

    public function query()
    {
        Log::info('ReturnReasonTypeExport: query() called', [
            'columns' => $this->columns ?: $this->allColumns(),
        ]);
        return $this->query;
    }

    public function map($row): array
    {
        try {
        $this->rowIndex++;
        $cols = $this->columns ?: $this->allColumns();
        $map  = $this->rowMap($row);
        $map['id'] = $this->rowIndex;
        return array_values(array_intersect_key($map, array_flip($cols)));
        } catch (\Throwable $e) {
            Log::error('ReturnReasonTypeExport: map() failed', [
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace'      => $e->getTraceAsString(),
                'row_index'  => $this->rowIndex,
                'row_id'     => $row->id ?? null,
            ]);
            throw $e;
        }
    }

    public function headings(): array
    {
        $cols   = $this->columns ?: $this->allColumns();
        $labels = $this->columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }

    private function rowMap($row): array
    {
        return [
            'id'          => $this->rowIndex,
            'name'        => $row->name,
            'slug'        => $row->slug,
            'description' => $row->description ?? '-',
            'is_active'   => $row->is_active ? 'Active' : 'Inactive',
            'sort_order'  => (int) ($row->sort_order ?? 0),
            'created_at'  => $row->created_at?->format('d M Y'),
            'updated_at'  => $row->updated_at?->format('d M Y'),
        ];
    }

    public static function allColumns(): array
    {
        return ['id', 'name', 'slug', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'];
    }

    public static function columnLabels(): array
    {
        return [
            'id'          => 'SL',
            'name'        => 'Name',
            'slug'        => 'Slug',
            'description' => 'Description',
            'is_active'   => 'Status',
            'sort_order'  => 'Sort Order',
            'created_at'  => 'Created At',
            'updated_at'  => 'Updated At',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                Log::info('ReturnReasonTypeExport: AfterSheet styling started');
                try {
                $sheet      = $event->sheet->getDelegate();
                $activeCols = $this->columns ?: $this->allColumns();
                $cols       = count($activeCols);
                $endCol     = Coordinate::stringFromColumnIndex($cols);

                $this->applyHeaderRows($sheet, $endCol, 'RETURN REASON TYPES');
                $this->applyHeadingStyle($sheet, $endCol);
                $this->applyDataStyle($sheet, $endCol, $activeCols);

                Log::info('ReturnReasonTypeExport: AfterSheet styling completed successfully', [
                    'total_rows' => $this->rowIndex,
                ]);
                } catch (\Throwable $e) {
                    Log::error('ReturnReasonTypeExport: AfterSheet styling failed', [
                        'error' => $e->getMessage(),
                        'class' => get_class($e),
                        'file'  => $e->getFile(),
                        'line'  => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            },
        ];
    }

    private function applyHeaderRows($sheet, string $endCol, string $title): void
    {
        $appName = config('app.name', 'ENOX ERP');
        foreach ([$appName, $title, 'Generated: ' . now()->format('d M Y H:i')] as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$endCol}{$row}");
        }
        $sheet->getStyle("A1:{$endCol}3")->applyFromArray([
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

    private function applyHeadingStyle($sheet, string $endCol): void
    {
        $sheet->getStyle("A6:{$endCol}6")->applyFromArray([
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF009966']],
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(20);
    }

    private function applyDataStyle($sheet, string $endCol, array $activeCols): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 7) {
            $sheet->freezePane('A7');
            return;
        }

        $sheet->getStyle("A7:{$endCol}{$highestRow}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFB0B0B0'],
                ],
            ],
        ]);

        $sheet->getStyle("A6:{$endCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF009966'],
                ],
            ],
        ]);

        $leftCols = ['name', 'slug', 'description'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $leftCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
            }
        }

        $sheet->freezePane('A7');
    }


}
