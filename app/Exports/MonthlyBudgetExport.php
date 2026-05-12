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

class MonthlyBudgetExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private int   $dataRowIdx  = 0;
    private array $mergeRanges = [];
    private array $months;

    public function __construct(
        private Builder $query,
        private array   $columns = []
    ) {
        $this->months = config('constants.months', [
            1 => 'January',  2 => 'February', 3 => 'March',    4 => 'April',
            5 => 'May',      6 => 'June',     7 => 'July',     8 => 'August',
            9 => 'September',10 => 'October', 11 => 'November',12 => 'December',
        ]);
    }

    public function startCell(): string { return 'A6'; }

    // ── Collection ────────────────────────────────────────────────

    public function collection(): Collection
    {
        $this->dataRowIdx  = 0;
        $this->mergeRanges = [];

        // Eager-load full 3-level platform hierarchy
        $records = $this->query
            ->with(['salePlatform.parent.parent'])
            ->get();

        // Sort by platform hierarchy (sort_order at each level) then year DESC, month ASC, id DESC
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
                'id'         => 0,
                'level1'     => $l1,
                'level2'     => $l2,
                'level3'     => $l3,
                'year'       => $record->year,
                'month'      => $this->months[$record->month] ?? $record->month,
                'budget'     => number_format($record->budget, 2),
                'currency'   => $record->currency,
                'notes'      => $record->notes ?? '-',
                'created_at' => $record->created_at?->format('d M Y'),
                'updated_at' => $record->updated_at?->format('d M Y'),
            ];
        }

        // Close final open ranges
        $this->closeMerge('level1', $l1Start, $this->dataRowIdx);
        $this->closeMerge('level2', $l2Start, $this->dataRowIdx);
        $this->closeMerge('level3', $l3Start, $this->dataRowIdx);

        // Assign sequential SL numbers
        foreach ($rows as $i => &$row) { $row['id'] = $i + 1; }
        unset($row);

        $activeCols = $this->columns ?: self::allColumns();
        return collect(array_map(
            fn($row) => array_values(array_intersect_key($row, array_flip($activeCols))),
            $rows
        ));
    }

    // ── Headings ──────────────────────────────────────────────────

    public function headings(): array
    {
        $cols   = $this->columns ?: self::allColumns();
        $labels = self::columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }

    // ── Column definitions ────────────────────────────────────────

    public static function allColumns(): array
    {
        return ['id', 'level1', 'level2', 'level3', 'year', 'month', 'budget', 'currency', 'notes', 'created_at', 'updated_at'];
    }

    public static function columnLabels(): array
    {
        return [
            'id'         => 'SL',
            'level1'     => 'Platform',
            'level2'     => 'Sub Platform',
            'level3'     => 'Sub Sub Platform',
            'year'       => 'Year',
            'month'      => 'Month',
            'budget'     => 'Budget',
            'currency'   => 'Currency',
            'notes'      => 'Notes',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    // ── Events ────────────────────────────────────────────────────

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $activeCols = $this->columns ?: self::allColumns();
                $colCount   = count($activeCols);
                $endCol     = Coordinate::stringFromColumnIndex($colCount);

                $this->applyHeaderRows($sheet, $endCol, 'MONTHLY BUDGETS');
                $this->applyHeadingStyle($sheet, $endCol, $activeCols);
                $this->applyDataStyle($sheet, $endCol, $activeCols);
                $this->applyHierarchicalMerges($sheet, $activeCols);
            },
        ];
    }

    // ── Private helpers ───────────────────────────────────────────

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
        if (!$p) return [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, 0, 0];

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

        // Within same platform: year DESC, month ASC, id DESC
        return [$s0, $s1, $s2, -($record->year ?? 0), $record->month ?? 0, -$record->id];
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
        // Base style: blue background, white bold text, center-aligned
        $sheet->getStyle("A6:{$endCol}6")->applyFromArray([
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(20);

        // Left-align heading cells for long-text columns
        $leftCols = ['level1', 'level2', 'level3', 'notes'];
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

        // Center-align all data rows by default
        $sheet->getStyle("A7:{$endCol}{$highestRow}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Left-align long-text data columns
        $leftCols = ['level1', 'level2', 'level3', 'notes'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $leftCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
            }
        }

        // Sticky heading row
        $sheet->freezePane('A7');
    }
}

