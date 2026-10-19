<?php

namespace App\Services;

use App\Models\TrackingDailyAudience;
use App\Models\TrackingDailyDuration;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Store performance dashboard, read only from tracking_* tables (daily rollups filled by dashboard sync).
 */
class EcomTrackerDashboardService
{
    public function __construct(
        private EcomActivityListService $activityList,
    ) {}

    private const PRODUCT_LIMIT = 10;

    private const RECOVERABLE_ROW_LIMIT = 20;

    private const TREND_LOG_SCALE_DAYS = 31;

    private const TREND_WEEKLY_THRESHOLD_DAYS = 32;

    private const TREND_MONTHLY_THRESHOLD_DAYS = 91;

    /** latest_funnel_stage => panel; the stage is also the activity page funnel filter */
    private const RECOVERABLE_PANELS = [
        3 => ['panelId' => 'cart-abandon', 'title' => 'Cart abandoned', 'tone' => 'cart', 'emptyMessage' => 'No cart abandonment in this period.'],
        4 => ['panelId' => 'begin-checkout-abandon', 'title' => 'Begin checkout abandoned', 'tone' => 'begin', 'emptyMessage' => 'No begin checkout abandonment in this period.'],
        5 => ['panelId' => 'proceed-checkout-abandon', 'title' => 'Proceed checkout abandoned', 'tone' => 'proceed', 'emptyMessage' => 'No proceed checkout abandonment in this period.'],
        6 => ['panelId' => 'payment-success-events', 'title' => 'Payment success', 'tone' => 'success', 'emptyMessage' => 'No payment success events in this period.'],
    ];

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
        $rangeWithPeriod = $range + ['period' => $period];

        $current = $this->activityList->dashboardTotals($rangeWithPeriod, []);
        $previous = $this->activityList->dashboardTotals($prevRange, []);

        $audiences = $this->audiences($from, $to);
        $returning = max(0, $current['sessions'] - $current['unique_visitors']);

