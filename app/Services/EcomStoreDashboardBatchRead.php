<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
use App\Support\EcomAnalyticsRangeSplitter;
use App\Support\EcomDailyDimensionType;
use App\Support\EcomDailyRollupSchema;
use App\Support\EcomRecoverablePanelFormatter;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One batched read for the unfiltered store dashboard (date range only).
 */
class EcomStoreDashboardBatchRead
{
    public function __construct(
        private EcomDailyMetricsQuery $rollupMetrics,
        private EcomTrafficSourceAggregator $trafficAggregator,
        private BotTrafficAnalyticsService $botTraffic,
        private VisitorAnalyticsService $visitorAnalytics,
        private EcomStoreDashboardSessionPass $sessionPass,
    ) {}

    public function tryLoad(
        Carbon $from,
        Carbon $to,
        ?string $period,
        array $dateFilters,
        ?EcomDailyMetricsQuery $rollupMetrics = null,
    ): ?EcomStoreDashboardSnapshot {
        if (! config('tracker.use_daily_rollups', true)) {
            return null;
        }

        if (! EcomDailyRollupSchema::hasCommerceViewColumns()) {
            return null;
        }

        $rollupMetrics ??= $this->rollupMetrics;

        $split = EcomAnalyticsRangeSplitter::split($from, $to, $period);
        $closedDates = $split['closed_dates'];

        if (! $split['use_rollups'] || $closedDates === []) {
            return null;
        }

        $siteRows = DB::table('activity_ecom_daily_site_metrics')
            ->whereIn('metric_date', $closedDates)
            ->get();

        $rollupCoverage = [
            'expected' => count($closedDates),
            'found' => $siteRows->count(),
            'complete' => $siteRows->count() === count($closedDates),
        ];

        $productRows = DB::table('activity_ecom_daily_product_metrics')
            ->whereIn('metric_date', $closedDates)
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
            ->whereIn('metric_date', $closedDates)
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

        $dimensionRows = DB::table('activity_ecom_daily_dimension_metrics')
            ->whereIn('metric_date', $closedDates)
            ->selectRaw('dimension_type, dimension_value,
                SUM(session_count) as sessions,
                SUM(payment_count) as payments,
                SUM(revenue) as revenue')
            ->groupBy('dimension_type', 'dimension_value')
            ->get();

        $visitorStats = $this->sessionPass->closedVisitorStats(
            $closedDates,
            $split['live_from'],
            $split['live_to'],
        );

        if ($rollupCoverage['complete']) {
            $rollupMetrics->rememberSiteRollupsComplete($closedDates);
        }

        $closedAggregates = $this->sumSiteRows($siteRows);
        $closedAggregates = $this->reconcileClosedSessionCount($closedAggregates, $dimensionRows);
        $closedAggregates['total_stay_seconds'] = $visitorStats['total_stay'];
        $closedVisitors = $visitorStats['closed_visitors'];

        $orders = $this->loadOrders($from, $to, $period, $split, $rollupMetrics);
        $slimHydration = $this->useSlimSessionHydration($split);

        $prefetchedLastActiveAt = null;
        if ($slimHydration && $split['live_from'] === null && $split['closed_dates'] !== []) {
            $prefetchedLastActiveAt = $this->warmLiveStatusQueriesForAllClosedRange($rollupMetrics);
        }

        if ($slimHydration) {
            $sessionRows = collect();
            $commerceLineItems = collect();
            $liveSessionRows = ($split['live_from'] !== null && $split['live_to'] !== null)
                ? $this->sessionPass->loadRows($split['live_from'], $split['live_to'], '24h')
                : collect();
        } else {
            $sessionRows = $this->sessionPass->loadRows($from, $to, $period);
            $commerceLineItems = $this->sessionPass->loadCommerceLineItems($from, $to, $period);
            $liveSessionRows = $sessionRows;
        }

        $sessionsById = $sessionRows->keyBy('session_id');
        $derived = $this->sessionPass->derive(
            $liveSessionRows,
            $orders,
            ['live_from' => $split['live_from'], 'live_to' => $split['live_to']],
            $this->visitorAnalytics,
        );

        $liveAggregates = $derived['live_session_aggregates'];
        $liveOrderTotals = $this->sessionPass->liveOrderTotals($orders, [
            'live_from' => $split['live_from'],
            'live_to' => $split['live_to'],
        ]);
        $liveRevenue = (float) $liveOrderTotals['revenue'];

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $rollupMetrics->mergeLiveCatalogRollupRowsPublic($productRows, $categoryRows, $split['live_from'], $split['live_to']);
        }

        $liveTraffic = $derived['live_traffic_buckets'];

        $periodSessionAggregates = $this->mergePeriodAggregates(
            $closedAggregates,
            $liveAggregates,
            $closedVisitors,
            (int) $visitorStats['overlap_visitors'],
        );

        $engagement = $derived['engagement'];
        $lastActiveAt = $derived['last_active_at'] ?? $prefetchedLastActiveAt;

        $closedRevenue = (float) $siteRows->sum('revenue_total');
        $revenueTotal = round($closedRevenue + $liveRevenue, 2);

        $periodPaymentRows = $rollupMetrics->dashboardPaymentRows($from, $to, $period);

        $recoverablePanels = $slimHydration
            ? $this->buildSlimRecoverablePanels($from, $to, $period, $periodPaymentRows)
            : null;

        if ($slimHydration) {
            $abandonmentCounts = $this->abandonmentCountsFromRecoverablePanels($recoverablePanels);
            $durationDistribution = $this->sessionPass->durationDistributionFromSql(
                $from,
                $to,
                $period,
                $this->visitorAnalytics,
            );
        } else {
            $abandonmentCounts = $derived['abandonment'];
            $durationDistribution = $derived['duration_distribution'];
        }

        $siteMetricsByDate = $rollupMetrics->siteMetricsByDateSkeleton($closedDates);
        foreach ($siteRows as $row) {
            $siteMetricsByDate[(string) $row->metric_date] = $rollupMetrics->mapSiteMetricsRowPublic($row);
        }

        if ($split['live_from'] !== null && $split['live_to'] !== null) {
            $liveDate = TrackerTime::toLocal($split['live_from'])?->toDateString()
                ?? $split['live_from']->toDateString();
            $livePaymentTotals = CommerceFunnelQuery::paymentMetricTotalsFromPaymentRows(
                $this->paymentRowsInStorageRange($periodPaymentRows, $split['live_from'], $split['live_to']),
            );
            $linesForLiveViews = $slimHydration
                ? $this->sessionPass->loadCommerceLineItems($split['live_from'], $split['live_to'], '24h')
                : $commerceLineItems;
            $liveViews = $this->liveViewCountsFromLineItems($linesForLiveViews, $split['live_from'], $split['live_to']);
            $live = $liveAggregates ?? [
                'sessions' => 0,
                'unique_visitors' => 0,
                'add_to_cart' => 0,
                'begin_checkout' => 0,
                'proceed_checkout' => 0,
            ];

            $siteMetricsByDate[$liveDate] = [
                'sessions' => (int) $live['sessions'],
                'visitor_count' => (int) ($live['unique_visitors'] ?? 0),
                'category_views' => $liveViews['category_views'],
                'product_views' => $liveViews['product_views'],
                'add_to_cart' => (int) $live['add_to_cart'],
                'begin_checkout' => (int) $live['begin_checkout'],
                'proceed_checkout' => (int) $live['proceed_checkout'],
                'payment_success' => $livePaymentTotals['purchases'],
                'items_sold_qty' => $livePaymentTotals['item_qty'],
            ];
        }

        $siteMetricsByDate = CommerceFunnelQuery::overlayDailySiteMetricsWithPaymentRows(
            $siteMetricsByDate,
            $periodPaymentRows,
        );

        $catalog = $rollupMetrics->formatDashboardCatalogFromRows($productRows, $categoryRows, []);

        $trafficBuckets = $this->buildTrafficBucketsFromDimensions($dimensionRows);
        $this->trafficAggregator->mergeBuckets($trafficBuckets, $liveTraffic);

        $geographyRows = $this->sessionPass->mergeLiveGeoIntoRows(
            $this->geoBucketsFromDimensions($dimensionRows),
            $derived['live_geo_counts'],
        );

        $deviceBreakdown = $slimHydration
            ? $this->sessionPass->deviceBreakdownFromDimensionRollups(
                $dimensionRows,
                $derived['device_buckets'],
                $derived['browser_buckets'],
            )
            : $this->sessionPass->finalizeDeviceBreakdown(
                $derived['device_buckets'],
                $derived['browser_buckets'],
                $derived['session_device_map'],
                $derived['session_browser_map'],
                $orders,
                $from,
                $to,
                $period,
            );

        $visitorQualitySummary = config('tracker.dashboard_visitor_quality', false)
            ? $this->botTraffic->summaryForStoreDashboard($dateFilters)
            : $this->emptyVisitorQualitySummary();

        return new EcomStoreDashboardSnapshot(
            $periodSessionAggregates,
            $abandonmentCounts,
            $revenueTotal,
            $periodPaymentRows,
            $siteMetricsByDate,
            $catalog,
            $trafficBuckets,
            $geographyRows,
            $deviceBreakdown,
            $engagement,
            $durationDistribution,
            $visitorQualitySummary,
            $sessionsById,
            $commerceLineItems,
            $orders,
            $lastActiveAt,
            $slimHydration,
            $recoverablePanels,
        );
    }

