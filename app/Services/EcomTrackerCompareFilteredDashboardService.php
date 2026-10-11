<?php

namespace App\Services;

use App\Models\TrackingDailyAudience;
use App\Models\TrackingDailyDuration;
use App\Support\EcomTrackerCompareKpiGroups;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild dashboard-shaped compare column data from filtered tracking_session rows (compare page only).
 */
class EcomTrackerCompareFilteredDashboardService
{
    private const PRODUCT_LIMIT = 10;

    private const RECOVERABLE_ROW_LIMIT = 20;

    private const TREND_LOG_SCALE_DAYS = 31;

    private const TREND_WEEKLY_THRESHOLD_DAYS = 32;

    private const TREND_MONTHLY_THRESHOLD_DAYS = 91;

    /** @var array<int, array<string, string>> */
    private const RECOVERABLE_PANELS = [
        3 => ['panelId' => 'cart-abandon', 'title' => 'Cart abandoned', 'tone' => 'cart', 'emptyMessage' => 'No cart abandonment in this period.'],
        4 => ['panelId' => 'begin-checkout-abandon', 'title' => 'Begin checkout abandoned', 'tone' => 'begin', 'emptyMessage' => 'No begin checkout abandonment in this period.'],
        5 => ['panelId' => 'proceed-checkout-abandon', 'title' => 'Proceed checkout abandoned', 'tone' => 'proceed', 'emptyMessage' => 'No proceed checkout abandonment in this period.'],
        6 => ['panelId' => 'payment-success-events', 'title' => 'Payment success', 'tone' => 'success', 'emptyMessage' => 'No payment success events in this period.'],
    ];

    public function __construct(
        private EcomActivityListService $activityList,
        private EcomTrackerDashboardService $dashboardService,
    ) {}

