<?php

namespace App\Services\EcomTrackerDashboard;

use App\Services\EcomTrackerDashboard\Sections\CategoryPerformanceSectionService;
use App\Services\EcomTrackerDashboard\Sections\DashboardKpiSectionService;
use App\Services\EcomTrackerDashboard\Sections\DeviceBrowserSectionService;
use App\Services\EcomTrackerDashboard\Sections\ProductPerformanceSectionService;
use App\Services\EcomTrackerDashboard\Sections\RecoverableSaleSectionService;
use App\Services\EcomTrackerDashboard\Sections\SessionDurationSectionService;
use App\Services\EcomTrackerDashboard\Sections\ShopperJourneyTrendSectionService;
use App\Services\EcomTrackerDashboard\Sections\TrafficSourcesSectionService;
use App\Services\EcomTrackerDashboard\Sections\UniqueReturningSectionService;
use App\Support\TrackerDatabase;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Store performance dashboard, read only from tracking_* tables (daily rollups filled by dashboard sync).
 */
class EcomTrackerDashboardService
{
    public function __construct(
        private DashboardKpiSectionService $kpiSection,
        private ShopperJourneyTrendSectionService $shopperJourneySection,
        private CategoryPerformanceSectionService $categorySection,
        private ProductPerformanceSectionService $productSection,
        private RecoverableSaleSectionService $recoverableSection,
        private DeviceBrowserSectionService $deviceBrowserSection,
        private TrafficSourcesSectionService $trafficSourcesSection,
        private SessionDurationSectionService $sessionDurationSection,
        private UniqueReturningSectionService $uniqueReturningSection,
    ) {}

    /**
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range  local calendar days
     * @param  string  $backUrl  dashboard URL the session pages return to
     * @return array<string, mixed>
     */
    public function dashboard(array $range, string $period, string $backUrl): array
    {
        $from = $range['from']->toDateString();
        $to = $range['to']->toDateString();
        $days = (int) $range['from']->copy()->startOfDay()->diffInDays($range['to']->copy()->startOfDay()) + 1;
        $prevFrom = $range['from']->copy()->subDays($days);
        $prevTo = $range['from']->copy()->subDay();

        $summaries = TrackerDatabase::table('tracking_daily_summaries')
            ->whereBetween('metric_date', [$prevFrom->toDateString(), $to])
            ->get()
            ->keyBy(fn (object $row) => (string) $row->metric_date);

        $prevRange = [
            'from' => $prevFrom->copy()->startOfDay(),
            'to' => $prevTo->copy()->endOfDay(),
            'period' => $period,
        ];
        $current = $this->kpiSection->totalsFromSummaryRows(
            $this->summaryRowsInRange($summaries, $from, $to),
        );
        $previous = $this->kpiSection->totalsFromSummaryRows(
            $this->summaryRowsInRange($summaries, $prevFrom->toDateString(), $prevTo->toDateString()),
        );

        $uniqueReturning = $this->uniqueReturningSection->snapshot(
            (int) $current['unique_visitors'],
            (int) $current['sessions'],
        );

        return [
            'kpiGroups' => $this->kpiSection->groups(
                $current,
                $previous,
                $this->kpiSection->comparisonLabel($period, $prevFrom, $prevTo),
            ),
            'categories' => $this->categorySection->categories($range['from'], $range['to'], $period, []),
            'products' => $this->productSection->products($range['from'], $range['to'], $period, []),
            'recoverable' => $this->recoverableSection->panels($from, $to, $backUrl),
            'devices' => $this->deviceBrowserSection->forRange($from, $to),
            'traffic' => $this->trafficSourcesSection->forRange($from, $to),
            'durations' => $this->sessionDurationSection->forRange($from, $to),
            'newReturning' => $uniqueReturning['newReturning'],
            'charts' => [
                'trend' => $this->shopperJourneySection->buildTrend($summaries, $range, $days, $period),
                'new_returning' => $uniqueReturning['chart'],
            ],
        ];
    }

    /**
     * Category / product view totals for trend buckets (tracking daily rollups, scaled when session filters apply).
     *
     * @param  array<string, mixed>  $sessionFilters
     * @return array{category_views: int, product_views: int}
     */
    public function summaryViewTotals(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        return $this->shopperJourneySection->summaryViewTotals($from, $to, $period, $sessionFilters);
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{departments: list<array<string, mixed>>, totals: array{category_views: int, product_views: int, category_count: int}}
     */
    public function categories(Carbon $from, Carbon $to, string $period, array $sessionFilters = []): array
    {
        return $this->categorySection->categories($from, $to, $period, $sessionFilters);
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    public function products(
        Carbon $from,
        Carbon $to,
        string $period,
        array $sessionFilters = [],
        ?int $limit = ProductPerformanceSectionService::PRODUCT_LIMIT,
    ): array {
        return $this->productSection->products($from, $to, $period, $sessionFilters, $limit);
    }

    /**
     * @param  Collection<string, object>  $summaries  keyed by metric_date
     */
    private function summaryRowsInRange(Collection $summaries, string $from, string $to): Collection
    {
        return $summaries
            ->filter(fn (object $row, string $date) => $date >= $from && $date <= $to)
            ->values();
    }
}