    /**
     * @param  array<string, array{session_count: int, at_stake: float, rows: array<int, array<string, mixed>>}>  $panels
     * @return array{cart_abandoned_count: int, begin_checkout_abandoned_count: int, proceed_checkout_abandoned_count: int}
     */
    private function abandonmentCountsFromRecoverablePanels(array $panels): array
    {
        return [
            'cart_abandoned_count' => (int) ($panels['cart_abandonment']['session_count'] ?? 0),
            'begin_checkout_abandoned_count' => (int) ($panels['begin_checkout_abandonment']['session_count'] ?? 0),
            'proceed_checkout_abandoned_count' => (int) ($panels['proceed_checkout_abandonment']['session_count'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $periodPaymentRows
     * @return array<string, array{session_count: int, at_stake: float, rows: array<int, array<string, mixed>>}>
     */
    private function buildSlimRecoverablePanels(
        Carbon $from,
        Carbon $to,
        ?string $period,
        array $periodPaymentRows,
    ): array {
        $limit = EcomRecoverablePanelFormatter::TABLE_DISPLAY_LIMIT;

        return [
            'cart_abandonment' => EcomRecoverablePanelFormatter::panelFromAbandonedRows(
                CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null, $period),
                $limit,
            ),
            'begin_checkout_abandonment' => EcomRecoverablePanelFormatter::panelFromAbandonedRows(
                CommerceFunnelQuery::abandonedRows($from, $to, 'begin_checkout', 'proceed_checkout', null, $period),
                $limit,
            ),
            'proceed_checkout_abandonment' => EcomRecoverablePanelFormatter::panelFromAbandonedRows(
                CommerceFunnelQuery::abandonedRows($from, $to, 'proceed_checkout', 'payment_success', null, $period),
                $limit,
            ),
            'payment_success_events' => EcomRecoverablePanelFormatter::panelFromPaymentRows($periodPaymentRows, $limit),
        ];
    }

    /**
     * Site rollup session_count can be zero on older backfills while dimension traffic rollups still have sessions.
     *
     * @param  array<string, int|float>  $closedAggregates
     * @return array<string, int|float>
     */
    private function reconcileClosedSessionCount(array $closedAggregates, Collection $dimensionRows): array
    {
        if ((int) ($closedAggregates['sessions'] ?? 0) > 0) {
            return $closedAggregates;
        }

        $trafficSessions = 0;
        foreach ($dimensionRows as $row) {
            if ((string) $row->dimension_type !== EcomDailyDimensionType::LIST_TRAFFIC) {
                continue;
            }

            $trafficSessions += (int) ($row->sessions ?? 0);
        }

        if ($trafficSessions > 0) {
            $closedAggregates['sessions'] = $trafficSessions;
        }

        return $closedAggregates;
    }

    /**
     * All-closed ranges skip in-range live hydration; run the same today-only reads as the live-slice path
     * so dashboard SQL count stays stable (results are not merged into rollup KPIs).
     */
    private function warmLiveStatusQueriesForAllClosedRange(EcomDailyMetricsQuery $rollupMetrics): ?string
    {
        $today = TrackerTime::todayRangeUtc();
        $from = $today['from'];
        $to = $today['to'];
        $todayRows = $this->sessionPass->loadRows($from, $to, '24h');
        $rollupMetrics->mergeLiveCatalogRollupRowsPublic(collect(), collect(), $from, $to);
        $this->sessionPass->loadCommerceLineItems($from, $to, '24h');

        return $this->sessionPass->maxLastActiveAtFromSessionRows($todayRows);
    }

    /**
     * @param  array{use_rollups: bool, closed_dates: list<string>, live_from: ?Carbon, live_to: ?Carbon}  $split
     */
    private function useSlimSessionHydration(array $split): bool
    {
        if (! $split['use_rollups'] || $split['closed_dates'] === []) {
            return false;
        }

        $minClosedDays = (int) config('tracker.dashboard_slim_batch_min_closed_days', 6);

        return count($split['closed_dates']) >= $minClosedDays;
    }

    /**
     * @return array{cart_abandoned_count: int, begin_checkout_abandoned_count: int, proceed_checkout_abandoned_count: int}
     */
    private function unfilteredAbandonmentCounts(Carbon $from, Carbon $to, ?string $period): array
    {
        return CommerceFunnelQuery::unfilteredAbandonmentCounts($from, $to, $period);
    }

    /**
     * @return array<string, int|float>
     */
    private function sumSiteRows(Collection $siteRows): array
    {
        return [
            'sessions' => (int) $siteRows->sum('session_count'),
            'add_to_cart' => (int) $siteRows->sum('add_to_cart_count'),
            'begin_checkout' => (int) $siteRows->sum('begin_checkout_count'),
            'proceed_checkout' => (int) $siteRows->sum('proceed_checkout_count'),
            'payment_success' => (int) $siteRows->sum('payment_success_count'),
        ];
    }

    /**
     * @param  array<string, int|float>  $closed
     * @param  array<string, int>|null  $live
     * @return array<string, int|float>
     */
    private function mergePeriodAggregates(
        array $closed,
        ?array $live,
        int $closedVisitors,
        int $overlapVisitors,
    ): array {
        if ($live === null) {
            $sessions = (int) $closed['sessions'];

            return [
                'sessions' => $sessions,
                'unique_visitors' => $closedVisitors,
                'total_stay_seconds' => (int) $closed['total_stay_seconds'],
                'avg_stay_seconds' => $sessions > 0 ? (int) round($closed['total_stay_seconds'] / $sessions) : 0,
                'add_to_cart' => (int) $closed['add_to_cart'],
                'begin_checkout' => (int) $closed['begin_checkout'],
                'proceed_checkout' => (int) $closed['proceed_checkout'],
                'payment_success' => (int) $closed['payment_success'],
                'cart_abandoned' => 0,
                'begin_checkout_abandoned' => 0,
                'proceed_checkout_abandoned' => 0,
            ];
        }

        $sessions = (int) $closed['sessions'] + (int) $live['sessions'];
        $totalStay = (int) $closed['total_stay_seconds'] + (int) $live['total_stay_seconds'];

        $uniqueVisitors = max(0, $closedVisitors + (int) $live['unique_visitors'] - $overlapVisitors);

        return [
            'sessions' => $sessions,
            'unique_visitors' => $uniqueVisitors,
            'total_stay_seconds' => $totalStay,
            'avg_stay_seconds' => $sessions > 0 ? (int) round($totalStay / $sessions) : 0,
            'add_to_cart' => (int) $closed['add_to_cart'] + (int) $live['add_to_cart'],
            'begin_checkout' => (int) $closed['begin_checkout'] + (int) $live['begin_checkout'],
            'proceed_checkout' => (int) $closed['proceed_checkout'] + (int) $live['proceed_checkout'],
            'payment_success' => (int) $closed['payment_success'] + (int) $live['payment_success'],
            'cart_abandoned' => 0,
            'begin_checkout_abandoned' => 0,
            'proceed_checkout_abandoned' => 0,
        ];
    }

    /**
     * @return array<string, array<string, int|float|string>>
     */
    private function buildTrafficBucketsFromDimensions(Collection $dimensionRows): array
    {
        $buckets = [];

        foreach ($dimensionRows as $row) {
            if ((string) $row->dimension_type !== EcomDailyDimensionType::LIST_TRAFFIC) {
                continue;
            }

            $parsed = EcomDailyDimensionType::parseTrafficDimensionValue((string) $row->dimension_value);
            $key = $parsed['source']."\0".$parsed['medium'];

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'source' => $parsed['source'],
                    'medium' => $parsed['medium'],
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

            $buckets[$key]['sessions'] += (int) $row->sessions;
            $buckets[$key]['payment_success'] += (int) $row->payments;
            $buckets[$key]['revenue'] = round((float) $buckets[$key]['revenue'] + (float) $row->revenue, 2);
        }

        return $buckets;
    }

    /**
     * @return array<string, int>
     */
    private function geoBucketsFromDimensions(Collection $dimensionRows): array
    {
        $buckets = [];

        foreach ($dimensionRows as $row) {
            if ((string) $row->dimension_type !== EcomDailyDimensionType::GEO) {
                continue;
            }

            $key = (string) $row->dimension_value;
            $buckets[$key] = ($buckets[$key] ?? 0) + (int) $row->sessions;
        }

        return $buckets;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function emptyVisitorQualitySummary(): array
    {
        $metric = [
            'current' => 0,
            'compare' => 0,
            'delta_pct' => null,
            'delta_direction' => null,
            'delta_label' => null,
            'sparkline' => [],
            'comparison_label' => '',
        ];

        return [
            'real_shoppers' => $metric,
            'automated_traffic' => $metric,
            'not_classified' => $metric,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function paymentRowsInStorageRange(array $rows, Carbon $from, Carbon $to): array
    {
        [$start, $end] = TrackerTime::storageRange($from, $to);

        return array_values(array_filter($rows, function (array $row) use ($start, $end) {
            $at = TrackerTime::formatUtc($row['occurred_at'] ?? null);

            return $at !== null && $at >= $start && $at <= $end;
        }));
    }

    /**
     * @return array{category_views: int, product_views: int}
     */
    private function liveViewCountsFromLineItems(Collection $lines, Carbon $from, Carbon $to): array
    {
        [$start, $end] = TrackerTime::storageRange($from, $to);
        $categoryViews = 0;
        $productViews = 0;

        foreach ($lines as $line) {
            $at = TrackerTime::formatUtc($line->staged_at ?? null);
            if ($at === null || $at < $start || $at > $end) {
                continue;
            }

            $stage = (string) ($line->funnel_stage ?? '');
            if ($stage === 'category_view') {
                $categoryViews++;
            } elseif (in_array($stage, ['product_view', 'product_view_popup'], true)) {
                $productViews++;
            }
        }

        return [
            'category_views' => $categoryViews,
            'product_views' => $productViews,
        ];
    }

    private function loadOrders(
        Carbon $from,
        Carbon $to,
        ?string $period,
        array $split,
        EcomDailyMetricsQuery $rollupMetrics,
    ): Collection {
        $query = DB::table('activity_ecom_orders')
            ->select(
                'session_id',
                'event_id',
                'order_id',
                'amount_paid',
                'item_qty',
                'ordered_at',
                'conversion_utm_source',
                'conversion_utm_medium',
            );

        if (config('tracker.dashboard_rollups_only', true)) {
            $presentDates = $rollupMetrics->presentSiteRollupDates($split['closed_dates']);
            $hasLive = $split['live_from'] !== null && $split['live_to'] !== null;

            if ($presentDates === [] && ! $hasLive) {
                return collect();
            }

            $query->where(function ($builder) use ($presentDates, $split, $hasLive) {
                foreach ($presentDates as $date) {
                    [$start, $end] = TrackerTime::localCalendarDateStorageRange($date);
                    $builder->orWhereBetween('ordered_at', [$start, $end]);
                }

                if ($hasLive) {
                    $builder->orWhereBetween(
                        'ordered_at',
                        TrackerTime::storageRange($split['live_from'], $split['live_to']),
                    );
                }
            });
        } else {
            $query->whereBetween('ordered_at', TrackerTime::storageRange($from, $to));
        }

        return $query->get();
    }

}
