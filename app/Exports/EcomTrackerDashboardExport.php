<?php

namespace App\Exports;

use App\Services\EcomTrackerDashboardService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EcomTrackerDashboardExport implements WithMultipleSheets
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $rows
     */
    public function __construct(
        private array $rows,
        private string $rangeLabel = '',
    ) {}

    public static function fromFilters(EcomTrackerDashboardService $service, array $filters): self
    {
        $range = $service->resolveDateRange($filters);

        return new self(
            $service->buildExportRows($filters),
            $range['label'],
        );
    }

    public function sheets(): array
    {
        $rangeLabel = $this->rangeLabel;

        return [
            new EcomTrackerDashboardSheetExport('KPIs', $this->rows['kpis'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Funnel', $this->rows['funnel'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Trend', $this->rows['trend'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Categories', $this->rows['categories'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Products', $this->rows['products'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Variants', $this->rows['variants'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Traffic Sources', $this->rows['traffic_sources'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Geography', $this->rows['geography'] ?? [], $rangeLabel),
            new EcomTrackerDashboardSheetExport('Devices', $this->rows['devices'] ?? [], $rangeLabel),
        ];
    }
}
