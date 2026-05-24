<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalePlatformExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private int $dataRowIdx = 0;

    private array $mergeRanges = [];

    public function __construct(
        private Builder $query,
        private array $columns = []
    ) {}

    public function startCell(): string { return 'A6'; }


    public function collection(): Collection
    {
        $this->dataRowIdx  = 0;
        $this->mergeRanges = [];

        $allPlatforms = $this->query->get();

        $childrenMap = $allPlatforms->groupBy('parent_id');
        $roots       = $allPlatforms->whereNull('parent_id')->sortBy('sort_order')->values();

        $rows = [];
        foreach ($roots as $root) {
            $this->appendPlatformRows($root, $childrenMap, $rows);
        }

        foreach ($rows as $i => &$row) {
            $row['id'] = $i + 1;
        }
        unset($row);

        $activeCols = $this->columns ?: self::allColumns();

        return collect(array_map(
            fn($row) => array_values(array_intersect_key($row, array_flip($activeCols))),
            $rows
        ));
    }

    private function appendPlatformRows($root, Collection $childrenMap, array &$rows): void
    {
        $children = ($childrenMap->get($root->id) ?? collect())->sortBy('sort_order')->values();

        if ($children->isEmpty()) {
            $this->dataRowIdx++;
            $rows[] = $this->makeRow($root->name, '', '', $root);
            return;
        }

        $level1Start = $this->dataRowIdx + 1;

        foreach ($children as $child) {
            $grandchildren = ($childrenMap->get($child->id) ?? collect())->sortBy('sort_order')->values();

            if ($grandchildren->isEmpty()) {
                $this->dataRowIdx++;
                $rows[] = $this->makeRow($root->name, $child->name, '', $child);
            } else {
                $level2Start = $this->dataRowIdx + 1;

                foreach ($grandchildren as $grand) {
                    $this->dataRowIdx++;
                    $rows[] = $this->makeRow($root->name, $child->name, $grand->name, $grand);
                }

                $level2End = $this->dataRowIdx;
                if ($level2End > $level2Start) {
                    $this->mergeRanges['level2'][] = [$level2Start, $level2End];
                }
            }
        }

        $level1End = $this->dataRowIdx;
        if ($level1End > $level1Start) {
            $this->mergeRanges['level1'][] = [$level1Start, $level1End];
        }
    }

    private function makeRow(string $l1, string $l2, string $l3, $platform): array
    {
        return [
            'id'                    => 0,
            'level1'                => $l1,
            'level2'                => $l2,
            'level3'                => $l3,
            'type'                  => ucfirst(str_replace('_', ' ', $platform->type ?? '')),
            'is_active'             => ($platform->is_active ?? false) ? 'Active' : 'Inactive',
            'show_in_analytics'     => ($platform->show_in_analytics ?? true) ? 'Yes' : 'No',
            'show_in_sale_tracking' => ($platform->show_in_sale_tracking ?? true) ? 'Yes' : 'No',
            'sort_order'            => $platform->sort_order ?? 0,
            'created_at'            => $platform->created_at?->format('d M Y'),
            'updated_at'            => $platform->updated_at?->format('d M Y'),
        ];
    }


    public function headings(): array
    {
        $cols   = $this->columns ?: self::allColumns();
        $labels = self::columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }


    public static function allColumns(): array
    {
        return ['id', 'level1', 'level2', 'level3', 'type', 'is_active', 'show_in_analytics', 'show_in_sale_tracking', 'sort_order', 'created_at', 'updated_at'];
    }

    public static function columnLabels(): array
    {
        return [
            'id'                    => 'SL',
            'level1'                => 'Platform',
            'level2'                => 'Sub Platform',
            'level3'                => 'Sub Sub Platform',
            'type'                  => 'Type',
            'is_active'             => 'Status',
            'show_in_analytics'     => 'Show in daily sale & spend report',
            'show_in_sale_tracking' => 'Show in Ads performance',
            'sort_order'            => 'Sort Order',
            'created_at'            => 'Created At',
            'updated_at'            => 'Updated At',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $activeCols = $this->columns ?: self::allColumns();
                $colCount   = count($activeCols);
                $endCol     = Coordinate::stringFromColumnIndex($colCount);

                $this->applyHeaderRows($sheet, $endCol, 'SALE PLATFORMS');
                $this->applyHeadingStyle($sheet, $endCol);
                $this->applyDataStyle($sheet, $endCol, $activeCols);
                $this->applyHierarchicalMerges($sheet, $activeCols);
            },
        ];
    }


    private function applyHierarchicalMerges($sheet, array $activeCols): void
    {
        $colIndexMap = array_flip($activeCols);
        $HEADER_OFFSET = 6;

        foreach (['level1', 'level2'] as $key) {
            if (!isset($colIndexMap[$key])) {
                continue;
            }
            if (empty($this->mergeRanges[$key])) {
                continue;
            }

            $excelCol = Coordinate::stringFromColumnIndex($colIndexMap[$key] + 1);

            foreach ($this->mergeRanges[$key] as [$startData, $endData]) {
                $startRow  = $startData + $HEADER_OFFSET;
                $endRow    = $endData   + $HEADER_OFFSET;
                $mergeRef  = "{$excelCol}{$startRow}:{$excelCol}{$endRow}";

                $sheet->mergeCells($mergeRef);
                $sheet->getStyle($mergeRef)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(false);
            }
        }
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
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
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
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
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
        ]);

        $leftCols = ['level1', 'level2', 'level3'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $leftCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(false);
            }
        }

        $sheet->freezePane('A7');
    }
}

