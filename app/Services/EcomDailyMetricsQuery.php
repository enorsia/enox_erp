<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
use App\Support\EcomAnalyticsRangeSplitter;
use App\Support\EcomDailyDimensionType;
use App\Support\EcomDailyRollupSchema;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads hybrid metrics: summed daily rollups for closed days + live raw queries for today.
 */
class EcomDailyMetricsQuery
{
    /** @var array<string, bool> */
    private array $siteRollupsCompleteMemo = [];

    public function __construct(
        private EcomTrafficSourceAggregator $trafficAggregator,
    ) {}

    /**
     * @param  list<string>  $dates
     */
    public function rollupHybridCatalogReady(Carbon $from, Carbon $to, ?string $period): bool
    {
        if (! config('tracker.use_daily_rollups', true)) {
            return false;
        }

        if (! EcomDailyRollupSchema::hasCommerceViewColumns()) {
            return false;
        }

        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        if (! $split['use_rollups'] || $split['closed_dates'] === []) {
            return false;
        }

        return $split['use_rollups'] && $split['closed_dates'] !== [];
    }

    /**
     * Closed days in the range that already have a site metrics rollup row.
     *
     * @param  list<string>  $closedDates
     * @return list<string>
     */
    public function presentSiteRollupDates(array $closedDates): array
    {
        if ($closedDates === []) {
            return [];
        }

        return DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $closedDates)
            ->orderBy('metric_date')
            ->pluck('metric_date')
            ->map(fn ($date) => (string) $date)
            ->values()
            ->all();
    }

    /**
     * Store dashboard payment rows when rollups-only: orders/actions only on rolled-up closed days + live today.
     *
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    public function dashboardPaymentRows(Carbon $from, Carbon $to, ?string $period): array
    {
        if (! config('tracker.dashboard_rollups_only', true)) {
            return CommerceFunnelQuery::paymentRows($from, $to, null, $period);
        }

        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);
        $rows = CommerceFunnelQuery::paymentRowsForLocalCalendarDates(
            $this->presentSiteRollupDates($split['closed_dates']),
            null,
        );

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $rows = array_merge(
                $rows,
                CommerceFunnelQuery::paymentRows($split['live_from'], $split['live_to'], null, '24h'),
            );
        }

        return $rows;
    }

    /**
     * @param  list<string>  $dates
     * @return array{expected: int, found: int, complete: bool}
     */
    public function siteRollupsCoverage(array $dates): array
    {
        if ($dates === []) {
            return ['expected' => 0, 'found' => 0, 'complete' => true];
        }

        $expected = count($dates);

        if ($this->hasCompleteSiteRollups($dates)) {
            return ['expected' => $expected, 'found' => $expected, 'complete' => true];
        }

        $found = (int) DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->count();

        return [
            'expected' => $expected,
            'found' => $found,
            'complete' => false,
        ];
    }

    public function hasCompleteSiteRollups(array $dates): bool
    {
        if ($dates === []) {
            return true;
        }

        $key = $this->datesCacheKey($dates);

        if (array_key_exists($key, $this->siteRollupsCompleteMemo)) {
            return $this->siteRollupsCompleteMemo[$key];
        }

        $found = (int) DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->count();

        return $this->siteRollupsCompleteMemo[$key] = $found === count($dates);
    }

    /**
     * Mark closed dates as covered by rollups (e.g. after batch loader validated site rows).
     *
     * @param  list<string>  $dates
     */
    public function rememberSiteRollupsComplete(array $dates): void
    {
        if ($dates === []) {
            return;
        }

        $this->siteRollupsCompleteMemo[$this->datesCacheKey($dates)] = true;
    }

    /**
     * @param  list<string>  $dates
     */
    private function datesCacheKey(array $dates): string
    {
        $normalized = $dates;
        sort($normalized);

        return implode(',', $normalized);
    }

    /**
     * @return array<string, int|float>|null null when rollups incomplete — caller should use raw aggregates
     */
    public function sumSiteSessionAggregates(array $dates): ?array
    {
        if ($dates === [] || ! $this->hasCompleteSiteRollups($dates)) {
            return null;
        }

        return $this->fetchSiteSessionAggregatesFromRollupTables($dates);
    }

    /**
     * Sum rollup tables for the given dates even when the set is incomplete (e.g. previous-period KPI compare).
     *
     * @param  list<string>  $dates
     * @return array<string, int|float>
     */
    public function sumSiteSessionAggregatesPartial(array $dates): array
    {
        if ($dates === []) {
            return $this->emptySiteSessionAggregates();
        }

        return $this->fetchSiteSessionAggregatesFromRollupTables($dates);
    }

    /**
     * @return array<string, int|float>
     */
    private function emptySiteSessionAggregates(): array
    {
        return [
            'sessions' => 0,
            'unique_visitors' => 0,
            'total_stay_seconds' => 0,
            'avg_stay_seconds' => 0,
            'add_to_cart' => 0,
            'begin_checkout' => 0,
            'proceed_checkout' => 0,
            'payment_success' => 0,
            'cart_abandoned' => 0,
            'begin_checkout_abandoned' => 0,
            'proceed_checkout_abandoned' => 0,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, int|float>
     */
    private function fetchSiteSessionAggregatesFromRollupTables(array $dates): array
    {
        $row = DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->selectRaw('
                COALESCE(SUM(session_count), 0) as sessions,
                COALESCE(SUM(add_to_cart_count), 0) as add_to_cart,
                COALESCE(SUM(begin_checkout_count), 0) as begin_checkout,
                COALESCE(SUM(proceed_checkout_count), 0) as proceed_checkout,
                COALESCE(SUM(payment_success_count), 0) as payment_success
            ')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $totalStay = (int) DB::table('activity_ecom_daily_visitors')
            ->whereIn('visit_date', $dates)
            ->sum('total_duration_seconds');

        return [
            'sessions' => $sessions,
            'unique_visitors' => $this->distinctVisitorsForDates($dates),
            'total_stay_seconds' => $totalStay,
            'avg_stay_seconds' => $sessions > 0 ? (int) round($totalStay / $sessions) : 0,
            'add_to_cart' => (int) ($row->add_to_cart ?? 0),
            'begin_checkout' => (int) ($row->begin_checkout ?? 0),
            'proceed_checkout' => (int) ($row->proceed_checkout ?? 0),
            'payment_success' => (int) ($row->payment_success ?? 0),
            'cart_abandoned' => 0,
            'begin_checkout_abandoned' => 0,
            'proceed_checkout_abandoned' => 0,
        ];
    }

    /**
     * @param  list<string>  $dates
     */
    public function sumRevenueForDates(array $dates): float
    {
        if ($dates === []) {
            return 0.0;
        }

        if (! config('tracker.dashboard_rollups_only', true) && ! $this->hasCompleteSiteRollups($dates)) {
            return 0.0;
        }

        return (float) DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->sum('revenue_total');
    }

    /**
     * @param  list<string>  $dates
     */
    public function sumItemsSoldQtyForDates(array $dates): int
    {
        if ($dates === []) {
            return 0;
        }

        if (! config('tracker.dashboard_rollups_only', true) && ! $this->hasCompleteSiteRollups($dates)) {
            return 0;
        }

        return (int) DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->sum('items_sold_qty');
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, array<string, int|float|string>>|null
     */
    public function sumTrafficBuckets(array $dates): ?array
    {
        if ($dates === []) {
            return [];
        }

        if (! config('tracker.dashboard_rollups_only', true) && ! $this->hasCompleteTrafficRollups($dates)) {
            return null;
        }

        $buckets = [];

        $rows = DB::table('activity_ecom_daily_dimension_metrics')
            ->whereIn('metric_date', $dates)
            ->where('dimension_type', EcomDailyDimensionType::LIST_TRAFFIC)
            ->get();

        foreach ($rows as $row) {
            $parsed = EcomDailyDimensionType::parseTrafficDimensionValue((string) $row->dimension_value);
            $source = $parsed['source'];
            $medium = $parsed['medium'];
            $key = $source."\0".$medium;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'source' => $source,
                    'medium' => $medium,
                    'sessions' => 0,
                    'views' => 0,
                    'add_to_cart' => 0,
                    'begin_checkout' => 0,
                    'proceed_checkout' => 0,
                    'payment_success' => 0,
                    'sold_qty' => 0,
                    'revenue' => 0.0,
                ];
            }

            $buckets[$key]['sessions'] += (int) $row->session_count;
            $buckets[$key]['payment_success'] += (int) $row->payment_count;
            $buckets[$key]['revenue'] = round((float) $buckets[$key]['revenue'] + (float) $row->revenue, 2);
        }

        return $buckets;
    }

    /**
     * @param  list<string>  $dates
     */
    private function hasCompleteTrafficRollups(array $dates): bool
    {
        if (! $this->hasCompleteSiteRollups($dates)) {
            return false;
        }

        $sitesWithSessions = DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $dates)
            ->where('session_count', '>', 0)
            ->pluck('metric_date')
            ->map(fn ($d) => (string) $d);

        if ($sitesWithSessions->isEmpty()) {
            return true;
        }

        $withTraffic = DB::table('activity_ecom_daily_dimension_metrics')
            ->whereIn('metric_date', $sitesWithSessions)
            ->where('dimension_type', EcomDailyDimensionType::LIST_TRAFFIC)
            ->distinct()
            ->pluck('metric_date')
            ->map(fn ($d) => (string) $d);

        return $sitesWithSessions->diff($withTraffic)->isEmpty();
    }

    /**
     * @param  list<string>  $dates
     */
    private function distinctVisitorsForDates(array $dates): int
    {
        return (int) DB::table('activity_ecom_daily_visitor_metrics')
            ->whereIn('metric_date', $dates)
            ->distinct()
            ->count('visitor_id');
    }

    /**
     * @param  callable(Carbon, Carbon, ?string): array<string, int|float>  $liveSessionAggregates
     * @return array<string, int|float>
     */
    public function hybridSessionAggregates(
        Carbon $from,
        Carbon $to,
        ?string $period,
        callable $liveSessionAggregates,
    ): array {
        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        if (! $split['use_rollups'] || $split['closed_dates'] === []) {
            return $liveSessionAggregates($from, $to, $period);
        }

        $closed = $this->sumSiteSessionAggregates($split['closed_dates']);

        if ($closed === null) {
            if (config('tracker.dashboard_rollups_only', true)) {
                $closed = $this->sumSiteSessionAggregatesPartial($split['closed_dates']);
            } elseif ($split['closed_dates'] !== [] && config('tracker.use_daily_rollups', true)) {
                $closed = $this->sumSiteSessionAggregatesPartial($split['closed_dates']);
            } else {
                return $liveSessionAggregates($from, $to, $period);
            }
        }

        if ($split['live_from'] === null || $split['live_to'] === null) {
            return $closed;
        }

        $live = $liveSessionAggregates($split['live_from'], $split['live_to'], '24h');

        return $this->mergeSessionAggregateRows($closed, $live, $split['closed_dates'], $split['live_from'], $split['live_to']);
    }

    /**
     * @param  array<string, int|float>  $closed
     * @param  array<string, int|float>  $live
     * @param  list<string>  $closedDates
     * @return array<string, int|float>
     */
    private function mergeSessionAggregateRows(
        array $closed,
        array $live,
        array $closedDates,
        Carbon $liveFrom,
        Carbon $liveTo,
    ): array {
        $sessions = (int) $closed['sessions'] + (int) $live['sessions'];
        $totalStay = (int) $closed['total_stay_seconds'] + (int) $live['total_stay_seconds'];

        $closedVisitors = $this->distinctVisitorsForDates($closedDates);
        $liveVisitors = (int) DB::table('activity_ecom_user')
            ->whereBetween('created_at', TrackerTime::storageRange($liveFrom, $liveTo))
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', '')
            ->distinct()
            ->count('visitor_id');

        $overlap = (int) DB::table('activity_ecom_daily_visitor_metrics as d')
            ->join('activity_ecom_user as s', 's.visitor_id', '=', 'd.visitor_id')
            ->whereIn('d.metric_date', $closedDates)
            ->whereBetween('s.created_at', TrackerTime::storageRange($liveFrom, $liveTo))
            ->distinct()
            ->count('d.visitor_id');

        $uniqueVisitors = max(0, $closedVisitors + $liveVisitors - $overlap);

        return [
            'sessions' => $sessions,
            'unique_visitors' => $uniqueVisitors,
            'total_stay_seconds' => $totalStay,
            'avg_stay_seconds' => $sessions > 0 ? (int) round($totalStay / $sessions) : 0,
            'add_to_cart' => (int) $closed['add_to_cart'] + (int) $live['add_to_cart'],
            'begin_checkout' => (int) $closed['begin_checkout'] + (int) $live['begin_checkout'],
            'proceed_checkout' => (int) $closed['proceed_checkout'] + (int) $live['proceed_checkout'],
            'payment_success' => (int) $closed['payment_success'] + (int) $live['payment_success'],
            'cart_abandoned' => (int) $closed['cart_abandoned'] + (int) $live['cart_abandoned'],
            'begin_checkout_abandoned' => (int) $closed['begin_checkout_abandoned'] + (int) $live['begin_checkout_abandoned'],
            'proceed_checkout_abandoned' => (int) $closed['proceed_checkout_abandoned'] + (int) $live['proceed_checkout_abandoned'],
        ];
    }

    /**
     * @return array<string, array<string, int|float|string>>
     */
    public function hybridTrafficBuckets(
        Carbon $from,
        Carbon $to,
        ?string $period,
        ?\Illuminate\Support\Collection $sessionIds,
    ): ?array {
        if ($sessionIds !== null) {
            return null;
        }

        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        if (! $split['use_rollups']) {
            return null;
        }

        $buckets = $this->sumTrafficBuckets($split['closed_dates']);

        if ($buckets === null) {
            return null;
        }

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $liveBuckets = $this->trafficAggregator->bucketsForRange(
                $split['live_from'],
                $split['live_to'],
                null,
                '24h',
            );
            $this->trafficAggregator->mergeBuckets($buckets, $liveBuckets);
        }

        return $buckets;
    }

    /**
     * Per calendar day site totals for trend charts (closed days from rollups + live today).
     *
     * @return array<string, array<string, int>>|null keyed by Y-m-d
     */
    public function hybridDailySiteMetricsByDate(Carbon $from, Carbon $to, ?string $period): ?array
    {
        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        if (! $split['use_rollups'] || $split['closed_dates'] === []) {
            return null;
        }

        if (! config('tracker.dashboard_rollups_only', true)
            && ! $this->hasCompleteSiteRollups($split['closed_dates'])) {
            return null;
        }

        $byDate = $this->siteMetricsByDateSkeleton($split['closed_dates']);

        $rows = DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $split['closed_dates'])
            ->get();

        foreach ($rows as $row) {
            $byDate[(string) $row->metric_date] = $this->mapSiteMetricsRow($row);
        }

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $liveDate = TrackerTime::toLocal($split['live_from'])?->toDateString()
                ?? $split['live_from']->toDateString();
            $byDate[$liveDate] = $this->liveSiteMetricsForDay($split['live_from'], $split['live_to']);
        }

        if (config('tracker.dashboard_rollups_only', true)
            && ! $this->hasCompleteSiteRollups($split['closed_dates'])) {
            return $byDate;
        }

        $paymentRows = CommerceFunnelQuery::paymentRows($from, $to, null, $period);

        return CommerceFunnelQuery::overlayDailySiteMetricsWithPaymentRows($byDate, $paymentRows);
    }

    /**
     * @return array<string, int>
     */
    /**
     * @param  list<string>  $dates
     * @return array<string, array<string, int>>
     */
    public function siteMetricsByDateSkeleton(array $dates): array
    {
        $byDate = [];

        foreach ($dates as $date) {
            $byDate[$date] = $this->emptySiteMetricsDay();
        }

        return $byDate;
    }

    /**
     * @return array<string, int>
     */
    public function emptySiteMetricsDay(): array
    {
        return [
            'sessions' => 0,
            'visitor_count' => 0,
            'category_views' => 0,
            'product_views' => 0,
            'add_to_cart' => 0,
            'begin_checkout' => 0,
            'proceed_checkout' => 0,
            'payment_success' => 0,
            'items_sold_qty' => 0,
        ];
    }

    private function mapSiteMetricsRow(object $row): array
    {
        return [
            'sessions' => (int) ($row->session_count ?? 0),
            'visitor_count' => (int) ($row->visitor_count ?? 0),
            'category_views' => (int) ($row->category_view_count ?? 0),
            'product_views' => (int) ($row->product_view_count ?? 0),
            'add_to_cart' => (int) ($row->add_to_cart_count ?? 0),
            'begin_checkout' => (int) ($row->begin_checkout_count ?? 0),
            'proceed_checkout' => (int) ($row->proceed_checkout_count ?? 0),
            'payment_success' => (int) ($row->payment_success_count ?? 0),
            'items_sold_qty' => (int) ($row->items_sold_qty ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $productCatalogOptions
     * @return array{products: array{products: array<int, array<string, mixed>>, filter_options: array<string, mixed>, sort_by: string}, categories: array<int, array<string, mixed>>}|null
     */
    public function hybridDashboardCatalog(
        Carbon $from,
        Carbon $to,
        ?string $period,
        array $productCatalogOptions = [],
    ): ?array {
        if (! $this->rollupHybridCatalogReady($from, $to, $period)) {
            return null;
        }

        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        $productRows = DB::table('activity_ecom_daily_product_metrics')
            ->whereIn('metric_date', $split['closed_dates'])
            ->selectRaw('product_code,
                MAX(product_name) as product_name,
                MAX(sku) as sku,
                MAX(department_name) as department_name,
                MAX(category_name) as category_name,
                SUM(view_count) as views,
                SUM(add_to_cart_count) as adds,
                SUM(begin_checkout_count) as begin_checkouts,
                SUM(proceed_checkout_count) as proceed_checkouts,
                SUM(payment_count) as purchases,
                SUM(units_sold) as qty,
                SUM(revenue) as revenue')
            ->groupBy('product_code')
            ->get();

        $categoryRows = DB::table('activity_ecom_daily_category_metrics')
            ->whereIn('metric_date', $split['closed_dates'])
            ->selectRaw('department_name,
                category_name,
                SUM(category_view_count) as category_views,
                SUM(product_view_count) as product_views,
                SUM(add_to_cart_count) as adds,
                SUM(proceed_checkout_count) as proceed_checkouts,
                SUM(payment_count) as purchases,
                SUM(units_sold) as sale_items,
                SUM(revenue) as sale_amount')
            ->groupBy('department_name', 'category_name')
            ->get();

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $this->mergeLiveProductRollupRows($productRows, $split['live_from'], $split['live_to']);
            $this->mergeLiveCategoryRollupRows($categoryRows, $split['live_from'], $split['live_to']);
        }

        $products = $productRows
            ->map(fn (object $row) => [
                'code' => (string) $row->product_code,
                'product_code' => (string) $row->product_code,
                'name' => (string) ($row->product_name ?? ''),
                'category' => (string) ($row->category_name ?? ''),
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'begin_checkouts' => (int) $row->begin_checkouts,
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'purchases' => (int) $row->purchases,
                'qty' => (int) round((float) $row->qty),
                'revenue' => round((float) $row->revenue, 2),
                'variant_count' => 1,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $maxRevenue = max(1.0, (float) collect($products)->max('revenue'));

        $products = array_map(static function (array $product) use ($maxRevenue) {
            $product['revenue_bar_percent'] = (int) round(((float) $product['revenue'] / $maxRevenue) * 100);

            return $product;
        }, $products);

        $categories = $categoryRows
            ->map(function (object $row) {
                $views = (int) $row->category_views + (int) $row->product_views;
                $purchases = (int) $row->purchases;
                $base = $views > 0 ? $views : (int) $row->adds;

                return [
                    'department_name' => (string) $row->department_name,
                    'category_name' => (string) $row->category_name,
                    'category_views' => (int) $row->category_views,
                    'product_views' => (int) $row->product_views,
                    'views' => $views,
                    'adds' => (int) $row->adds,
                    'proceed_checkouts' => (int) $row->proceed_checkouts,
                    'purchases' => $purchases,
                    'sale_items' => (int) round((float) $row->sale_items),
                    'sale_amount' => round((float) $row->sale_amount, 2),
                    'conversion_rate' => $base > 0 ? round(($purchases / $base) * 100, 1) : 0.0,
                    'label' => \App\Support\TrackerCategoryIdentity::label((string) $row->department_name, (string) $row->category_name),
                    'name' => \App\Support\TrackerCategoryIdentity::label((string) $row->department_name, (string) $row->category_name),
                ];
            })
            ->sortByDesc('sale_amount')
            ->values()
            ->all();

        $sortBy = $productCatalogOptions['sort_by'] ?? 'revenue';

        return [
            'products' => [
                'products' => $products,
                'filter_options' => ['categories' => [], 'colors' => [], 'sizes' => []],
                'sort_by' => is_string($sortBy) ? $sortBy : 'revenue',
            ],
            'categories' => $categories,
        ];
    }

    /**
     * Sidebar facet counts when only the date range is filtered (no session facets).
     *
     * @return array<string, array<string, int>>|null
     */
    public function hybridActivityFacetCounts(Carbon $from, Carbon $to, ?string $period): ?array
    {
        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

        if (! $split['use_rollups'] || ! $this->hasCompleteSiteRollups($split['closed_dates'])) {
            return null;
        }

        $device = $this->sumDimensionSessions($split['closed_dates'], EcomDailyDimensionType::DEVICE);
        $loggedIn = $this->sumDimensionSessions($split['closed_dates'], EcomDailyDimensionType::LOGGED_IN);
        $hasOrder = $this->sumDimensionSessions($split['closed_dates'], EcomDailyDimensionType::HAS_ORDER);
        $traffic = $this->sumTrafficBuckets($split['closed_dates']);

        if ($traffic === null) {
            return null;
        }

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $this->mergeLiveFacetDimensions($device, EcomDailyDimensionType::DEVICE, $split['live_from'], $split['live_to']);
            $this->mergeLiveFacetDimensions($loggedIn, EcomDailyDimensionType::LOGGED_IN, $split['live_from'], $split['live_to']);
            $this->mergeLiveFacetDimensions($hasOrder, EcomDailyDimensionType::HAS_ORDER, $split['live_from'], $split['live_to']);
            $liveTraffic = $this->trafficAggregator->bucketsForRange($split['live_from'], $split['live_to'], null, '24h');
            $this->trafficAggregator->mergeBuckets($traffic, $liveTraffic);
        }

        $utmSource = [];
        $utmMedium = [];

        foreach ($traffic as $bucket) {
            $source = (string) ($bucket['source'] ?? '(direct)');
            $medium = (string) ($bucket['medium'] ?? 'none');
            $sessions = (int) ($bucket['sessions'] ?? 0);
            $utmSource[$source] = ($utmSource[$source] ?? 0) + $sessions;
            $utmMedium[$medium] = ($utmMedium[$medium] ?? 0) + $sessions;
        }

        arsort($utmSource);
        arsort($utmMedium);
        arsort($device);

        return [
            'device_type' => $device,
            'logged_in' => [
                '1' => (int) ($loggedIn['1'] ?? 0),
                '0' => (int) ($loggedIn['0'] ?? 0),
            ],
            'has_order' => [
                '1' => (int) ($hasOrder['1'] ?? 0),
                '0' => (int) ($hasOrder['0'] ?? 0),
            ],
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, int>
     */
    public function sumDimensionSessions(array $dates, string $dimensionType): array
    {
        if ($dates === []) {
            return [];
        }

        $counts = [];

        $rows = DB::table('activity_ecom_daily_dimension_metrics')
            ->whereIn('metric_date', $dates)
            ->where('dimension_type', $dimensionType)
            ->selectRaw('dimension_value, SUM(session_count) as sessions')
            ->groupBy('dimension_value')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->dimension_value] = (int) $row->sessions;
        }

        return $counts;
    }

    /**
     * @param  list<string>  $dates
     * @return array<int, array{location: string, sessions: int, revenue: float}>|null
     */
    public function hybridGeographyRows(array $dates, Carbon $liveFrom, Carbon $liveTo, ?int $limit): ?array
    {
        if (! $this->hasCompleteSiteRollups($dates)) {
            return null;
        }

        $buckets = $this->sumDimensionSessions($dates, EcomDailyDimensionType::GEO);

        if ($liveFrom && $liveTo) {
            $this->mergeLiveFacetDimensions($buckets, EcomDailyDimensionType::GEO, $liveFrom, $liveTo);
        }

        $rows = collect($buckets)
            ->map(function (int $sessions, string $key) {
                [$city, $country] = array_pad(explode("\0", $key, 2), 2, 'Unknown');

                return [
                    'location' => $city.', '.$country,
                    'sessions' => $sessions,
                    'revenue' => 0.0,
                ];
            })
            ->sortByDesc('sessions')
            ->values();

        if ($limit !== null) {
            $rows = $rows->take($limit);
        }

        return $rows->all();
    }

    /**
     * @param  array<string, int>  $target
     */
    private function mergeLiveFacetDimensions(array &$target, string $type, Carbon $from, Carbon $to): void
    {
        $bounds = TrackerTime::storageRange($from, $to);

        if ($type === EcomDailyDimensionType::DEVICE) {
            $rows = DB::table('activity_ecom_user')
                ->whereBetween('created_at', $bounds)
                ->selectRaw("COALESCE(NULLIF(TRIM(device_type), ''), 'unknown') as bucket, COUNT(*) as sessions")
                ->groupBy('bucket')
                ->get();
        } elseif ($type === EcomDailyDimensionType::LOGGED_IN) {
            $rows = DB::table('activity_ecom_user')
                ->whereBetween('created_at', $bounds)
                ->selectRaw("CASE WHEN is_logged_in = 1 THEN '1' ELSE '0' END as bucket, COUNT(*) as sessions")
                ->groupBy('bucket')
                ->get();
        } elseif ($type === EcomDailyDimensionType::HAS_ORDER) {
            $rows = DB::table('activity_ecom_user as s')
                ->whereBetween('s.created_at', $bounds)
                ->leftJoin('activity_ecom_orders as o', function ($join) use ($bounds) {
                    $join->on('o.session_id', '=', 's.session_id')
                        ->whereBetween('o.ordered_at', $bounds);
                })
                ->selectRaw('CASE WHEN o.session_id IS NOT NULL THEN 1 ELSE 0 END as bucket, COUNT(*) as sessions')
                ->groupBy('bucket')
                ->get();
        } elseif ($type === EcomDailyDimensionType::GEO) {
            $rows = DB::table('activity_ecom_user')
                ->whereBetween('created_at', $bounds)
                ->selectRaw("COALESCE(NULLIF(TRIM(city), ''), 'Unknown') as city, COALESCE(NULLIF(TRIM(country), ''), 'Unknown') as country, COUNT(*) as sessions")
                ->groupBy('city', 'country')
                ->get()
                ->map(fn (object $row) => (object) [
                    'bucket' => $row->city."\0".$row->country,
                    'sessions' => $row->sessions,
                ]);
        } else {
            return;
        }

        foreach ($rows as $row) {
            $key = (string) $row->bucket;
            $target[$key] = ($target[$key] ?? 0) + (int) $row->sessions;
        }
    }

    private function mergeLiveProductRollupRows(\Illuminate\Support\Collection $rows, Carbon $from, Carbon $to): void
    {
        $bounds = TrackerTime::storageRange($from, $to);
        $live = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.product_code')
            ->where('li.product_code', '!=', '')
            ->selectRaw("li.product_code,
                MAX(li.product_name) as product_name,
                SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN li.funnel_stage = 'add_to_cart' THEN 1 ELSE 0 END) as adds,
                SUM(CASE WHEN li.funnel_stage = 'begin_checkout' THEN 1 ELSE 0 END) as begin_checkouts,
                SUM(CASE WHEN li.funnel_stage = 'proceed_checkout' THEN 1 ELSE 0 END) as proceed_checkouts,
                SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN 1 ELSE 0 END) as purchases,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.qty ELSE 0 END), 0) as qty,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.line_total ELSE 0 END), 0) as revenue")
            ->groupBy('li.product_code')
            ->get()
            ->keyBy('product_code');

        foreach ($live as $code => $row) {
            $existing = $rows->firstWhere('product_code', $code);
            if ($existing === null) {
                $rows->push($row);

                continue;
            }

            $existing->views = (int) $existing->views + (int) $row->views;
            $existing->adds = (int) $existing->adds + (int) $row->adds;
            $existing->begin_checkouts = (int) $existing->begin_checkouts + (int) $row->begin_checkouts;
            $existing->proceed_checkouts = (int) $existing->proceed_checkouts + (int) $row->proceed_checkouts;
            $existing->purchases = (int) $existing->purchases + (int) $row->purchases;
            $existing->qty = (float) $existing->qty + (float) $row->qty;
            $existing->revenue = (float) $existing->revenue + (float) $row->revenue;
        }
    }

    private function mergeLiveCategoryRollupRows(\Illuminate\Support\Collection $rows, Carbon $from, Carbon $to): void
    {
        $bounds = TrackerTime::storageRange($from, $to);
        $live = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.category_name')
            ->where('li.category_name', '!=', '')
            ->selectRaw("COALESCE(li.department_name, '') as department_name,
                li.category_name,
                SUM(CASE WHEN li.funnel_stage = 'category_view' THEN 1 ELSE 0 END) as category_views,
                SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as product_views,
                SUM(CASE WHEN li.funnel_stage = 'add_to_cart' THEN 1 ELSE 0 END) as adds,
                SUM(CASE WHEN li.funnel_stage = 'proceed_checkout' THEN 1 ELSE 0 END) as proceed_checkouts,
                SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN 1 ELSE 0 END) as purchases,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.qty ELSE 0 END), 0) as sale_items,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.line_total ELSE 0 END), 0) as sale_amount")
            ->groupBy('department_name', 'li.category_name')
            ->get();

        foreach ($live as $row) {
            $existing = $rows->first(fn (object $item) => (string) $item->department_name === (string) $row->department_name
                && (string) $item->category_name === (string) $row->category_name);

            if ($existing === null) {
                $rows->push($row);

                continue;
            }

            $existing->category_views = (int) $existing->category_views + (int) $row->category_views;
            $existing->product_views = (int) $existing->product_views + (int) $row->product_views;
            $existing->adds = (int) $existing->adds + (int) $row->adds;
            $existing->proceed_checkouts = (int) $existing->proceed_checkouts + (int) $row->proceed_checkouts;
            $existing->purchases = (int) $existing->purchases + (int) $row->purchases;
            $existing->sale_items = (float) $existing->sale_items + (float) $row->sale_items;
            $existing->sale_amount = (float) $existing->sale_amount + (float) $row->sale_amount;
        }
    }

    /**
     * @return array<string, int>
     */
    private function liveSiteMetricsForDay(Carbon $from, Carbon $to): array
    {
        $bounds = TrackerTime::storageRange($from, $to);

        $sessions = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw(
                'COUNT(*) as session_count,
                 COUNT(DISTINCT visitor_id) as visitor_count,
                 COALESCE(SUM(has_add_to_cart), 0) as add_to_cart,
                 COALESCE(SUM(has_begin_checkout), 0) as begin_checkout,
                 COALESCE(SUM(has_proceed_checkout), 0) as proceed_checkout,
                 COALESCE(SUM(has_payment_success), 0) as payment_success',
            )
            ->first();

        $orderTotals = CommerceFunnelQuery::paymentMetricTotals(
            $from,
            $to,
            null,
            TrackerTime::toLocal($from)?->toDateString() === TrackerTime::localDate() ? '24h' : null,
        );

        $viewStats = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->selectRaw(
                "SUM(CASE WHEN li.funnel_stage = 'category_view' THEN 1 ELSE 0 END) as category_views,
                 SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as product_views",
            )
            ->first();

        return [
            'sessions' => (int) ($sessions->session_count ?? 0),
            'visitor_count' => (int) ($sessions->visitor_count ?? 0),
            'category_views' => (int) ($viewStats->category_views ?? 0),
            'product_views' => (int) ($viewStats->product_views ?? 0),
            'add_to_cart' => (int) ($sessions->add_to_cart ?? 0),
            'begin_checkout' => (int) ($sessions->begin_checkout ?? 0),
            'proceed_checkout' => (int) ($sessions->proceed_checkout ?? 0),
            'payment_success' => $orderTotals['purchases'],
            'items_sold_qty' => $orderTotals['item_qty'],
        ];
    }

    public function mapSiteMetricsRowPublic(object $row): array
    {
        return $this->mapSiteMetricsRow($row);
    }

    public function liveSiteMetricsForDayPublic(Carbon $from, Carbon $to): array
    {
        return $this->liveSiteMetricsForDay($from, $to);
    }

    public function mergeLiveProductRollupRowsPublic(\Illuminate\Support\Collection $rows, Carbon $from, Carbon $to): void
    {
        $this->mergeLiveProductRollupRows($rows, $from, $to);
    }

    public function mergeLiveCategoryRollupRowsPublic(\Illuminate\Support\Collection $rows, Carbon $from, Carbon $to): void
    {
        $this->mergeLiveCategoryRollupRows($rows, $from, $to);
    }

    public function mergeLiveCatalogRollupRowsPublic(
        \Illuminate\Support\Collection $productRows,
        \Illuminate\Support\Collection $categoryRows,
        Carbon $from,
        Carbon $to,
    ): void {
        $bounds = TrackerTime::storageRange($from, $to);
        $live = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->select(
                'li.product_code',
                'li.product_name',
                'li.department_name',
                'li.category_name',
                'li.funnel_stage',
                'li.qty',
                'li.line_total',
            )
            ->get();

        // One scan of today's line items: aggregate product + category rollups in PHP.
        $liveProducts = [];
        $liveCategories = [];

        foreach ($live as $row) {
            $stage = (string) $row->funnel_stage;
            $code = (string) ($row->product_code ?? '');
            if ($code !== '') {
                $liveProducts[$code] ??= (object) [
                    'product_code' => $code,
                    'product_name' => (string) ($row->product_name ?? ''),
                    'views' => 0,
                    'adds' => 0,
                    'begin_checkouts' => 0,
                    'proceed_checkouts' => 0,
                    'purchases' => 0,
                    'qty' => 0.0,
                    'revenue' => 0.0,
                ];
                $this->accumulateLiveCatalogStage($liveProducts[$code], $stage, $row);
            }

            $categoryName = (string) ($row->category_name ?? '');
            if ($categoryName === '') {
                continue;
            }

            $dept = (string) ($row->department_name ?? '');
            $catKey = $dept."\0".$categoryName;
            $liveCategories[$catKey] ??= (object) [
                'department_name' => $dept,
                'category_name' => $categoryName,
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'purchases' => 0,
                'sale_items' => 0.0,
                'sale_amount' => 0.0,
            ];
            $this->accumulateLiveCategoryStage($liveCategories[$catKey], $stage, $row);
        }

        foreach ($liveProducts as $code => $row) {
            $existing = $productRows->firstWhere('product_code', $code);
            if ($existing === null) {
                $productRows->push($row);

                continue;
            }

            $existing->views = (int) $existing->views + (int) $row->views;
            $existing->adds = (int) $existing->adds + (int) $row->adds;
            $existing->begin_checkouts = (int) ($existing->begin_checkouts ?? 0) + (int) $row->begin_checkouts;
            $existing->proceed_checkouts = (int) $existing->proceed_checkouts + (int) $row->proceed_checkouts;
            $existing->purchases = (int) $existing->purchases + (int) $row->purchases;
            $existing->qty = (float) $existing->qty + (float) $row->qty;
            $existing->revenue = (float) $existing->revenue + (float) $row->revenue;
        }

        foreach ($liveCategories as $catKey => $row) {
            [$dept, $categoryName] = array_pad(explode("\0", $catKey, 2), 2, '');
            $existing = $categoryRows->first(fn (object $r) => (string) $r->department_name === $dept
                && (string) $r->category_name === $categoryName);
            if ($existing === null) {
                $categoryRows->push($row);

                continue;
            }

            $existing->category_views = (int) $existing->category_views + (int) $row->category_views;
            $existing->product_views = (int) $existing->product_views + (int) $row->product_views;
            $existing->adds = (int) $existing->adds + (int) $row->adds;
            $existing->proceed_checkouts = (int) $existing->proceed_checkouts + (int) $row->proceed_checkouts;
            $existing->purchases = (int) $existing->purchases + (int) $row->purchases;
            $existing->sale_items = (float) $existing->sale_items + (float) $row->sale_items;
            $existing->sale_amount = (float) $existing->sale_amount + (float) $row->sale_amount;
        }
    }

    private function accumulateLiveCatalogStage(object $target, string $stage, object $row): void
    {
        if (in_array($stage, ['product_view', 'product_view_popup'], true)) {
            $target->views++;
        } elseif ($stage === 'add_to_cart') {
            $target->adds++;
        } elseif ($stage === 'begin_checkout') {
            $target->begin_checkouts++;
        } elseif ($stage === 'proceed_checkout') {
            $target->proceed_checkouts++;
        } elseif ($stage === 'payment_success') {
            $target->purchases++;
            $target->qty += (float) ($row->qty ?? 0);
            $target->revenue += (float) ($row->line_total ?? 0);
        }
    }

    private function accumulateLiveCategoryStage(object $target, string $stage, object $row): void
    {
        if ($stage === 'category_view') {
            $target->category_views++;
        } elseif (in_array($stage, ['product_view', 'product_view_popup'], true)) {
            $target->product_views++;
        } elseif ($stage === 'add_to_cart') {
            $target->adds++;
        } elseif ($stage === 'proceed_checkout') {
            $target->proceed_checkouts++;
        } elseif ($stage === 'payment_success') {
            $target->purchases++;
            $target->sale_items += (float) ($row->qty ?? 0);
            $target->sale_amount += (float) ($row->line_total ?? 0);
        }
    }

    /**
     * @param  array<string, mixed>  $productCatalogOptions
     * @return array{products: array{products: array<int, array<string, mixed>>, filter_options: array<string, mixed>, sort_by: string}, categories: array<int, array<string, mixed>>}
     */
    public function formatDashboardCatalogFromRows(
        \Illuminate\Support\Collection $productRows,
        \Illuminate\Support\Collection $categoryRows,
        array $productCatalogOptions = [],
    ): array {
        $products = $productRows
            ->map(fn (object $row) => [
                'code' => (string) $row->product_code,
                'product_code' => (string) $row->product_code,
                'name' => (string) ($row->product_name ?? ''),
                'category' => (string) ($row->category_name ?? ''),
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'begin_checkouts' => (int) ($row->begin_checkouts ?? 0),
                'proceed_checkouts' => (int) ($row->proceed_checkouts ?? 0),
                'purchases' => (int) $row->purchases,
                'qty' => (int) round((float) $row->qty),
                'revenue' => round((float) $row->revenue, 2),
                'variant_count' => 1,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $maxRevenue = max(1.0, (float) collect($products)->max('revenue'));

        $products = array_map(static function (array $product) use ($maxRevenue) {
            $product['revenue_bar_percent'] = (int) round(((float) $product['revenue'] / $maxRevenue) * 100);

            return $product;
        }, $products);

        $categories = $categoryRows
            ->map(function (object $row) {
                $views = (int) $row->category_views + (int) $row->product_views;
                $purchases = (int) $row->purchases;
                $base = $views > 0 ? $views : (int) $row->adds;

                return [
                    'department_name' => (string) $row->department_name,
                    'category_name' => (string) $row->category_name,
                    'category_views' => (int) $row->category_views,
                    'product_views' => (int) $row->product_views,
                    'views' => $views,
                    'adds' => (int) $row->adds,
                    'proceed_checkouts' => (int) $row->proceed_checkouts,
                    'purchases' => $purchases,
                    'sale_items' => (int) round((float) $row->sale_items),
                    'sale_amount' => round((float) $row->sale_amount, 2),
                    'conversion_rate' => $base > 0 ? round(($purchases / $base) * 100, 1) : 0.0,
                    'label' => \App\Support\TrackerCategoryIdentity::label((string) $row->department_name, (string) $row->category_name),
                    'name' => \App\Support\TrackerCategoryIdentity::label((string) $row->department_name, (string) $row->category_name),
                ];
            })
            ->sortByDesc('sale_amount')
            ->values()
            ->all();

        $sortBy = $productCatalogOptions['sort_by'] ?? 'revenue';

        return [
            'products' => [
                'products' => $products,
                'filter_options' => ['categories' => [], 'colors' => [], 'sizes' => []],
                'sort_by' => is_string($sortBy) ? $sortBy : 'revenue',
            ],
            'categories' => $categories,
        ];
    }
}
