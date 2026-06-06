<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// =============================================================================
// DailyReturnExport  –  entry point, splits records into per-month sheets
// =============================================================================

class DailyReturnExport implements WithMultipleSheets
{
    // -------------------------------------------------------------------------
    // Column definitions (shared with MonthSheet)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Constructor & sheets()
    // -------------------------------------------------------------------------

    public function __construct(
        private Builder $query,
        private array   $columns = []
    ) {}

    public function sheets(): array
    {
        $activeCols = $this->columns ?: self::allColumns();

        Log::info('DailyReturnExport: sheets() started', ['columns' => $activeCols]);

        try {
            // Fix N+1: eager-load all nested relationships in one query set
            $records = $this->query
                ->with([
                    'returnReasonType',
                    'salePlatform',
                    'salePlatform.parent',
                    'salePlatform.parent.parent',
                ])
                ->get();

            // Group by year-month, sorted chronologically
            $grouped = $records
                ->groupBy(fn ($r) => $r->date?->format('Y-m') ?? 'Unknown')
                ->sortKeys();

            $sheets = [];

            foreach ($grouped as $yearMonth => $monthRecords) {
                $title = $yearMonth !== 'Unknown'
                    ? Carbon::createFromFormat('Y-m', $yearMonth)->format('M-Y')
                    : 'Unknown';

                $sheets[] = new DailyReturnMonthSheet($monthRecords, $activeCols, $title);
            }

            // Fallback: at least one empty sheet when there is no data
            if (empty($sheets)) {
                $sheets[] = new DailyReturnMonthSheet(collect(), $activeCols, 'No Data');
            }

            Log::info('DailyReturnExport: sheets() completed', ['sheet_count' => count($sheets)]);

            return $sheets;

        } catch (\Throwable $e) {
            Log::error('DailyReturnExport: sheets() failed', [
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'columns' => $activeCols,
            ]);

            throw $e;
        }
    }
}

// =============================================================================
// DailyReturnMonthSheet  –  renders one Excel sheet for a single month
// =============================================================================

class DailyReturnMonthSheet implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithCustomStartCell, WithTitle
{
    // Style constants
    private const HEADER_OFFSET   = 6;
    private const START_CELL      = 'A6';
    private const COLOR_GREEN     = 'FF009966';
    private const COLOR_WHITE     = 'FFFFFFFF';
    private const COLOR_BORDER    = 'FFB0B0B0';
    private const LEFT_ALIGN_COLS = ['level1', 'level2', 'level3', 'reason'];

    private int   $dataRowIdx  = 0;
    private array $mergeRanges = [];

    public function __construct(
        private Collection $records,
        private array      $columns = [],
        private string     $sheetTitle = 'Sheet'
    ) {}

    // -------------------------------------------------------------------------
    // WithTitle
    // -------------------------------------------------------------------------

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    // -------------------------------------------------------------------------
    // WithCustomStartCell
    // -------------------------------------------------------------------------

    public function startCell(): string
    {
        return self::START_CELL;
    }

    // -------------------------------------------------------------------------
    // WithHeadings
    // -------------------------------------------------------------------------

    public function headings(): array
    {
        $labels = DailyReturnExport::columnLabels();

        return array_values(
            array_intersect_key($labels, array_flip($this->columns))
        );
    }

    // -------------------------------------------------------------------------
    // FromCollection
    // -------------------------------------------------------------------------

    public function collection(): Collection
    {
        Log::info('DailyReturnMonthSheet: collection() started', [
            'sheet'   => $this->sheetTitle,
            'columns' => $this->columns,
        ]);

        try {
            $this->dataRowIdx  = 0;
            $this->mergeRanges = [];

            $sorted = $this->records->sort(
                fn ($a, $b) => $this->compareSortKeys(
                    $this->buildSortKey($a),
                    $this->buildSortKey($b)
                )
            )->values();

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
                    $l1Start   = $l2Start = $l3Start = $this->dataRowIdx;
                    $prevL1    = $l1;
                    $prevL2Key = $l2Key;
                    $prevL3Key = $l3Key;
                } elseif ($l2Key !== $prevL2Key) {
                    $this->closeMerge('level2', $l2Start, $this->dataRowIdx - 1);
                    $this->closeMerge('level3', $l3Start, $this->dataRowIdx - 1);
                    $l2Start   = $l3Start = $this->dataRowIdx;
                    $prevL2Key = $l2Key;
                    $prevL3Key = $l3Key;
                } elseif ($l3Key !== $prevL3Key) {
                    $this->closeMerge('level3', $l3Start, $this->dataRowIdx - 1);
                    $l3Start   = $this->dataRowIdx;
                    $prevL3Key = $l3Key;
                }

                $rows[] = [
                    'id'                                 => 0,   // filled in after loop
                    'level1'                             => $l1,
                    'level2'                             => $l2,
                    'level3'                             => $l3,
                    'reason'                             => $record->returnReasonType?->name ?? '-',
                    'date'                               => $record->date?->format('d M Y'),
                    'return_amount'                      => (float) ($record->return_amount ?? 0),
                    'number_of_returns'                  => (int)   ($record->number_of_returns ?? 0),
                    'number_of_return_quantities'        => (int)   ($record->number_of_return_quantities ?? 0),
                    'number_of_male_returns'             => (int)   ($record->number_of_male_returns ?? 0),
                    'number_of_female_returns'           => (int)   ($record->number_of_female_returns ?? 0),
                    'number_of_kids_returns'             => (int)   ($record->number_of_kids_returns ?? 0),
                    'number_of_male_return_quantities'   => (int)   ($record->number_of_male_return_quantities ?? 0),
                    'number_of_female_return_quantities' => (int)   ($record->number_of_female_return_quantities ?? 0),
                    'number_of_kids_return_quantities'   => (int)   ($record->number_of_kids_return_quantities ?? 0),
                    'created_at'                         => $record->created_at?->format('d M Y'),
                    'updated_at'                         => $record->updated_at?->format('d M Y'),
                ];
            }