    /**
     * @param  array<string, mixed>  $dashboard  unfiltered dashboard payload from {@see EcomTrackerDashboardService}
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $sessionFilters
     * @return array<string, mixed>
     */
    public function apply(array $dashboard, array $range, string $period, array $sessionFilters, string $backUrl): array
    {
        $from = $range['from']->toDateString();
        $to = $range['to']->toDateString();
        $days = (int) $range['from']->copy()->startOfDay()->diffInDays($range['to']->copy()->startOfDay()) + 1;
        $prevFrom = $range['from']->copy()->subDays($days);
        $prevTo = $range['from']->copy()->subDay();
        $prevRange = ['from' => $prevFrom->copy()->startOfDay(), 'to' => $prevTo->copy()->endOfDay()];

        $current = $this->activityList->dashboardTotals($range, $sessionFilters);
        $previous = $this->activityList->dashboardTotals($prevRange, $sessionFilters);
        $returning = max(0, $current['sessions'] - $current['unique_visitors']);

        $dashboard['kpiGroups'] = EcomTrackerCompareKpiGroups::build($current, $previous, $period, $prevFrom, $prevTo);
        $dashboard['newReturning'] = ['unique' => $current['unique_visitors'], 'returning' => $returning];
        $dashboard['charts']['new_returning'] = [
            'labels' => ['Unique', 'Returning'],
            'values' => [$current['unique_visitors'], $returning],
        ];
        $dashboard['charts']['trend'] = $this->trend($range, $period, $days, $sessionFilters);
        $dashboard['categories'] = $this->dashboardService->categories($range['from'], $range['to'], $period, $sessionFilters);
        $dashboard['products'] = $this->dashboardService->products($range['from'], $range['to'], $period, $sessionFilters);
        $dashboard['recoverable'] = $this->recoverable($from, $to, $sessionFilters, $backUrl);
        $audiences = $this->audiences($range, $sessionFilters);
        $dashboard['devices'] = [
            'by_device' => $audiences[TrackingDailyAudience::TYPE_DEVICE] ?? [],
            'by_browser' => $audiences[TrackingDailyAudience::TYPE_BROWSER] ?? [],
        ];
        $dashboard['traffic'] = $audiences[TrackingDailyAudience::TYPE_TRAFFIC_SOURCE] ?? [];
        $dashboard['durations'] = $this->durations($range, $sessionFilters);

        return $dashboard;
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function trend(array $range, string $period, int $days, array $filters): array
    {
        $fromLocal = TrackerTime::toLocal($range['from'])?->copy()->startOfDay()
            ?? $range['from']->copy()->startOfDay();
        $toLocalDay = TrackerTime::toLocal($range['to'])?->copy()->startOfDay()
            ?? $range['to']->copy()->startOfDay();
        $toLocal = TrackerTime::toLocal($range['to'])?->copy()->endOfDay() ?? $range['to']->copy()->endOfDay();

        if ($days === 1) {
            return $this->hourlyTrend($range, $period, $fromLocal, $filters);
        }

        $bucket = match (true) {
            $days >= self::TREND_MONTHLY_THRESHOLD_DAYS => 'month',
            $days >= self::TREND_WEEKLY_THRESHOLD_DAYS => 'week',
            default => 'day',
        };
        $periodBuckets = $this->trendPeriods($fromLocal, $toLocal, $bucket);
        $labels = [];
        $data = $this->emptyTrendBucketData();

        foreach ($periodBuckets as [$periodStart, $periodEnd, $label]) {
            $labels[] = $label;
            $subRange = ['from' => $periodStart, 'to' => $periodEnd];
            $this->pushTrendBucket($data, $this->trendMetrics($subRange, $filters, $period));
        }

        return $this->formatTrendResponse(
            $labels,
            $data,
            $bucket,
            $days,
            $days > self::TREND_LOG_SCALE_DAYS,
            $this->trendRangeLabel($days, $bucket, $period, null),
        );
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function trendMetrics(array $range, array $filters, string $period): array
    {
        $rangeWithPeriod = $range + ['period' => $period];
        $totals = $this->activityList->dashboardTotals($rangeWithPeriod, $filters);
        $scope = fn (Builder $query) => $this->activityList->constrainSessionsInRange($query, $rangeWithPeriod, $filters);
        $orderBounds = TrackerTime::storageRange($range['from'], $range['to']);

        $views = $this->dashboardService->summaryViewTotals($range['from'], $range['to'], $period, $filters);

        $purchaseSessions = (int) DB::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope)
            ->whereBetween('o.ordered_at', $orderBounds)
            ->distinct()
            ->count('o.tracking_session_id');

        return [
            'unique_visitors' => (int) $totals['unique_visitors'],
            'sessions' => (int) $totals['sessions'],
            'category_views' => (int) ($views['category_views'] ?? 0),
            'product_views' => (int) ($views['product_views'] ?? 0),
            'cart_sessions' => (int) $totals['cart_sessions'],
            'checkout_sessions' => (int) $totals['checkout_sessions'],
            'proceed_sessions' => (int) $totals['proceed_sessions'],
            'payments' => (int) $totals['payments'],
            'items_sold' => (int) $totals['items_sold'],
            'purchase_sessions' => $purchaseSessions,
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     */
    private function hourlyTrend(array $range, string $period, Carbon $dayLocal, array $filters): array
    {
        $tz = TrackerTime::timezone();
        $bounds = TrackerTime::storageRange($range['from'], $range['to']);
        $scope = fn (Builder $query) => $this->activityList->constrainSessionsInRange($query, $range, $filters);
        $hourExpr = "HOUR(CONVERT_TZ(ts.last_active_at, '+00:00', '".str_replace("'", "''", $tz)."'))";

        $sessionRows = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw("{$hourExpr} as hour")
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT ts.tracking_daily_visitor_id) as unique_visitors')
            ->selectRaw('SUM(CASE WHEN ts.has_add_to_cart = 1 THEN 1 ELSE 0 END) as cart_sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as checkout_sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_sessions')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as payments')
            ->groupBy('hour')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->hour);

        $hourlyRows = DB::table('tracking_hourly_summaries')
            ->where('metric_date', $dayLocal->toDateString())
            ->get()
            ->keyBy(fn (object $row) => (int) $row->metric_hour);

        $filteredByHour = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw("{$hourExpr} as hour")
            ->selectRaw('COUNT(*) as sessions')
            ->groupBy('hour')
            ->pluck('sessions', 'hour');

        $orderHourExpr = "HOUR(CONVERT_TZ(o.ordered_at, '+00:00', '".str_replace("'", "''", $tz)."'))";

        $itemRows = DB::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope)
            ->whereBetween('o.ordered_at', $bounds)
            ->selectRaw("{$orderHourExpr} as hour")
            ->selectRaw('COALESCE(SUM(o.items_qty), 0) as items_sold')
            ->selectRaw('COUNT(DISTINCT o.tracking_session_id) as purchase_sessions')
            ->groupBy('hour')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->hour);

