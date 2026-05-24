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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DailyReturnExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell
{
    private int   $dataRowIdx  = 0;
    private array $mergeRanges = [];
    private array $rowBands    = [];   // excel_row => band_index (0-based, per Level-1 group)

    public function __construct(
        private Builder $query,
        private array   $columns = []
    ) {}

    public function startCell(): string { return 'A6'; }

    public function collection(): Collection
    {
        $this->dataRowIdx  = 0;
        $this->mergeRanges = [];
        $this->rowBands    = [];

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

        // Track which Excel row belongs to which Level-1 platform group
        // (used later in AfterSheet for alternating band colours)
        $prevL1  = null;
        $bandIdx = -1;
        foreach ($rows as $i => $row) {
            if ($row['level1'] !== $prevL1) {
                $bandIdx++;
                $prevL1 = $row['level1'];
            }
            $this->rowBands[$i + 7] = $bandIdx;   // Excel row = 0-based index + 7
        }

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
        $rows = [
            [config('app.name', 'ENOX ERP'),                     'FF005C3E', 'FFFFFFFF', 18, 34],
            [$title,                                              'FF009966', 'FFFFFFFF', 13, 26],
            ['Generated: ' . now()->format('d M Y H:i'),         'FFB2DFDB', 'FF003D2B', 11, 20],
        ];

        foreach ($rows as $i => [$text, $bg, $fg, $size, $height]) {
            $row = $i + 1;
            $sheet->setCellValue("A{$row}", $text);
            $sheet->mergeCells("A{$row}:{$endCol}{$row}");
            $sheet->getStyle("A{$row}:{$endCol}{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'font'      => ['bold' => true, 'size' => $size, 'color' => ['argb' => $fg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    private function applyHeadingStyle($sheet, string $endCol, array $activeCols): void
    {
        // Each column group gets a distinct background colour so the user can
        // instantly spot which columns relate to which metric category.
        $headingColors = [
            'id'                                  => 'FF546E7A',   // blue-gray  (SL)
            'level1'                              => 'FF263238',   // dark slate (Platform)
            'level2'                              => 'FF37474F',   // slate      (Sub Platform)
            'level3'                              => 'FF455A64',   // mid-slate  (Sub Sub Platform)
            'reason'                              => 'FF6A1B9A',   // deep purple
            'date'                                => 'FF00695C',   // dark teal
            'return_amount'                       => 'FFBF360C',   // deep orange
            'number_of_returns'                   => 'FF283593',   // deep indigo
            'number_of_return_quantities'         => 'FF283593',   // deep indigo
            'number_of_male_returns'              => 'FF0D47A1',   // deep blue
            'number_of_male_return_quantities'    => 'FF0D47A1',   // deep blue
            'number_of_female_returns'            => 'FF880E4F',   // deep pink
            'number_of_female_return_quantities'  => 'FF880E4F',   // deep pink
            'number_of_kids_returns'              => 'FF1B5E20',   // deep green
            'number_of_kids_return_quantities'    => 'FF1B5E20',   // deep green
            'created_at'                          => 'FF607D8B',   // gray-blue  (audit)
            'updated_at'                          => 'FF607D8B',   // gray-blue  (audit)
        ];

        $leftCols = ['level1', 'level2', 'level3', 'reason'];

        foreach ($activeCols as $idx => $colKey) {
            $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
            $bg       = $headingColors[$colKey] ?? 'FF009966';
            $halign   = in_array($colKey, $leftCols)
                ? Alignment::HORIZONTAL_LEFT
                : Alignment::HORIZONTAL_CENTER;

            $sheet->getStyle("{$excelCol}6")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => [
                    'horizontal' => $halign,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ]);
        }

        $sheet->getRowDimension(6)->setRowHeight(22);
    }

    private function applyDataStyle($sheet, string $endCol, array $activeCols): void
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 7) {
            $sheet->freezePane('A7');
            return;
        }

        // ── Colour palettes ──────────────────────────────────────────────────

        // Alternating soft background colours – one shade per Level-1 platform group.
        // Keeps groups visually separated without being distracting.
        $bandColors = [
            'FFFFFFFF',   // white          (group 1)
            'FFF0F7FF',   // light periwinkle (group 2)
            'FFF0FFF5',   // light mint     (group 3)
            'FFFDF6EC',   // light cream    (group 4)
            'FFF8F0FF',   // light lavender (group 5)
            'FFEFF9FD',   // light cyan     (group 6 … cycles)
        ];

        // Fixed column tints for metric groups – always visible regardless of band.
        $colTints = [
            'return_amount'                      => 'FFFFF3E0',   // amber   – stands out
            'number_of_returns'                  => 'FFEDE7F6',   // indigo  – totals
            'number_of_return_quantities'        => 'FFEDE7F6',
            'number_of_male_returns'             => 'FFE3F2FD',   // blue    – male
            'number_of_male_return_quantities'   => 'FFE3F2FD',
            'number_of_female_returns'           => 'FFFCE4EC',   // pink    – female
            'number_of_female_return_quantities' => 'FFFCE4EC',
            'number_of_kids_returns'             => 'FFF1F8E9',   // green   – kids
            'number_of_kids_return_quantities'   => 'FFF1F8E9',
        ];

        // ── Build consecutive row-ranges per band (efficient: no cell-by-cell loop) ─
        $bandRanges  = [];
        $prevBandIdx = null;
        $rangeStart  = null;

        for ($row = 7; $row <= $highestRow; $row++) {
            $bi = ($this->rowBands[$row] ?? 0) % count($bandColors);
            if ($bi !== $prevBandIdx) {
                if ($prevBandIdx !== null) {
                    $bandRanges[] = ['band' => $prevBandIdx, 'start' => $rangeStart, 'end' => $row - 1];
                }
                $rangeStart  = $row;
                $prevBandIdx = $bi;
            }
        }
        if ($prevBandIdx !== null) {
            $bandRanges[] = ['band' => $prevBandIdx, 'start' => $rangeStart, 'end' => $highestRow];
        }

        // ── Apply colours column by column ───────────────────────────────────
        foreach ($activeCols as $idx => $colKey) {
            $excelCol = Coordinate::stringFromColumnIndex($idx + 1);

            if (isset($colTints[$colKey])) {
                // Metric columns: fixed tint colour for the whole column
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colTints[$colKey]);
            } else {
                // Platform / text columns: apply the platform-group band colour
                foreach ($bandRanges as $r) {
                    $sheet->getStyle("{$excelCol}{$r['start']}:{$excelCol}{$r['end']}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($bandColors[$r['band']]);
                }
            }
        }

        // ── Alignment ────────────────────────────────────────────────────────
        $sheet->getStyle("A7:{$endCol}{$highestRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $leftCols = ['level1', 'level2', 'level3', 'reason'];
        foreach ($activeCols as $idx => $colKey) {
            if (in_array($colKey, $leftCols)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(false);
            }
        }

        // ── Thin border on entire table (heading row + data rows) ─────────────
        $sheet->getStyle("A6:{$endCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFBDBDBD'],
                ],
            ],
        ]);

        $sheet->freezePane('A7');
    }
}
