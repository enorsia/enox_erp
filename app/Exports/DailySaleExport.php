<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Facades\Log;

class DailySaleExport implements WithMultipleSheets
{
    public function __construct(
        private Builder $query,
        private array   $columns = []
    ) {}

    public function sheets(): array
    {
        Log::info('DailySaleExport: sheets() started', [
            'columns' => $this->columns ?: self::allColumns(),
        ]);

        try {
            $records = $this->query
                ->with(['salePlatform.parent.parent'])
                ->get();

            // Group records by year-month, sorted chronologically
            $grouped = $records
                ->groupBy(fn($r) => $r->date?->format('Y-m') ?? 'Unknown')
                ->sortKeys();

            $sheets = [];
            foreach ($grouped as $yearMonth => $monthRecords) {
                $title = ($yearMonth !== 'Unknown')
                    ? Carbon::createFromFormat('Y-m', $yearMonth)->format('M-Y')
                    : 'Unknown';

                $sheets[] = new DailySaleMonthSheet($monthRecords, $this->columns, $title);
            }

            // Fallback: at least one empty sheet when there is no data
            if (empty($sheets)) {
                $sheets[] = new DailySaleMonthSheet(collect(), $this->columns, 'No Data');
            }

            Log::info('DailySaleExport: sheets() completed successfully', [
                'sheet_count' => count($sheets),
            ]);

            return $sheets;

        } catch (\Throwable $e) {
            Log::error('DailySaleExport: sheets() failed', [
                'error'   => $e->getMessage(),
                'class'   => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'columns' => $this->columns ?: self::allColumns(),
            ]);
            throw $e;
        }
    }

    public static function allColumns(): array
    {
        return [
            'id', 'level1', 'level2', 'level3', 'date', 'spent', 'sales',
            'number_of_orders', 'number_of_quantities',
            'number_of_male_orders', 'number_of_female_orders', 'number_of_kids_orders',
            'number_of_male_quantities', 'number_of_female_quantities', 'number_of_kids_quantities',
            'created_at', 'updated_at',
        ];
    }

    public static function columnLabels(): array
    {
        return [
            'id'                          => 'SL',
            'level1'                      => 'Platform',
            'level2'                      => 'Sub Platform',
            'level3'                      => 'Sub Sub Platform',
            'date'                        => 'Date',
            'spent'                       => 'Spent (£)',
            'sales'                       => 'Sales (£)',
            'number_of_orders'            => 'Total Orders',
            'number_of_quantities'        => 'Total Qty',
            'number_of_male_orders'       => 'Male Orders',
            'number_of_female_orders'     => 'Female Orders',
            'number_of_kids_orders'       => 'Kids Orders',
            'number_of_male_quantities'   => 'Male Qty',
            'number_of_female_quantities' => 'Female Qty',
            'number_of_kids_quantities'   => 'Kids Qty',
            'created_at'                  => 'Created At',
            'updated_at'                  => 'Updated At',
        ];
    }
}