        $labels = [];
        $data = $this->emptyTrendBucketData();

        for ($hour = 0; $hour < 24; $hour++) {
            $session = $sessionRows->get($hour);
            $hourly = $hourlyRows->get($hour);
            $items = $itemRows->get($hour);
            $totalHourSessions = (int) ($hourly->sessions ?? 0);
            $filteredHourSessions = (int) ($filteredByHour[$hour] ?? 0);
            $viewRatio = $totalHourSessions > 0 ? min(1.0, $filteredHourSessions / $totalHourSessions) : 0.0;
            $labels[] = sprintf('%02d:00', $hour);
            $this->pushTrendBucket($data, [
                'unique_visitors' => (int) ($session->unique_visitors ?? 0),
                'sessions' => (int) ($session->sessions ?? 0),
                'category_views' => (int) round((int) ($hourly->category_views ?? 0) * $viewRatio),
                'product_views' => (int) round((int) ($hourly->product_views ?? 0) * $viewRatio),
                'cart_sessions' => (int) ($session->cart_sessions ?? 0),
                'checkout_sessions' => (int) ($session->checkout_sessions ?? 0),
                'proceed_sessions' => (int) ($session->proceed_sessions ?? 0),
                'payments' => (int) ($session->payments ?? 0),
                'items_sold' => (int) ($items->items_sold ?? 0),
                'purchase_sessions' => (int) ($items->purchase_sessions ?? 0),
            ]);
        }

        return $this->formatTrendResponse(
            $labels,
            $data,
            'hour',
            1,
            false,
            $this->trendRangeLabel(1, 'hour', $period, $dayLocal),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function recoverable(string $from, string $to, array $filters, string $backUrl): array
    {
        $range = [
            'from' => Carbon::createFromFormat('Y-m-d', $from, TrackerTime::timezone())->startOfDay(),
            'to' => Carbon::createFromFormat('Y-m-d', $to, TrackerTime::timezone())->endOfDay(),
        ];
        $scope = fn (Builder $query) => $this->activityList->constrainSessionsInRange($query, $range, $filters);

        $ranked = DB::table('tracking_recoverable_sales as rs')
            ->join('tracking_session as ts', 'ts.id', '=', 'rs.tracking_session_id')
            ->whereBetween('rs.metric_date', [$from, $to])
            ->tap($scope)
            ->select('rs.type', 'rs.qty', 'rs.sale_value', 'rs.occurred_at', 'ts.session_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY rs.type ORDER BY rs.occurred_at DESC, rs.id DESC) as row_no')
            ->selectRaw('COUNT(*) OVER (PARTITION BY rs.type) as total_count')
            ->selectRaw('SUM(rs.sale_value) OVER (PARTITION BY rs.type) as total_value');

        $rows = DB::query()
            ->fromSub($ranked, 'r')
            ->where('row_no', '<=', self::RECOVERABLE_ROW_LIMIT)
            ->orderBy('row_no')
            ->get()
            ->groupBy('type');

        $panels = [];
        foreach (self::RECOVERABLE_PANELS as $stage => $panel) {
            $typeRows = $rows->get($stage, collect());

            $panels[] = $panel + [
                'stage' => $stage,
                'session_count' => (int) ($typeRows->first()->total_count ?? 0),
                'at_stake' => (float) ($typeRows->first()->total_value ?? 0),
                'rows' => $typeRows->map(fn (object $row) => [
                    'session_id' => (string) $row->session_id,
                    'session_label' => substr((string) $row->session_id, 0, 8).'…',
                    'qty' => (int) $row->qty,
                    'value' => (float) $row->sale_value,
                    'occurred_ago' => TrackerTime::diffForHumansFromStorage($row->occurred_at) ?? '—',
                    'activity_url' => route('admin.ecom-activity.show', ['session' => $row->session_id, 'back' => $backUrl]),
                ])->all(),
            ];
        }

        return $panels;
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     * @return array<int, list<array<string, mixed>>>
     */
    private function audiences(array $range, array $filters): array
    {
        $scope = fn (Builder $query) => $this->activityList->constrainSessionsInRange($query, $range, $filters);
        $deviceIds = array_values(array_filter(array_map('intval', $filters['devices'] ?? [])));
        $utmSourceIds = array_values(array_filter(array_map('intval', $filters['utm_sources'] ?? [])));
        $utmMediums = array_values(array_filter(array_map('strval', $filters['utm_mediums'] ?? []), 'filled'));

        $deviceRows = DB::table('tracking_session as ts')
            ->tap($scope);
        SessionTrafficAttribution::joinLatestSessionDetails($deviceRows);
        $deviceRows = $deviceRows
            ->when($deviceIds !== [], fn (Builder $query) => $query->whereIn('sd.tracking_device_id', $deviceIds))
            ->leftJoin('tracking_device as dv', 'dv.id', '=', 'sd.tracking_device_id')
            ->groupBy('sd.tracking_device_id', 'dv.name')
            ->selectRaw('sd.tracking_device_id as dimension_id, dv.name as name')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_add_to_cart = 1 THEN 1 ELSE 0 END) as add_to_cart')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as begin_checkout')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_checkout')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as payment_success')
            ->get();