        return [
            'kpiGroups' => $this->kpiGroups($current, $previous, $this->comparisonLabel($period, $prevFrom, $prevTo)),
            'categories' => $this->categories($range['from'], $range['to'], $period, []),
            'products' => $this->products($range['from'], $range['to'], $period, []),
            'recoverable' => $this->recoverable($from, $to, $backUrl),
            'devices' => [
                'by_device' => $audiences[TrackingDailyAudience::TYPE_DEVICE] ?? [],
                'by_browser' => $audiences[TrackingDailyAudience::TYPE_BROWSER] ?? [],
            ],
            'traffic' => $audiences[TrackingDailyAudience::TYPE_TRAFFIC_SOURCE] ?? [],
            'durations' => $this->durations($from, $to),
            'newReturning' => ['unique' => $current['unique_visitors'], 'returning' => $returning],
            'charts' => [
                'trend' => $this->buildTrend($summaries, $range, $days, $period),
                'new_returning' => [
                    'labels' => ['Unique', 'Returning'],
                    'values' => [$current['unique_visitors'], $returning],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<string, object>  $rows
     * @return array<string, float|int>
     */
    private function totals(Collection $rows, int $uniqueVisitors): array
    {
        $totals = ['unique_visitors' => $uniqueVisitors];

        foreach ([
            'sessions', 'stay_seconds', 'cart_sessions', 'checkout_sessions', 'proceed_sessions',
            'cart_drops', 'checkout_drops', 'proceed_drops', 'payments', 'items_sold',
        ] as $column) {
            $totals[$column] = (int) $rows->sum($column);
        }

        $totals['sale_amount'] = round((float) $rows->sum('sale_amount'), 2);
        $totals['avg_stay_seconds'] = $totals['sessions'] > 0 ? (int) round($totals['stay_seconds'] / $totals['sessions']) : 0;

        return $totals;
    }

    /**
     * Each metric carries the activity page filters it drills down to.
     *
     * @param  array<string, float|int>  $current
     * @param  array<string, float|int>  $previous
     * @return list<array{title: string, modifier: string, cols: int, metrics: list<array<string, mixed>>}>
     */
    private function kpiGroups(array $current, array $previous, string $comparisonLabel): array
    {
        $metric = fn (string $label, string $key, callable $format, string $tip, array $activity = []) => [
            'label' => $label,
            'formatted' => $format($current[$key]),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => $this->comparison($current[$key], $previous[$key], $format($previous[$key]), $comparisonLabel),
        ];

        $count = fn (float|int $value) => number_format($value);
        $duration = fn (float|int $value) => format_duration((int) $value);

        $currentFunnel = $this->funnelKpisFromTotals($current);
        $previousFunnel = $this->funnelKpisFromTotals($previous);

        $payments = $this->funnelDropMetric(
            'Payments',
            $currentFunnel['payment_rate'],
            $currentFunnel['payment_count'],
            $previousFunnel['payment_rate'],
            $previousFunnel['payment_count'],
            'Share of sessions with at least one payment; count is completed orders (multiple orders in one session each count).',
            $comparisonLabel,
            ['funnel' => 6],
        );
        $payments['value_class'] = 'etd-kpi-value--success';

        return [
            ['title' => 'Audience & engagement', 'modifier' => 'etd-kpi-group--audience', 'cols' => 4, 'metrics' => [
                $metric('Unique visitors', 'unique_visitors', $count, 'Distinct visitor IDs among sessions in the selected period (same session rules as User activity).'),
                $metric('Sessions', 'sessions', $count, 'Sessions in the selected period using the same date rules as User activity.'),
                $metric('Total stay time', 'stay_seconds', $duration, 'Sum of session Duration values for sessions in the period (same as User activity Duration column).'),
                $metric('Avg stay time', 'avg_stay_seconds', $duration, 'Average Duration per session in the period (total stay time divided by session count).'),
            ]],
            ['title' => 'Sale & conversion', 'modifier' => 'etd-kpi-group--sale', 'cols' => 2, 'metrics' => [
                $metric('Items sold', 'items_sold', $count, 'Total product units sold from completed orders in the period (sums line-item quantities from payment_success events in the date range).', ['has_order' => 1]),
                $metric('Sale amount', 'sale_amount', fn (float|int $value) => '£'.number_format((float) $value, 2), 'Total sale amount from completed orders in the period (sum of payment_success amount_paid in the date range).', ['has_order' => 1]),
            ]],
            ['title' => 'Funnel drop-off', 'modifier' => 'etd-kpi-group--funnel', 'cols' => 4, 'metrics' => [
                $this->funnelDropMetric(
                    'Cart drop',
                    $currentFunnel['cart_drop_rate'],
                    $currentFunnel['cart_drop_count'],
                    $previousFunnel['cart_drop_rate'],
                    $previousFunnel['cart_drop_count'],
                    'Sessions that added to cart but did not begin checkout (matches Cart abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 3],
                ),
                $this->funnelDropMetric(
                    'Checkout drop',
                    $currentFunnel['checkout_drop_rate'],
                    $currentFunnel['checkout_drop_count'],
                    $previousFunnel['checkout_drop_rate'],
                    $previousFunnel['checkout_drop_count'],
                    'Sessions that began checkout but did not proceed (matches Begin checkout abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 4],
                ),
                $this->funnelDropMetric(
                    'Proceed drop',
                    $currentFunnel['proceed_drop_rate'],
                    $currentFunnel['proceed_drop_count'],
                    $previousFunnel['proceed_drop_rate'],
                    $previousFunnel['proceed_drop_count'],
                    'Sessions that proceeded to checkout but did not pay (matches Proceed checkout abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 5],
                ),
                $payments,
            ]],
        ];
    }

    /**
     * @param  array<string, float|int>  $totals
     * @return array<string, float|int>
     */
    private function funnelKpisFromTotals(array $totals): array
    {
        $sessions = (int) ($totals['sessions'] ?? 0);
        $cartStage = (int) ($totals['cart_sessions'] ?? 0);
        $checkoutStage = (int) ($totals['checkout_sessions'] ?? 0);
        $proceedStage = (int) ($totals['proceed_sessions'] ?? 0);
        $payments = (int) ($totals['payments'] ?? 0);
        $purchaseSessions = (int) ($totals['purchase_sessions'] ?? $payments);
        $cartAbandoned = (int) ($totals['cart_drops'] ?? 0);
        $checkoutAbandoned = (int) ($totals['checkout_drops'] ?? 0);
        $proceedAbandoned = (int) ($totals['proceed_drops'] ?? 0);

        return [
            'cart_drop_rate' => $cartStage > 0 ? round($cartAbandoned / $cartStage * 100, 1) : 0.0,
            'cart_drop_count' => $cartAbandoned,
            'checkout_drop_rate' => $checkoutStage > 0 ? round($checkoutAbandoned / $checkoutStage * 100, 1) : 0.0,
            'checkout_drop_count' => $checkoutAbandoned,
            'proceed_drop_rate' => $proceedStage > 0 ? round($proceedAbandoned / $proceedStage * 100, 1) : 0.0,
            'proceed_drop_count' => $proceedAbandoned,
            'payment_rate' => $sessions > 0 ? round($purchaseSessions / $sessions * 100, 1) : 0.0,
            'payment_count' => $payments,
        ];
    }

    private function formatFunnelDropValue(float $rate, int $count): string
    {
        return number_format($rate, 1).'% / '.number_format($count);
    }

    /**
     * @return array<string, mixed>
     */
    private function funnelDropMetric(
        string $label,
        float $currentRate,
        int $currentCount,
        float $previousRate,
        int $previousCount,
        string $tip,
        string $comparisonLabel,
        array $activity = [],
    ): array {
        return [
            'label' => $label,
            'formatted' => $this->formatFunnelDropValue($currentRate, $currentCount),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => $this->comparison(
                $currentRate,
                $previousRate,
                $this->formatFunnelDropValue($previousRate, $previousCount),
                $comparisonLabel,
            ),
        ];
    }

    /**
     * @return array{items_sold: int, sale_amount: float}
     */
    private function saleTotalsFromOrders(string $from, string $to): array
    {
        $row = TrackerDatabase::table('tracking_orders')
            ->whereBetween('metric_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(items_qty), 0) as items_sold')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as sale_amount')
            ->first();

        return [
            'items_sold' => (int) ($row->items_sold ?? 0),
            'sale_amount' => round((float) ($row->sale_amount ?? 0), 2),
        ];
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: string, delta_label: null, previous_formatted: string, comparison_label: string}
     */
    private function comparison(float|int $current, float|int $previous, string $previousFormatted, string $comparisonLabel): array
    {
        [$deltaPct, $direction] = match (true) {
            $previous == 0 => $current > 0 ? [null, 'up'] : [0.0, 'flat'],
            $current == 0 => [-100.0, 'down'],
            default => [round(($current - $previous) / $previous * 100, 1), $current > $previous ? 'up' : ($current < $previous ? 'down' : 'flat')],
        };

        return [
            'delta_pct' => $deltaPct,
            'delta_direction' => $direction,
            'delta_label' => null,
            'previous_formatted' => $previousFormatted,
            'comparison_label' => $comparisonLabel,
        ];
    }

    private function comparisonLabel(string $period, Carbon $prevFrom, Carbon $prevTo): string
    {
        return match (true) {
            $period === '24h' => 'yesterday',
            $period === '7d' => 'previous 7 days',
            $period === '30d' => 'previous 30 days',
            $prevFrom->isSameDay($prevTo) => $prevFrom->format('d M Y'),
            default => $prevFrom->format('d M').' – '.$prevTo->format('d M Y'),
        };
    }

    /**
     * @param  Collection<string, object>  $summaries
     * @param  array{from: Carbon, to: Carbon}  $range
     * @return array<string, mixed>
     */
    private function buildTrend(Collection $summaries, array $range, int $days, string $period): array
    {
        $fromLocal = TrackerTime::toLocal($range['from'])?->copy()->startOfDay()
            ?? $range['from']->copy()->startOfDay();
        $toLocalDay = TrackerTime::toLocal($range['to'])?->copy()->startOfDay()
            ?? $range['to']->copy()->startOfDay();

        if ($days === 1) {
            return $this->hourlyTrendFromTable($fromLocal->toDateString(), $period, $fromLocal);
        }

        $toLocal = TrackerTime::toLocal($range['to'])?->copy()->endOfDay() ?? $range['to']->copy()->endOfDay();
        $bucket = match (true) {
            $days >= self::TREND_MONTHLY_THRESHOLD_DAYS => 'month',
            $days >= self::TREND_WEEKLY_THRESHOLD_DAYS => 'week',
            default => 'day',
        };
        $periodBuckets = $this->trendPeriods($fromLocal, $toLocal, $bucket);
        $rangeSummaries = $summaries->filter(
            fn (object $row, string $date) => $date >= $fromLocal->toDateString() && $date <= $toLocalDay->toDateString(),
        );

        $labels = [];
        $data = $this->emptyTrendBucketData();
        $trendSupport = $this->prefetchTrendSupportData($range + ['period' => $period]);

        foreach ($periodBuckets as [$periodStart, $periodEnd, $label]) {
            $labels[] = $label;
            $bucketMetrics = $this->aggregateDailyTrendBucket(
                $rangeSummaries,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                $trendSupport,
            );
            $this->pushTrendBucket($data, $bucketMetrics);
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
     * @return array<string, mixed>
     */
    private function hourlyTrendFromTable(string $date, string $period, Carbon $dayLocal): array
    {
        $rows = TrackerDatabase::table('tracking_hourly_summaries')
            ->where('metric_date', $date)
            ->get()
            ->keyBy(fn (object $row) => (int) $row->metric_hour);

        $purchaseSessionsByHour = TrackerDatabase::table('tracking_orders')
            ->where('metric_date', $date)
            ->get(['tracking_session_id', 'ordered_at'])
            ->groupBy(fn (object $order) => (int) Carbon::parse($order->ordered_at)->timezone(TrackerTime::timezone())->format('G'))
            ->map(fn (Collection $orders) => $orders->pluck('tracking_session_id')->unique()->count());

        $labels = [];
        $data = $this->emptyTrendBucketData();

        for ($hour = 0; $hour < 24; $hour++) {
            $row = $rows->get($hour);
            $labels[] = sprintf('%02d:00', $hour);
            $this->pushTrendBucket($data, [
                'unique_visitors' => (int) ($row?->unique_visitors ?? 0),
                'sessions' => (int) ($row?->sessions ?? 0),
                'category_views' => (int) ($row?->category_views ?? 0),
                'product_views' => (int) ($row?->product_views ?? 0),
                'cart_sessions' => (int) ($row?->cart_sessions ?? 0),
                'checkout_sessions' => (int) ($row?->checkout_sessions ?? 0),
                'proceed_sessions' => (int) ($row?->proceed_sessions ?? 0),
                'payments' => (int) ($row?->payments ?? 0),
                'items_sold' => (int) ($row?->items_sold ?? 0),
                'purchase_sessions' => (int) ($purchaseSessionsByHour->get($hour) ?? 0),
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
     * One round trip for trend buckets (avoids per-day visitor / purchase queries).
     *
     * @return array{
     *     visitors_by_date: Collection<string, Collection<int, int>>,
     *     purchase_session_ids_by_date: Collection<string, Collection<int, int>>
     * }
     */
    /**
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     */
    private function prefetchTrendSupportData(array $range): array
    {
        $visitorsByDate = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->select(['dv.visit_date as session_date', 'ts.tracking_daily_visitor_id'])
            ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope(
                $query,
                $range['from'],
                $range['to'],
                $range['period'] ?? null,
                'ts',
            ))
            ->get()
            ->groupBy(fn (object $row) => (string) $row->session_date)
            ->map(fn (Collection $rows) => $rows->pluck('tracking_daily_visitor_id')->map(fn ($id) => (int) $id));

        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();

        $purchaseSessionIdsByDate = TrackerDatabase::table('tracking_orders')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->select(['metric_date', 'tracking_session_id'])
            ->get()
            ->groupBy(fn (object $row) => (string) $row->metric_date)
            ->map(fn (Collection $rows) => $rows->pluck('tracking_session_id')->map(fn ($id) => (int) $id));

        return [
            'visitors_by_date' => $visitorsByDate,
            'purchase_session_ids_by_date' => $purchaseSessionIdsByDate,
        ];
    }

    /**
     * @param  Collection<string, object>  $summaries
     * @param  array{
     *     visitors_by_date: Collection<string, Collection<int, int>>,
     *     purchase_session_ids_by_date: Collection<string, Collection<int, int>>
     * }  $trendSupport
     * @return array<string, int>
     */
    private function aggregateDailyTrendBucket(
        Collection $summaries,
        string $fromDate,
        string $toDate,
        array $trendSupport,
    ): array {
        $metrics = [
            'sessions' => 0,
            'category_views' => 0,
            'product_views' => 0,
            'cart_sessions' => 0,
            'checkout_sessions' => 0,
            'proceed_sessions' => 0,
            'payments' => 0,
            'items_sold' => 0,
        ];

        foreach ($summaries as $date => $row) {
            if ($date < $fromDate || $date > $toDate) {
                continue;
            }

            $metrics['sessions'] += (int) ($row->sessions ?? 0);
            $metrics['category_views'] += (int) ($row->category_views ?? 0);
            $metrics['product_views'] += (int) ($row->product_views ?? 0);
            $metrics['cart_sessions'] += (int) ($row->cart_sessions ?? 0);
            $metrics['checkout_sessions'] += (int) ($row->checkout_sessions ?? 0);
            $metrics['proceed_sessions'] += (int) ($row->proceed_sessions ?? 0);
            $metrics['payments'] += (int) ($row->payments ?? 0);
            $metrics['items_sold'] += (int) ($row->items_sold ?? 0);
        }

        $visitorIds = collect();
        foreach ($trendSupport['visitors_by_date'] as $date => $ids) {
            if ($date >= $fromDate && $date <= $toDate) {
                $visitorIds = $visitorIds->merge($ids);
            }
        }

        $purchaseSessionIds = collect();
        foreach ($trendSupport['purchase_session_ids_by_date'] as $date => $ids) {
            if ($date >= $fromDate && $date <= $toDate) {
                $purchaseSessionIds = $purchaseSessionIds->merge($ids);
            }
        }

        return [
            'unique_visitors' => $visitorIds->unique()->count(),
            'sessions' => $metrics['sessions'],
            'category_views' => $metrics['category_views'],
            'product_views' => $metrics['product_views'],
            'cart_sessions' => $metrics['cart_sessions'],
            'checkout_sessions' => $metrics['checkout_sessions'],
            'proceed_sessions' => $metrics['proceed_sessions'],
            'payments' => $metrics['payments'],
            'items_sold' => $metrics['items_sold'],
            'purchase_sessions' => $purchaseSessionIds->unique()->count(),
        ];
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
     * Category / product view totals for trend buckets (tracking daily rollups, scaled when session filters apply).
     *
     * @param  array<string, mixed>  $sessionFilters
     * @return array{category_views: int, product_views: int}
     */
    public function summaryViewTotals(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $rows = TrackerDatabase::table('tracking_daily_summaries')
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        if (! EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return [
                'category_views' => (int) $rows->sum('category_views'),
                'product_views' => (int) $rows->sum('product_views'),
            ];
        }

        $ratios = $this->sessionFilterRatiosByDate($from, $to, $period, $sessionFilters);
        $categoryViews = 0;
        $productViews = 0;

        foreach ($rows as $row) {
            $ratio = $ratios[(string) $row->metric_date] ?? 0.0;
            $categoryViews += (int) round((int) $row->category_views * $ratio);
            $productViews += (int) round((int) $row->product_views * $ratio);
        }

        return ['category_views' => $categoryViews, 'product_views' => $productViews];
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array<string, float> metric_date => ratio in [0, 1]
     */
    private function sessionFilterRatiosByDate(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $totalsByDate = TrackerDatabase::table('tracking_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->pluck('sessions', 'metric_date');

        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $filteredByDate = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->selectRaw('dv.visit_date as session_date, COUNT(*) as sessions')
            ->tap(fn ($query) => $this->activityList->constrainSessionsInRange($query, $range, $sessionFilters))
            ->groupBy('dv.visit_date')
            ->pluck('sessions', 'session_date');

        $ratios = [];
        foreach ($totalsByDate as $date => $totalSessions) {
            $total = (int) $totalSessions;
            $filtered = (int) ($filteredByDate[$date] ?? 0);
            $ratios[(string) $date] = $total > 0 ? min(1.0, $filtered / $total) : 0.0;
        }

        return $ratios;
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{departments: list<array<string, mixed>>, totals: array{category_views: int, product_views: int, category_count: int}}
     */
    public function categories(Carbon $from, Carbon $to, string $period, array $sessionFilters = []): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return $this->categoriesWithSessionFilters($from, $to, $period, $sessionFilters);
        }

        $rows = TrackerDatabase::table('tracking_daily_categories as dc')
            ->join('tracking_category as c', 'c.id', '=', 'dc.tracking_category_id')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->whereBetween('dc.metric_date', [$fromDate, $toDate])
            ->groupBy('c.id', 'c.name', 'd.id', 'd.name')
            ->selectRaw('c.id, c.name, d.id as department_id, d.name as department_name')
            ->selectRaw('SUM(dc.category_views) as category_views, SUM(dc.product_views) as product_views, SUM(dc.add_to_carts) as adds')
            ->selectRaw('SUM(dc.proceed_checkouts) as proceed_checkouts, SUM(dc.sold_qty) as sale_items, SUM(dc.sale_amount) as sale_amount')
            ->get();

        $soldByCategory = $this->scopedCategorySoldTotals($from, $to, $period, []);

        $metrics = ['category_views', 'product_views', 'adds', 'proceed_checkouts', 'sale_items', 'sale_amount'];
        $sort = fn (array $a, array $b) => [$b['sale_amount'], $b['product_views']] <=> [$a['sale_amount'], $a['product_views']];

        $departments = [];
        foreach ($rows as $row) {
            $departmentId = (int) ($row->department_id ?? 0);
            $categoryId = (int) $row->id;
            $sold = $soldByCategory[$categoryId] ?? ['sale_items' => (int) $row->sale_items, 'sale_amount' => (float) $row->sale_amount];

            $category = [
                'category_id' => $categoryId,
                'category_name' => (string) $row->name,
                'department_id' => $departmentId ?: null,
                'category_views' => (int) $row->category_views,
                'product_views' => (int) $row->product_views,
                'adds' => (int) $row->adds,
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'sale_items' => (int) $sold['sale_items'],
                'sale_amount' => (float) $sold['sale_amount'],
            ];

            $departments[$departmentId] ??= ['key' => 'department-'.$departmentId, 'name' => (string) ($row->department_name ?? 'Other'), 'categories' => []] + array_fill_keys($metrics, 0);
            $departments[$departmentId]['categories'][] = $category;
            foreach ($metrics as $metric) {
                $departments[$departmentId][$metric] += $category[$metric];
            }
        }

        foreach ($departments as &$department) {
            usort($department['categories'], $sort);
            $department['category_count'] = count($department['categories']);
        }
        unset($department);
        usort($departments, $sort);

        return [
            'departments' => $departments,
            'totals' => [
                'category_views' => (int) $rows->sum('category_views'),
                'product_views' => (int) $rows->sum('product_views'),
                'category_count' => $rows->count(),
            ],
        ];
    }

    /**
     * Top products from tracking_daily_products (sold totals respect session scope via tracking_orders).
     *
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    public function products(Carbon $from, Carbon $to, string $period, array $sessionFilters = [], ?int $limit = self::PRODUCT_LIMIT): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return $this->productsWithSessionFilters($from, $to, $period, $sessionFilters, $limit);
        }

        $aggregated = TrackerDatabase::table('tracking_daily_products')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->groupBy('product_code')
            ->selectRaw('product_code as code')
            ->selectRaw('SUM(product_views) as views, SUM(add_to_carts) as adds, SUM(begin_checkouts) as begin_checkouts')
            ->selectRaw('SUM(proceed_checkouts) as proceed_checkouts')
            ->selectRaw('SUM(sold_qty) as qty, SUM(sale_amount) as revenue')
            ->get()
            ->keyBy('code');

        $all = $aggregated->map(function (object $row) {
            $code = (string) $row->code;

            return [
                'code' => $code,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'begin_checkouts' => (int) ($row->begin_checkouts ?? 0),
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'qty' => (int) $row->qty,
                'revenue' => round((float) $row->revenue, 2),
            ];
        });

        return $this->packageProductRows($all, $limit, $from, $to, $period, []);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    private function packageProductRows(
        Collection $all,
        ?int $limit,
        Carbon $from,
        Carbon $to,
        string $period,
        array $sessionFilters,
    ): array {
        $range = ['from' => $from, 'to' => $to, 'period' => $period];
        $sorted = $this->sortProductRows($all);
        $visible = $limit === null ? $sorted : $sorted->take($limit);

        $titles = TrackerDatabase::table('tracking_product')
            ->whereIn('code', $visible->pluck('code')->all())
            ->pluck('title', 'code');

        $maxRevenue = max(1.0, (float) $visible->max('revenue'));

        return [
            'rows' => $visible->map(function (array $row) use ($titles, $maxRevenue) {
                $code = $row['code'];

                return [
                    'code' => $code,
                    'name' => (string) ($titles[$code] ?? $code),
                    'views' => (int) ($row['views'] ?? 0),
                    'add_to_cart' => (int) ($row['adds'] ?? 0),
                    'begin_checkout' => (int) ($row['begin_checkouts'] ?? 0),
                    'proceed_checkout' => (int) ($row['proceed_checkouts'] ?? 0),
                    'paid' => (int) ($row['qty'] ?? 0),
                    'revenue' => $row['revenue'],
                    'revenue_bar_percent' => (int) round($row['revenue'] / $maxRevenue * 100),
                ];
            })->values()->all(),
            'totals' => [
                'views' => (int) $all->sum('views'),
                'product_count' => $all->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductRows(Collection $all): Collection
    {
        return $all
            ->sortBy([
                ['qty', 'desc'],
                ['revenue', 'desc'],
                ['views', 'desc'],
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{departments: list<array<string, mixed>>, totals: array{category_views: int, product_views: int, category_count: int}}
     */
    private function categoriesWithSessionFilters(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $ratios = $this->sessionFilterRatiosByDate($from, $to, $period, $sessionFilters);
        $dailyRows = TrackerDatabase::table('tracking_daily_categories as dc')
            ->join('tracking_category as c', 'c.id', '=', 'dc.tracking_category_id')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->whereBetween('dc.metric_date', [$from->toDateString(), $to->toDateString()])
            ->get([
                'dc.metric_date',
                'c.id',
                'c.name',
                'd.id as department_id',
                'd.name as department_name',
                'dc.category_views',
                'dc.product_views',
                'dc.add_to_carts',
                'dc.proceed_checkouts',
            ]);

        $soldByCategory = $this->scopedCategorySoldTotals($from, $to, $period, $sessionFilters);
        $metrics = ['category_views', 'product_views', 'adds', 'proceed_checkouts', 'sale_items', 'sale_amount'];
        $sort = fn (array $a, array $b) => [$b['sale_amount'], $b['product_views']] <=> [$a['sale_amount'], $a['product_views']];

        $categoryMap = [];

        foreach ($dailyRows as $row) {
            $ratio = $ratios[(string) $row->metric_date] ?? 0.0;
            if ($ratio <= 0) {
                continue;
            }

            $categoryId = (int) $row->id;
            $departmentId = (int) ($row->department_id ?? 0);

            $categoryMap[$categoryId] ??= [
                'category_id' => $categoryId,
                'category_name' => (string) $row->name,
                'department_id' => $departmentId ?: null,
                'department_name' => (string) ($row->department_name ?? 'Other'),
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0.0,
            ];

            $categoryMap[$categoryId]['category_views'] += (int) round((int) $row->category_views * $ratio);
            $categoryMap[$categoryId]['product_views'] += (int) round((int) $row->product_views * $ratio);
            $categoryMap[$categoryId]['adds'] += (int) round((int) $row->add_to_carts * $ratio);
            $categoryMap[$categoryId]['proceed_checkouts'] += (int) round((int) $row->proceed_checkouts * $ratio);
        }

        foreach ($soldByCategory as $categoryId => $sold) {
            $categoryId = (int) $categoryId;
            $categoryMap[$categoryId] ??= [
                'category_id' => $categoryId,
                'category_name' => 'Category #'.$categoryId,
                'department_id' => null,
                'department_name' => 'Other',
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0.0,
            ];
            $categoryMap[$categoryId]['sale_items'] = (int) $sold['sale_items'];
            $categoryMap[$categoryId]['sale_amount'] = (float) $sold['sale_amount'];
        }

        $departments = [];
        foreach ($categoryMap as $category) {
            $departmentId = (int) ($category['department_id'] ?? 0);
            $departments[$departmentId] ??= [
                'key' => 'department-'.$departmentId,
                'name' => (string) $category['department_name'],
                'categories' => [],
            ] + array_fill_keys($metrics, 0);
            unset($category['department_name']);
            $departments[$departmentId]['categories'][] = $category;
            foreach ($metrics as $metric) {
                $departments[$departmentId][$metric] += $category[$metric];
            }
        }

        $categoryTotals = ['category_views' => 0, 'product_views' => 0, 'category_count' => count($categoryMap)];
        foreach ($departments as &$department) {
            usort($department['categories'], $sort);
            $department['category_count'] = count($department['categories']);
            $categoryTotals['category_views'] += (int) $department['category_views'];
            $categoryTotals['product_views'] += (int) $department['product_views'];
        }
        unset($department);
        usort($departments, $sort);

        return ['departments' => array_values($departments), 'totals' => $categoryTotals];
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    private function productsWithSessionFilters(Carbon $from, Carbon $to, string $period, array $sessionFilters, ?int $limit = self::PRODUCT_LIMIT): array
    {
        $ratios = $this->sessionFilterRatiosByDate($from, $to, $period, $sessionFilters);
        $dailyRows = TrackerDatabase::table('tracking_daily_products')
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $byCode = [];
        foreach ($dailyRows as $row) {
            $ratio = $ratios[(string) $row->metric_date] ?? 0.0;
            if ($ratio <= 0) {
                continue;
            }

            $code = (string) $row->product_code;
            $byCode[$code] ??= [
                'code' => $code,
                'views' => 0,
                'adds' => 0,
                'begin_checkouts' => 0,
                'proceed_checkouts' => 0,
                'qty' => 0,
                'revenue' => 0.0,
            ];
            $byCode[$code]['views'] += (int) round((int) $row->product_views * $ratio);
            $byCode[$code]['adds'] += (int) round((int) $row->add_to_carts * $ratio);
            $byCode[$code]['begin_checkouts'] += (int) round((int) ($row->begin_checkouts ?? 0) * $ratio);
            $byCode[$code]['proceed_checkouts'] += (int) round((int) $row->proceed_checkouts * $ratio);
            $byCode[$code]['qty'] += (int) round((int) $row->sold_qty * $ratio);
            $byCode[$code]['revenue'] += round((float) $row->sale_amount * $ratio, 2);
        }

        $all = collect($byCode)->values();

        return $this->packageProductRows($all, $limit, $from, $to, $period, $sessionFilters);
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     */
    private function scopedCategorySoldTotals(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        [$fromBound, $toBound] = TrackerTime::storageRange($from, $to);
        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $rows = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->whereBetween('o.ordered_at', [$fromBound, $toBound])
            ->whereNotNull('od.tracking_category_id')
            ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 'ts'))
            ->when(
                EcomActivityListService::hasActiveFilters($sessionFilters),
                fn ($query) => $this->activityList->constrainSessionsInRange($query, $range, $sessionFilters),
            )
            ->groupBy('od.tracking_category_id')
            ->selectRaw('od.tracking_category_id as category_id')
            ->selectRaw('SUM(od.qty) as sale_items, SUM(od.line_total) as sale_amount')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->category_id] = [
                'sale_items' => (int) $row->sale_items,
                'sale_amount' => round((float) $row->sale_amount, 2),
            ];
        }

        return $map;
    }

    /**
     * The four recoverable panels: totals per type and its latest rows, in one query.
     *
     * @return list<array<string, mixed>>
     */
    private function recoverable(string $from, string $to, string $backUrl): array
    {
        $ranked = TrackerDatabase::table('tracking_recoverable_sales as rs')
            ->join('tracking_session as ts', 'ts.id', '=', 'rs.tracking_session_id')
            ->whereBetween('rs.metric_date', [$from, $to])
            ->select('rs.type', 'rs.qty', 'rs.sale_value', 'rs.occurred_at', 'ts.session_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY rs.type ORDER BY rs.occurred_at DESC, rs.id DESC) as row_no')
            ->selectRaw('COUNT(*) OVER (PARTITION BY rs.type) as total_count')
            ->selectRaw('SUM(rs.sale_value) OVER (PARTITION BY rs.type) as total_value');

        $rows = TrackerDatabase::query()
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
     * Device, browser and traffic source rows for the range, keyed by audience type (sorted sold → proceed → cart → views → revenue).
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function audiences(string $from, string $to): array
    {
        $rows = TrackerDatabase::table('tracking_daily_audiences as a')
            ->leftJoin('tracking_device as dv', fn ($join) => $join->on('dv.id', '=', 'a.dimension_id')->where('a.type', TrackingDailyAudience::TYPE_DEVICE))
            ->leftJoin('tracking_browser as br', fn ($join) => $join->on('br.id', '=', 'a.dimension_id')->where('a.type', TrackingDailyAudience::TYPE_BROWSER))
            ->whereBetween('a.metric_date', [$from, $to])
            ->groupBy('a.type', 'a.dimension_id', 'a.source', 'a.medium', 'dv.name', 'br.name')
            ->selectRaw('a.type, a.dimension_id, a.source, a.medium, COALESCE(dv.name, br.name) as name')
            ->selectRaw('SUM(a.sessions) as sessions, SUM(a.views) as views, SUM(a.add_to_carts) as add_to_cart')
            ->selectRaw('SUM(a.begin_checkouts) as begin_checkout, SUM(a.proceed_checkouts) as proceed_checkout')
            ->selectRaw('SUM(a.payments) as payment_success, SUM(a.sold_qty) as sold_qty, SUM(a.sale_amount) as revenue')
            ->get();

        $performanceKeys = [
            'sold' => 'sold_qty',
            'proceed' => 'proceed_checkout',
            'adds' => 'add_to_cart',
            'views' => 'views',
            'revenue' => 'revenue',
        ];

        return $rows
            ->groupBy('type')
            ->map(fn (Collection $typeRows) => $this->sortByFunnelPerformance(
                $typeRows->map(fn (object $row) => [
                    'id' => (int) $row->dimension_id ?: null,
                    'label' => ucfirst((string) ($row->name ?? 'Unknown')),
                    'source' => (string) $row->source,
                    'medium' => (string) $row->medium,
                    'sessions' => (int) $row->sessions,
                    'views' => (int) $row->views,
                    'add_to_cart' => (int) $row->add_to_cart,
                    'begin_checkout' => (int) $row->begin_checkout,
                    'proceed_checkout' => (int) $row->proceed_checkout,
                    'payment_success' => (int) $row->payment_success,
                    'sold_qty' => (int) $row->sold_qty,
                    'revenue' => (float) $row->revenue,
                    'conversion_rate' => (int) $row->sessions > 0 ? round((int) $row->payment_success / (int) $row->sessions * 100, 1) : 0,
                ])->all(),
                $performanceKeys,
            ))
            ->all();
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

    /**
     * Bucket counts from the daily table; the median needs the middle session(s) of the range.
     *
     * @return array{buckets: list<array{key: int, label: string, count: int, pct: float}>, total_sessions: int, median_label: string}
     */
    private function durations(string $from, string $to): array
    {
        $counts = TrackerDatabase::table('tracking_daily_durations')
            ->whereBetween('metric_date', [$from, $to])
            ->groupBy('bucket')
            ->selectRaw('bucket, SUM(sessions) as sessions')
            ->pluck('sessions', 'bucket');

        $total = (int) $counts->sum();
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

        $middle = $total === 0 ? collect() : TrackerDatabase::table('tracking_daily_visitor as dv')
            ->join('tracking_session as ts', 'ts.tracking_daily_visitor_id', '=', 'dv.id')
            ->whereBetween('dv.visit_date', [$from, $to])
            ->orderBy('ts.duration_seconds')
            ->offset(intdiv($total - 1, 2))
            ->limit($total % 2 === 0 ? 2 : 1)
            ->pluck('ts.duration_seconds');

        return [
            'buckets' => $buckets,
            'total_sessions' => $total,
            'median_label' => format_duration((int) round((float) $middle->avg())),
        ];
    }
}
