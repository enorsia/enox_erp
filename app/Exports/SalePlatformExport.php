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

class SalePlatformExport implements FromQuery, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell, WithMapping
{
    public function __construct(
        private Builder $query,
        private array $columns = []
    ) {}

    public function startCell(): string { return 'A6'; }

    public function query()
    {
        return $this->query;
    }

    public function map($row): array
    {
        $cols = $this->columns ?: $this->allColumns();
        $map  = $this->rowMap($row);
        return array_values(array_intersect_key($map, array_flip($cols)));
    }

    public function headings(): array
    {
        $cols = $this->columns ?: $this->allColumns();
        $labels = $this->columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }

    private function rowMap($row): array
    {
        return [
            'id'         => $row->id,
            'name'       => $row->name,
            'slug'       => $row->slug,
            'parent'     => $row->parent?->name ?? '-',
            'type'       => ucfirst(str_replace('_', ' ', $row->type)),
            'is_active'  => $row->is_active ? 'Active' : 'Inactive',
            'sort_order' => $row->sort_order,
            'created_at' => $row->created_at?->format('d M Y'),
        ];
    }

    public static function allColumns(): array
    {
        return ['id', 'name', 'slug', 'parent', 'type', 'is_active', 'sort_order', 'created_at'];
    }

    public static function columnLabels(): array
    {
        return [
            'id'         => '#',
            'name'       => 'Name',
            'slug'       => 'Slug',
            'parent'     => 'Parent',
            'type'       => 'Type',
            'is_active'  => 'Status',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $cols  = count($this->columns ?: $this->allColumns());
                $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);

                $this->applyHeaderRows($sheet, $endCol, 'SALE PLATFORMS');
                $this->applyHeadingStyle($sheet, $endCol);
                $this->applyDataStyle($sheet, $endCol);
            },
        ];
    }

    private function applyHeaderRows($sheet, string $endCol, string $title): void
    {
        $appName = config('app.name', 'ENOX ERP');
        $info    = [$appName, $title, 'Generated: ' . now()->format('d M Y H:i')];

        foreach ($info as $i => $text) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$endCol}{$row}");
        }

        $sheet->getStyle("A1:{$endCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A1')->getFont()->setSize(18)->setBold(true);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(18);
    }

    private function applyHeadingStyle($sheet, string $endCol): void
    {
        $sheet->getStyle("A6:{$endCol}6")->applyFromArray([
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(20);
    }

    private function applyDataStyle($sheet, string $endCol): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 7) {
            $sheet->getStyle("A7:{$endCol}{$highestRow}")->applyFromArray([
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->freezePane('A7');
    }
}

