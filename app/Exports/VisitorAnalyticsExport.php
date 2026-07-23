<?php

namespace App\Exports;

use App\Services\VisitorAnalyticsService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VisitorAnalyticsExport implements WithMultipleSheets
{
    private const REPORT_PREFIX = 'VISITOR ANALYTICS';

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $rows
     */
    public function __construct(
        private array $rows,
        private string $rangeLabel = '',
    ) {}

    public static function fromRange(
        VisitorAnalyticsService $analytics,
        Carbon $from,
        ?Carbon $until,
        string $rangeLabel,
    ): self {
        return new self(
            $analytics->buildExportRows($from, $until),
            $rangeLabel,
        );
    }

    public function sheets(): array
    {
        $rangeLabel = $this->rangeLabel;

        return [
            new EcomTrackerDashboardSheetExport('KPIs', $this->rows['kpis'] ?? [], $rangeLabel, self::REPORT_PREFIX),
            new EcomTrackerDashboardSheetExport('Trend', $this->rows['trend'] ?? [], $rangeLabel, self::REPORT_PREFIX),
            new EcomTrackerDashboardSheetExport('New vs Returning', $this->rows['new_returning'] ?? [], $rangeLabel, self::REPORT_PREFIX),
            new EcomTrackerDashboardSheetExport('Duration', $this->rows['duration'] ?? [], $rangeLabel, self::REPORT_PREFIX),
            new EcomTrackerDashboardSheetExport('Visitors', $this->rows['visitors'] ?? [], $rangeLabel, self::REPORT_PREFIX),
        ];
    }
}