            // Close any still-open merge groups
            $this->closeMerge('level1', $l1Start, $this->dataRowIdx);
            $this->closeMerge('level2', $l2Start, $this->dataRowIdx);
            $this->closeMerge('level3', $l3Start, $this->dataRowIdx);

            // Assign sequential SL numbers
            foreach ($rows as $i => &$row) {
                $row['id'] = $i + 1;
            }
            unset($row);

            // Filter to only the requested columns, preserving order
            $result = collect(array_map(
                fn ($row) => array_values(array_intersect_key($row, array_flip($this->columns))),
                $rows
            ));

            Log::info('DailyReturnMonthSheet: collection() completed', [
                'sheet'        => $this->sheetTitle,
                'record_count' => count($rows),
                'columns'      => $this->columns,
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('DailyReturnMonthSheet: collection() failed', [
                'sheet'   => $this->sheetTitle,
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'columns' => $this->columns,
            ]);

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // WithEvents
    // -------------------------------------------------------------------------

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                Log::info('DailyReturnMonthSheet: AfterSheet started', ['sheet' => $this->sheetTitle]);

                try {
                    $sheet    = $event->sheet->getDelegate();
                    $colCount = count($this->columns);
                    $endCol   = Coordinate::stringFromColumnIndex($colCount);

                    $this->applyHeaderRows($sheet, $endCol, 'DAILY RETURNS');
                    $this->applyHeadingStyle($sheet, $endCol);
                    $this->applyDataStyle($sheet, $endCol);
                    $this->applyHierarchicalMerges($sheet);

                    Log::info('DailyReturnMonthSheet: AfterSheet completed', ['sheet' => $this->sheetTitle]);

                } catch (\Throwable $e) {
                    Log::error('DailyReturnMonthSheet: AfterSheet failed', [
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

    // -------------------------------------------------------------------------
    // Platform helpers
    // -------------------------------------------------------------------------

    private function resolvePlatformLevels($platform): array
    {
        if (! $platform) {
            return ['', '', ''];
        }

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

        if (! $p) {
            return [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 0, 0];
        }

        $s0 = $s1 = $s2 = 0;

        if ($p->parent_id && $p->parent) {
            $parent = $p->parent;

            if ($parent->parent_id && $parent->parent) {
                $s0 = $parent->parent->sort_order ?? 0;
                $s1 = $parent->sort_order         ?? 0;
                $s2 = $p->sort_order              ?? 0;
            } else {
                $s0 = $parent->sort_order ?? 0;
                $s1 = $p->sort_order      ?? 0;
            }
        } else {
            $s0 = $p->sort_order ?? 0;
        }

        $ts = $record->date?->timestamp ?? 0;

        return [$s0, $s1, $s2, -$ts, -$record->id];
    }

    private function compareSortKeys(array $a, array $b): int
    {
        foreach ($a as $i => $v) {
            if ($v !== $b[$i]) {
                return $v <=> $b[$i];
            }
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Merge tracking
    // -------------------------------------------------------------------------

    private function closeMerge(string $key, ?int $start, int $end): void
    {
        if ($start !== null && $end > $start) {
            $this->mergeRanges[$key][] = [$start, $end];
        }
    }

    // -------------------------------------------------------------------------
    // Styling helpers
    // -------------------------------------------------------------------------

    private function applyHeaderRows($sheet, string $endCol, string $title): void
    {
        $appName = config('app.name', 'ENOX ERP');

        $metaRows = [
            1 => $appName,
            2 => $title,
            3 => 'Generated: ' . now()->format('d M Y H:i'),
        ];

        foreach ($metaRows as $row => $text) {
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
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_GREEN],
            ],
            'font'      => [
                'bold'  => true,
                'color' => ['argb' => self::COLOR_WHITE],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(6)->setRowHeight(20);

        foreach ($this->columns as $idx => $colKey) {
            if (in_array($colKey, self::LEFT_ALIGN_COLS, true)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}6")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }
        }
    }

    private function applyDataStyle($sheet, string $endCol): void
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->freezePane('A7');

        if ($highestRow < 7) {
            return;
        }

        $sheet->getStyle("A7:{$endCol}{$highestRow}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders'   => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => self::COLOR_BORDER],
                ],
            ],
        ]);

        // Outer border spanning heading + data block
        $sheet->getStyle("A6:{$endCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => self::COLOR_GREEN],
                ],
            ],
        ]);

        foreach ($this->columns as $idx => $colKey) {
            if (in_array($colKey, self::LEFT_ALIGN_COLS, true)) {
                $excelCol = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$excelCol}7:{$excelCol}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(false);
            }
        }
    }

    private function applyHierarchicalMerges($sheet): void
    {
        $colIndexMap = array_flip($this->columns);

        foreach (['level1', 'level2', 'level3'] as $key) {
            if (! isset($colIndexMap[$key]) || empty($this->mergeRanges[$key])) {
                continue;
            }

            $excelCol = Coordinate::stringFromColumnIndex($colIndexMap[$key] + 1);

            foreach ($this->mergeRanges[$key] as [$startData, $endData]) {
                $startRow = $startData + self::HEADER_OFFSET;
                $endRow   = $endData   + self::HEADER_OFFSET;
                $ref      = "{$excelCol}{$startRow}:{$excelCol}{$endRow}";

                $sheet->mergeCells($ref);
                $sheet->getStyle($ref)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(false);
            }
        }
    }
}