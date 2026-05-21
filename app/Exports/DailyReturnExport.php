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

class DailyReturnExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private int   $dataRowIdx  = 0;
    private array $mergeRanges = [];

    public function __construct(
        private Builder $query,
        private array   $columns = []
    ) {}

    public function startCell(): string { return 'A6'; }

    public function collection(): Collection
    {
        $this->dataRowIdx  = 0;
        $this->mergeRanges = [];

        $records = $this->query
            ->with(['salePlatform.parent.parent', 'returnReasonType'])
            ->get();

        $sorted = $records->sort(function ($a, $b) {
            $ka = $this->buildSortKey($a);
            $kb = $this->buildSortKey($b);
            foreach ($ka as $i => $v) {
                if ($v !== $kb[$i]) return $v <=> $kb[$i];
            }
            return 0;
        })->values();

        $rows      = [];
        $prevL1    = null;
        $prevL2Key = null;
        $prevL3Key = null;
        $l1Start   = $l2Start = $l3Start = null;

        foreach ($sorted as $record) {
            [$l1, $l2, $l3] = $this->resolvePlatformLevels($record->salePlatform);
            $this->dataRowIdx++;

            $l2Key = "{$l1}|{$l2}";
            $l3Key = "{$l1}|{$l2}|{$l3}";

            if ($l1 !== $prevL1) {
                $this->closeMerge('level1', $l1Start, $this->dataRowIdx - 1);
                $this->closeMerge('level2', $l2Start, $this->dataRowIdx - 1);
                $this->closeMerge('level3', $l3Start, $this->dataRowIdx - 1);
                $l1Start = $l2Start = $l3Start = $this->dataRowIdx;
                $prevL1 = $l1; $prevL2Key = $l2Key; $prevL3Key = $l3Key;
            } elseif ($l2Key !== $prevL2Key) {
                $this->closeMerge('level2', $l2Start, $this->dataRowIdx - 1);
                $this->closeMerge('level3', $l3Start, $this->dataRowIdx - 1);
                $l2Start = $l3Start = $this->dataRowIdx;
                $prevL2Key = $l2Key; $prevL3Key = $l3Key;
            } elseif ($l3Key !== $prevL3Key) {
                $this->closeMerge('level3', $l3Start, $this->dataRowIdx - 1);
                $l3Start   = $this->dataRowIdx;
                $prevL3Key = $l3Key;
            }

            $rows[] = [
                'id'                                 => 0,
                'level1'                             => $l1,
                'level2'                             => $l2,
                'level3'                             => $l3,
                'reason'                             => $record->returnReasonType?->name ?? '-',
                'date'                               => $record->date?->format('d M Y'),
                'return_amount'                      => $record->return_amount,
                'number_of_returns'                  => $record->number_of_returns,
                'number_of_return_quantities'        => $record->number_of_return_quantities,
                'number_of_male_returns'             => $record->number_of_male_returns,
                'number_of_female_returns'           => $record->number_of_female_returns,
                'number_of_kids_returns'             => $record->number_of_kids_returns,
                'number_of_male_return_quantities'   => $record->number_of_male_return_quantities,
                'number_of_female_return_quantities' => $record->number_of_female_return_quantities,
                'number_of_kids_return_quantities'   => $record->number_of_kids_return_quantities,
                'created_at'                         => $record->created_at?->format('d M Y'),
                'updated_at'                         => $record->updated_at?->format('d M Y'),
            ];
        }

        $this->closeMerge('level1', $l1Start, $this->dataRowIdx);
        $this->closeMerge('level2', $l2Start, $this->dataRowIdx);
        $this->closeMerge('level3', $l3Start, $this->dataRowIdx);

        foreach ($rows as $i => &$row) { $row['id'] = $i + 1; }
        unset($row);

        $activeCols = $this->columns ?: self::allColumns();
        return collect(array_map(
            fn($row) => array_values(array_intersect_key($row, array_flip($activeCols))),
            $rows
        ));
    }

    public function headings(): array
    {
        $cols   = $this->columns ?: self::allColumns();
        $labels = self::columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }

    public static function allColumns(): array
    {
        return [
            'id', 'level1', 'level2', 'level3', 'reason', 'date',
            'return_amount',
            'number_of_returns', 'number_of_return_quantities',
            'number_of_male_returns', 'number_of_female_returns', 'number_of_kids_returns',
            'number_of_male_return_quantities', 'number_of_female_return_quantities', 'number_of_kids_return_quantities',
            'created_at', 'updated_at',
        ];
    }

    public static function columnLabels(): array
    {
        return [
            'id'                                  => 'SL',
            'level1'                              => 'Platform',
            'level2'                              => 'Sub Platform',
            'level3'                              => 'Sub Sub Platform',
            'reason'                              => 'Return Reason',
            'date'                                => 'Date',
            'return_amount'                       => 'Return Amount',
            'number_of_returns'                   => 'Total Returns',
            'number_of_return_quantities'         => 'Total Return Qty',
            'number_of_male_returns'              => 'Male Returns',
            'number_of_female_returns'            => 'Female Returns',
            'number_of_kids_returns'              => 'Kids Returns',
            'number_of_male_return_quantities'    => 'Male Return Qty',
            'number_of_female_return_quantities'  => 'Female Return Qty',
            'number_of_kids_return_quantities'    => 'Kids Return Qty',
            'created_at'                          => 'Created At',
            'updated_at'                          => 'Updated At',
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

                $this->applyHeaderRows($sheet, $endCol, 'DAILY RETURNS');
                $this->applyHeadingStyle($sheet, $endCol, $activeCols);
                $this->applyDataStyle($sheet, $endCol, $activeCols);
                $this->applyHierarchicalMerges($sheet, $activeCols);
            },
        ];
    }

    private function resolvePlatformLevels($platform): array
    {
        if (!$platform) return ['', '', ''];
        if ($platform->parent_id && $platform->parent) {
            $parent = $platform->parent;
            if ($parent->parent_id && $parent->parent) {
                return [$parent->parent->name, $parent->name, $platform->name];
            }
            return [$parent->name, $platform->name, ''];
        }
        return [$platform->name, '', ''];
    }

    private function buildSortKey($record): array
    {
        $p = $record->salePlatform;
        if (!$p) return [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, 0];

        $s0 = $s1 = $s2 = 0;
        if ($p->parent_id && $p->parent) {
            $parent = $p->parent;
            if ($parent->parent_id && $parent->parent) {
                $s0 = $parent->parent->sort_order ?? 0;
                $s1 = $parent->sort_order ?? 0;
                $s2 = $p->sort_order ?? 0;
            } else {
                $s0 = $parent->sort_order ?? 0;
                $s1 = $p->sort_order ?? 0;
            }
        } else {
            $s0 = $p->sort_order ?? 0;
        }

        $ts = $record->date ? $record->date->timestamp : 0;
        return [$s0, $s1, $s2, -$ts, -$record->id];
    }

    private function closeMerge(string $key, ?int $start, int $end): void
    {
        if ($start !== null && $end > $start) {
            $this->mergeRanges[$key][] = [$start, $end];
        }
    }

    private function applyHierarchicalMerges($sheet, array $activeCols): void
    {
        $colIndexMap   = array_flip($activeCols);
        $HEADER_OFFSET = 6;

        foreach (['level1', 'level2', 'level3'] as $key) {
            if (!isset($colIndexMap[$key]) || empty($this->mergeRanges[$key])) continue;

            $excelCol = Coordinate::stringFromColumnIndex($colIndexMap[$key] + 1);

            foreach ($this->mergeRanges[$key] as [$startData, $endData]) {
                $startRow = $startData + $HEADER_OFFSET;
                $endRow   = $endData   + $HEADER_OFFSET;
                $ref      = "{$excelCol}{$startRow}:{$excelCol}{$endRow}";

                $sheet->mergeCells($ref);
                $sheet->getStyle($ref)->getAlignment()
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

    private function applyHeadingStyle($sheet, string $endCol, array $activeCols): void
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

        $leftCols = ['level1', 'level2', 'level3', 'reason'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $leftCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }
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

        $leftCols = ['level1', 'level2', 'level3', 'reason'];
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