        $browserRows = DB::table('tracking_session as ts')
            ->tap($scope);
        SessionTrafficAttribution::joinLatestSessionDetails($browserRows);
        $browserRows = $browserRows
            ->leftJoin('tracking_browser as br', 'br.id', '=', 'sd.tracking_browser_id')
            ->groupBy('sd.tracking_browser_id', 'br.name')
            ->selectRaw('sd.tracking_browser_id as dimension_id, br.name as name')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_add_to_cart = 1 THEN 1 ELSE 0 END) as add_to_cart')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as begin_checkout')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_checkout')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as payment_success')
            ->get();

        $trafficRows = DB::table('tracking_session as ts')
            ->tap($scope);
        SessionTrafficAttribution::joinLatestSessionDetails($trafficRows);
        $trafficRows = $trafficRows
            ->when($utmSourceIds !== [], fn (Builder $query) => $query->whereIn('sd.tracking_traffic_source_id', $utmSourceIds))
            ->when($utmMediums !== [], fn (Builder $query) => $query->whereIn('sd.tracking_traffic_source_id', fn (Builder $sources) => $sources
                ->select('id')
                ->from('tracking_traffic_source')
                ->whereIn('medium', $utmMediums)))
            ->leftJoin('tracking_traffic_source as tts', 'tts.id', '=', 'sd.tracking_traffic_source_id')
            ->groupBy('sd.tracking_traffic_source_id', 'tts.name', 'tts.medium')
            ->selectRaw('sd.tracking_traffic_source_id as dimension_id, tts.name as name, tts.name as source, tts.medium as medium')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_add_to_cart = 1 THEN 1 ELSE 0 END) as add_to_cart')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as begin_checkout')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_checkout')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as payment_success')
            ->get();

        $bounds = TrackerTime::storageRange($range['from'], $range['to']);
        $soldByDevice = $this->soldMetricsByDimension($scope, $bounds, 'sd.tracking_device_id', $deviceIds, [], []);
        $soldByBrowser = $this->soldMetricsByDimension($scope, $bounds, 'sd.tracking_browser_id', [], [], []);
        $soldByTraffic = $this->soldMetricsByDimension($scope, $bounds, 'sd.tracking_traffic_source_id', [], $utmSourceIds, $utmMediums);

        $performanceKeys = [
            'sold' => 'sold_qty',
            'proceed' => 'proceed_checkout',
            'adds' => 'add_to_cart',
            'views' => 'sessions',
            'revenue' => 'revenue',
        ];

        return [
            TrackingDailyAudience::TYPE_DEVICE => $this->sortByFunnelPerformance(
                $this->mapAudienceRows($deviceRows, $soldByDevice),
                $performanceKeys,
            ),
            TrackingDailyAudience::TYPE_BROWSER => $this->sortByFunnelPerformance(
                $this->mapAudienceRows($browserRows, $soldByBrowser, false),
                $performanceKeys,
            ),
            TrackingDailyAudience::TYPE_TRAFFIC_SOURCE => $this->sortByFunnelPerformance(
                $this->mapTrafficRows($trafficRows, $soldByTraffic),
                $performanceKeys,
            ),
        ];
    }

    /**
     * @return array<int|string, array{sold_qty: int, revenue: float}>
     */
    /**
     * @param  list<int>  $deviceIds
     * @param  list<int>  $utmSourceIds
     * @param  list<string>  $utmMediums
     */
    private function soldMetricsByDimension(
        \Closure $scope,
        array $bounds,
        string $dimensionColumn,
        array $deviceIds = [],
        array $utmSourceIds = [],
        array $utmMediums = [],
    ): array {
        $query = DB::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope);
        SessionTrafficAttribution::joinLatestSessionDetails($query);
        $query
            ->when($deviceIds !== [] && $dimensionColumn === 'sd.tracking_device_id', fn (Builder $q) => $q->whereIn('sd.tracking_device_id', $deviceIds))
            ->when($utmSourceIds !== [] && $dimensionColumn === 'sd.tracking_traffic_source_id', fn (Builder $q) => $q->whereIn('sd.tracking_traffic_source_id', $utmSourceIds))
            ->when($utmMediums !== [] && $dimensionColumn === 'sd.tracking_traffic_source_id', fn (Builder $q) => $q->whereIn('sd.tracking_traffic_source_id', fn (Builder $sources) => $sources
                ->select('id')
                ->from('tracking_traffic_source')
                ->whereIn('medium', $utmMediums)));
        $rows = $query
            ->whereBetween('o.ordered_at', $bounds)
            ->groupBy($dimensionColumn)
            ->selectRaw("{$dimensionColumn} as dimension_id")
            ->selectRaw('COALESCE(SUM(o.items_qty), 0) as sold_qty')
            ->selectRaw('COALESCE(SUM(o.amount_paid), 0) as revenue')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row->dimension_id ?? 0)] = [
                'sold_qty' => (int) $row->sold_qty,
                'revenue' => (float) $row->revenue,
            ];
        }

        return $map;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int|string, array{sold_qty: int, revenue: float}>  $sold
     * @return list<array<string, mixed>>
     */
    private function mapAudienceRows(Collection $rows, array $sold, bool $withViews = true): array
    {
        return $rows->map(function (object $row) use ($sold, $withViews) {
            $id = (int) ($row->dimension_id ?? 0);
            $sessions = (int) $row->sessions;
            $soldRow = $sold[$id] ?? ['sold_qty' => 0, 'revenue' => 0.0];

            return [
                'id' => $id ?: null,
                'label' => ucfirst((string) ($row->name ?? 'Unknown')),
                'source' => '',
                'medium' => '',
                'sessions' => $sessions,
                'views' => $withViews ? $sessions : 0,
                'add_to_cart' => (int) $row->add_to_cart,
                'begin_checkout' => (int) $row->begin_checkout,
                'proceed_checkout' => (int) $row->proceed_checkout,
                'payment_success' => (int) $row->payment_success,
                'sold_qty' => $soldRow['sold_qty'],
                'revenue' => $soldRow['revenue'],
                'conversion_rate' => $sessions > 0 ? round((int) $row->payment_success / $sessions * 100, 1) : 0,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int|string, array{sold_qty: int, revenue: float}>  $sold
     * @return list<array<string, mixed>>
     */
    private function mapTrafficRows(Collection $rows, array $sold): array
    {
        return $rows->map(function (object $row) use ($sold) {
            $id = (int) ($row->dimension_id ?? 0);
            $sessions = (int) $row->sessions;
            $soldRow = $sold[$id] ?? ['sold_qty' => 0, 'revenue' => 0.0];
            $source = (string) ($row->source ?? 'Other');

            return [
                'id' => $id ?: null,
                'label' => $source,
                'source' => $source,
                'medium' => (string) ($row->medium ?? ''),
                'sessions' => $sessions,
                'views' => $sessions,
                'add_to_cart' => (int) $row->add_to_cart,
                'begin_checkout' => (int) $row->begin_checkout,
                'proceed_checkout' => (int) $row->proceed_checkout,
                'payment_success' => (int) $row->payment_success,
                'sold_qty' => $soldRow['sold_qty'],
                'revenue' => $soldRow['revenue'],
                'conversion_rate' => $sessions > 0 ? round((int) $row->payment_success / $sessions * 100, 1) : 0,
            ];
        })->all();
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     * @return array{buckets: list<array{key: int, label: string, count: int, pct: float}>, total_sessions: int, median_label: string}
     */
    private function durations(array $range, array $filters): array
    {
        $scope = fn (Builder $query) => $this->activityList->constrainSessionsInRange($query, $range, $filters);

        $durations = DB::table('tracking_session as ts')
            ->tap($scope)
            ->orderBy('ts.duration_seconds')
            ->pluck('ts.duration_seconds');

        $total = $durations->count();
        $counts = array_fill_keys(array_keys(TrackingDailyDuration::BUCKETS), 0);

        foreach ($durations as $seconds) {
            $counts[TrackingDailyDuration::bucketFor((int) $seconds)]++;
        }

        $buckets = [];
        foreach (TrackingDailyDuration::BUCKETS as $bucket => $definition) {
            $count = (int) ($counts[$bucket] ?? 0);
            $buckets[] = [
                'key' => $bucket,
                'label' => $definition['label'],
                'count' => $count,
                'pct' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }

        $middle = $total === 0
            ? collect()
            : $durations->slice((int) floor(($total - 1) / 2), $total % 2 === 0 ? 2 : 1);

        return [
            'buckets' => $buckets,
            'total_sessions' => $total,
            'median_label' => format_duration((int) round((float) $middle->avg())),
        ];
    }

    /**
     * @return array<string, list<int|float>>
     */
    private function emptyTrendBucketData(): array
    {
        return [
            'unique_visitors' => [],
            'sessions' => [],
            'category_views' => [],
            'product_views' => [],
            'cart_sessions' => [],
            'checkout_sessions' => [],
            'proceed_sessions' => [],
            'payments' => [],
            'items_sold' => [],
            'conversion_rate' => [],
        ];
    }

    /**
     * @param  array<string, list<int|float>>  $data
     * @param  array<string, int>  $metrics
     */
    private function pushTrendBucket(array &$data, array $metrics): void
    {
        foreach ([
            'unique_visitors', 'sessions', 'category_views', 'product_views',
            'cart_sessions', 'checkout_sessions', 'proceed_sessions', 'payments', 'items_sold',
        ] as $column) {
            $data[$column][] = (int) ($metrics[$column] ?? 0);
        }

        $sessions = (int) ($metrics['sessions'] ?? 0);
        $purchaseSessions = (int) ($metrics['purchase_sessions'] ?? $metrics['payments'] ?? 0);
        $data['conversion_rate'][] = $sessions > 0
            ? round($purchaseSessions / $sessions * 100, 1)
            : 0;
    }

    /**
     * @param  array<string, list<int|float>>  $data
     * @return array<string, mixed>
     */
    private function formatTrendResponse(
        array $labels,
        array $data,
        string $bucket,
        int $totalDays,
        bool $useLogScale,
        string $rangeLabel,
    ): array {
        $series = [
            ['key' => 'unique_visitors', 'label' => 'Unique visitors', 'data' => $data['unique_visitors'], 'chart_type' => 'bar', 'y_axis_id' => 'y'],
            ['key' => 'sessions', 'label' => 'Sessions', 'data' => $data['sessions'], 'chart_type' => 'bar', 'y_axis_id' => 'y'],
            ['key' => 'category_views', 'label' => 'Category view', 'data' => $data['category_views'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'product_views', 'label' => 'Product view', 'data' => $data['product_views'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'add_to_cart', 'label' => 'Add to cart', 'data' => $data['cart_sessions'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'begin_checkout', 'label' => 'Begin checkout', 'data' => $data['checkout_sessions'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'proceed_checkout', 'label' => 'Proceed checkout', 'data' => $data['proceed_sessions'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'purchases', 'label' => 'Purchase', 'data' => $data['payments'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'items_sold_qty', 'label' => 'Items sold qty', 'data' => $data['items_sold'], 'chart_type' => 'line', 'y_axis_id' => 'y'],
            ['key' => 'conversion_rate', 'label' => 'Conv. rate %', 'data' => $data['conversion_rate'], 'chart_type' => 'line', 'y_axis_id' => 'y1'],
        ];

        return [
            'labels' => $labels,
            'series' => $series,
            'unique_visitors' => $data['unique_visitors'],
            'sessions' => $data['sessions'],
            'items_sold_qty' => $data['items_sold'],
            'conversion_rates' => $data['conversion_rate'],
            'bucket' => $bucket,
            'total_days' => $totalDays,
            'use_log_scale' => $useLogScale,
            'range_label' => $rangeLabel,
        ];
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon, 2: string}>
     */
    private function trendPeriods(Carbon $from, Carbon $to, string $bucket): array
    {
        $periods = [];
        $cursor = $from->copy();

        while ($cursor <= $to) {
            if ($bucket === 'month') {
                $periodStart = $cursor->copy();
                $periodEnd = $cursor->copy()->endOfMonth()->endOfDay();
                if ($periodEnd > $to) {
                    $periodEnd = $to->copy();
                }
                $label = $periodStart->format('M Y');
                $cursor = $periodEnd->copy()->addSecond()->startOfDay();
            } elseif ($bucket === 'week') {
                $periodStart = $cursor->copy();
                $periodEnd = $cursor->copy()->addDays(6)->endOfDay();
                if ($periodEnd > $to) {
                    $periodEnd = $to->copy();
                }
                $label = $periodStart->format('d M').' – '.$periodEnd->format('d M');
                $cursor = $periodEnd->copy()->addSecond()->startOfDay();
            } else {
                $periodStart = $cursor->copy()->startOfDay();
                $periodEnd = $cursor->copy()->endOfDay();
                $label = $cursor->format('d M');
                $cursor->addDay();
            }

            $periods[] = [$periodStart, $periodEnd, $label];
        }

        return $periods;
    }

    private function trendRangeLabel(int $totalDays, string $bucket, ?string $period = null, ?Carbon $dayLocal = null): string
    {
        return match ($bucket) {
            'hour' => match ($period) {
                'yesterday' => TrackerTime::yesterdayPresetLabel().' · hourly',
                '24h' => TrackerTime::todayPresetLabel().' · hourly',
                default => ($dayLocal?->format('d M Y') ?? '1 day').' · hourly',
            },
            'week' => "{$totalDays} days · weekly buckets",
            'month' => "{$totalDays} days · monthly buckets",
            default => "{$totalDays} days · daily",
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{sold: string, proceed: string, adds: string, views: string, revenue: string}  $fields
     * @return list<array<string, mixed>>
     */
    private function sortByFunnelPerformance(array $rows, array $fields): array
    {
        usort($rows, function (array $a, array $b) use ($fields): int {
            foreach (['sold', 'proceed', 'adds', 'views', 'revenue'] as $metric) {
                $key = $fields[$metric];
                $cmp = ((float) ($b[$key] ?? 0)) <=> ((float) ($a[$key] ?? 0));

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        return array_values($rows);
    }
}
