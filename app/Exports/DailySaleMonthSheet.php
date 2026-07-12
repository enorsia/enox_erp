<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

/**
 * A single monthly sheet used by DailySaleExport (WithMultipleSheets).
 * All logic is identical to the original DailySaleExport – only the data
 * source changes from a Builder to a pre-fetched Collection.
 */
class DailySaleMonthSheet implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell, WithTitle
{
    private int   $dataRowIdx  = 0;
    private array $mergeRanges = [];

    public function __construct(
        private Collection $records,
        private array      $columns = [],
        private string     $sheetTitle = 'Sheet'
    ) {}

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function startCell(): string { return 'A6'; }

    public function collection(): Collection
    {
        Log::info('DailySaleMonthSheet: collection() started', [
            'sheet'   => $this->sheetTitle,
            'columns' => $this->columns ?: DailySaleExport::allColumns(),
        ]);

        try {
            $this->dataRowIdx  = 0;
            $this->mergeRanges = [];

            $sorted = $this->records->sort(function ($a, $b) {
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
                    'id'                           => 0,
                    'level1'                       => $l1,
                    'level2'                       => $l2,
                    'level3'                       => $l3,
                    'date'                         => $record->date?->format('d M Y'),
                    'spent'                        => (float) ($record->spent ?? 0),
                    'sales'                        => (float) ($record->sales ?? 0),
                    'number_of_orders'             => (int)   ($record->number_of_orders ?? 0),
                    'number_of_quantities'         => (int)   ($record->number_of_quantities ?? 0),
                    'number_of_male_orders'        => (int)   ($record->number_of_male_orders ?? 0),
                    'number_of_female_orders'      => (int)   ($record->number_of_female_orders ?? 0),
                    'number_of_kids_orders'        => (int)   ($record->number_of_kids_orders ?? 0),
                    'number_of_male_quantities'    => (int)   ($record->number_of_male_quantities ?? 0),
                    'number_of_female_quantities'  => (int)   ($record->number_of_female_quantities ?? 0),
                    'number_of_kids_quantities'    => (int)   ($record->number_of_kids_quantities ?? 0),
                    'created_at'                   => $record->created_at?->format('d M Y'),
                    'updated_at'                   => $record->updated_at?->format('d M Y'),
                ];
            }

            $this->closeMerge('level1', $l1Start, $this->dataRowIdx);
            $this->closeMerge('level2', $l2Start, $this->dataRowIdx);
            $this->closeMerge('level3', $l3Start, $this->dataRowIdx);

            foreach ($rows as $i => &$row) { $row['id'] = $i + 1; }
            unset($row);

            $activeCols = $this->columns ?: DailySaleExport::allColumns();
            $result = collect(array_map(
                fn($row) => array_values(array_intersect_key($row, array_flip($activeCols))),
                $rows
            ));

            Log::info('DailySaleMonthSheet: collection() completed successfully', [
                'sheet'        => $this->sheetTitle,
                'record_count' => count($rows),
                'columns'      => $activeCols,
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('DailySaleMonthSheet: collection() failed', [
                'sheet'   => $this->sheetTitle,
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'columns' => $this->columns ?: DailySaleExport::allColumns(),
            ]);
            throw $e;
        }
    }

    public function headings(): array
    {
        $cols   = $this->columns ?: DailySaleExport::allColumns();
        $labels = DailySaleExport::columnLabels();
        return array_values(array_intersect_key($labels, array_flip($cols)));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                Log::info('DailySaleMonthSheet: AfterSheet styling started', ['sheet' => $this->sheetTitle]);
                try {
                    $sheet      = $event->sheet->getDelegate();
                    $activeCols = $this->columns ?: DailySaleExport::allColumns();
                    $colCount   = count($activeCols);
                    $endCol     = Coordinate::stringFromColumnIndex($colCount);

                    $this->applyHeaderRows($sheet, $endCol, 'DAILY SALES');
                    $this->applyHeadingStyle($sheet, $endCol, $activeCols);
                    $this->applyDataStyle($sheet, $endCol, $activeCols);
                    $this->applyHierarchicalMerges($sheet, $activeCols);

                    Log::info('DailySaleMonthSheet: AfterSheet styling completed successfully', ['sheet' => $this->sheetTitle]);
                } catch (\Throwable $e) {
                    Log::error('DailySaleMonthSheet: AfterSheet styling failed', [
                        'sheet' => $this->sheetTitle,
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
    }

    private function applyHeadingStyle($sheet, string $endCol, array $activeCols): void
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

        $leftCols = ['level1', 'level2', 'level3'];
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
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFB0B0B0'],
                ],
            ],
        ]);

        // Outer border on heading + data block
        $sheet->getStyle("A6:{$endCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF009966'],
                ],
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

        // Apply money number format for currency columns
        $moneyCols = ['spent', 'sales'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $moneyCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $sheet->freezePane('A7');
    }
}

