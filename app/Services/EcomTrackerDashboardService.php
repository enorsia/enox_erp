<?php

namespace App\Services;

use App\Models\TrackingDailyAudience;
use App\Models\TrackingDailyDuration;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Store performance dashboard, read only from the daily tracking_* tables filled by the dashboard sync
 * (one small query per panel, no per-session scans). Row shapes follow the master dashboard partials.
 */
class EcomTrackerDashboardService
{
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
     * @param  array{from: Carbon, to: Carbon}  $range  local calendar days
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

        $summaries = DB::table('tracking_daily_summaries')
            ->whereBetween('metric_date', [$prevFrom->toDateString(), $to])
            ->get()
            ->keyBy(fn (object $row) => (string) $row->metric_date);

        $visitors = DB::table('tracking_daily_visitor')
            ->whereBetween('visit_date', [$prevFrom->toDateString(), $to])
            ->selectRaw('COUNT(DISTINCT CASE WHEN visit_date >= ? THEN tracking_main_visitor_id END) as current_count', [$from])
            ->selectRaw('COUNT(DISTINCT CASE WHEN visit_date < ? THEN tracking_main_visitor_id END) as previous_count', [$from])
            ->first();

        $current = $this->totals($summaries->filter(fn (object $row, string $date) => $date >= $from), (int) $visitors->current_count);
        $previous = $this->totals($summaries->filter(fn (object $row, string $date) => $date <= $prevTo->toDateString()), (int) $visitors->previous_count);

        $current = array_merge($current, $this->saleTotalsFromOrders($from, $to));
        $previous = array_merge($previous, $this->saleTotalsFromOrders($prevFrom->toDateString(), $prevTo->toDateString()));

        $audiences = $this->audiences($from, $to);
        $returning = max(0, $current['sessions'] - $current['unique_visitors']);

        return [
            'kpiGroups' => $this->kpiGroups($current, $previous, $this->comparisonLabel($period, $prevFrom, $prevTo)),
            'categories' => $this->categories($from, $to),
            'products' => $this->products($from, $to),
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
            'Share of all sessions that completed a payment (one count per session with payment_success).',
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
            'payment_rate' => $sessions > 0 ? round($payments / $sessions * 100, 1) : 0.0,
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
        $row = DB::table('tracking_orders')
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

        foreach ($periodBuckets as [$periodStart, $periodEnd, $label]) {
            $labels[] = $label;
            $bucketMetrics = $this->aggregateDailyTrendBucket(
                $rangeSummaries,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
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
        $rows = DB::table('tracking_hourly_summaries')
            ->where('metric_date', $date)
            ->get()
            ->keyBy(fn (object $row) => (int) $row->metric_hour);

        $purchaseSessionsByHour = DB::table('tracking_orders')
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
     * @param  Collection<string, object>  $summaries
     * @return array<string, int>
     */
    private function aggregateDailyTrendBucket(Collection $summaries, string $fromDate, string $toDate): array
    {
        $hourly = DB::table('tracking_hourly_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->selectRaw('
                COALESCE(SUM(category_views), 0) as category_views,
                COALESCE(SUM(product_views), 0) as product_views,
                COALESCE(SUM(cart_sessions), 0) as cart_sessions,
                COALESCE(SUM(checkout_sessions), 0) as checkout_sessions,
                COALESCE(SUM(proceed_sessions), 0) as proceed_sessions,
                COALESCE(SUM(payments), 0) as payments,
                COALESCE(SUM(items_sold), 0) as items_sold
            ')
            ->first();

        $sessions = 0;

        foreach ($summaries as $date => $row) {
            if ($date < $fromDate || $date > $toDate) {
                continue;
            }

            $sessions += (int) ($row->sessions ?? 0);
        }

        return [
            'unique_visitors' => $this->distinctVisitorsBetween($fromDate, $toDate),
            'sessions' => $sessions,
            'category_views' => (int) ($hourly->category_views ?? 0),
            'product_views' => (int) ($hourly->product_views ?? 0),
            'cart_sessions' => (int) ($hourly->cart_sessions ?? 0),
            'checkout_sessions' => (int) ($hourly->checkout_sessions ?? 0),
            'proceed_sessions' => (int) ($hourly->proceed_sessions ?? 0),
            'payments' => (int) ($hourly->payments ?? 0),
            'items_sold' => (int) ($hourly->items_sold ?? 0),
            'purchase_sessions' => (int) DB::table('tracking_orders')
                ->whereBetween('metric_date', [$fromDate, $toDate])
                ->distinct()
                ->count('tracking_session_id'),
        ];
    }

    private function distinctVisitorsBetween(string $fromDate, string $toDate): int
    {
        return (int) DB::table('tracking_daily_visitor')
            ->whereBetween('visit_date', [$fromDate, $toDate])
            ->distinct()
            ->count('tracking_main_visitor_id');
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
     * Departments with their categories, best selling first.
     *
     * @return array{departments: list<array<string, mixed>>, totals: array{category_views: int, product_views: int, category_count: int}}
     */
    private function categories(string $from, string $to): array
    {
        $rows = DB::table('tracking_daily_categories as dc')
            ->join('tracking_category as c', 'c.id', '=', 'dc.tracking_category_id')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->whereBetween('dc.metric_date', [$from, $to])
            ->groupBy('c.id', 'c.name', 'd.id', 'd.name')
            ->selectRaw('c.id, c.name, d.id as department_id, d.name as department_name')
            ->selectRaw('SUM(dc.category_views) as category_views, SUM(dc.product_views) as product_views, SUM(dc.add_to_carts) as adds')
            ->selectRaw('SUM(dc.proceed_checkouts) as proceed_checkouts, SUM(dc.sold_qty) as sale_items, SUM(dc.sale_amount) as sale_amount')
            ->get();

        $metrics = ['category_views', 'product_views', 'adds', 'proceed_checkouts', 'sale_items', 'sale_amount'];
        $sort = fn (array $a, array $b) => [$b['sale_amount'], $b['product_views']] <=> [$a['sale_amount'], $a['product_views']];

        $departments = [];
        foreach ($rows as $row) {
            $departmentId = (int) ($row->department_id ?? 0);
            $category = [
                'category_id' => (int) $row->id,
                'category_name' => (string) $row->name,
                'department_id' => $departmentId ?: null,
            ];
            foreach ($metrics as $metric) {
                $category[$metric] = $metric === 'sale_amount' ? (float) $row->{$metric} : (int) $row->{$metric};
            }

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
     * Top products ranked sold → proceed → adds → views → sale value (daily rows are per product code).
     *
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    private function products(string $from, string $to): array
    {
        $top = DB::table('tracking_daily_products')
            ->whereBetween('metric_date', [$from, $to])
            ->groupBy('product_code')
            ->selectRaw('product_code as code, SUM(product_views) as views, SUM(add_to_carts) as adds, SUM(proceed_checkouts) as proceed_checkouts')
            ->selectRaw('SUM(sold_qty) as qty, SUM(sale_amount) as revenue')
            ->selectRaw('COUNT(*) OVER () as product_count, SUM(SUM(product_views)) OVER () as total_views')
            ->orderByDesc('qty')
            ->orderByDesc('proceed_checkouts')
            ->orderByDesc('adds')
            ->orderByDesc('views')
            ->orderByDesc('revenue')
            ->limit(self::PRODUCT_LIMIT);

        // Titles only for the ten rows shown.
        $rows = DB::query()
            ->fromSub($top, 'top')
            ->select('top.*')
            ->selectSub(fn ($title) => $title->from('tracking_product')->whereColumn('code', 'top.code')->selectRaw('MIN(title)'), 'title')
            ->orderByDesc('qty')
            ->orderByDesc('proceed_checkouts')
            ->orderByDesc('adds')
            ->orderByDesc('views')
            ->orderByDesc('revenue')
            ->get();

        $maxRevenue = (float) $rows->max('revenue');

        return [
            'rows' => $rows->map(fn (object $row) => [
                'code' => (string) $row->code,
                'name' => $row->title ?: $row->code,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'qty' => (int) $row->qty,
                'revenue' => (float) $row->revenue,
                'revenue_bar_percent' => $maxRevenue > 0 ? (int) round((float) $row->revenue / $maxRevenue * 100) : 0,
            ])->all(),
            'totals' => [
                'views' => (int) ($rows->first()->total_views ?? 0),
                'product_count' => (int) ($rows->first()->product_count ?? 0),
            ],
        ];
    }

    /**
     * The four recoverable panels: totals per type and its latest rows, in one query.
     *
     * @return list<array<string, mixed>>
     */
    private function recoverable(string $from, string $to, string $backUrl): array
    {
        $ranked = DB::table('tracking_recoverable_sales as rs')
            ->join('tracking_session as ts', 'ts.id', '=', 'rs.tracking_session_id')
            ->whereBetween('rs.metric_date', [$from, $to])
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
     * Device, browser and traffic source rows for the range, keyed by audience type (sorted sold → proceed → cart → views → revenue).
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function audiences(string $from, string $to): array
    {
        $rows = DB::table('tracking_daily_audiences as a')
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
        $counts = DB::table('tracking_daily_durations')
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

        $middle = $total === 0 ? collect() : DB::table('tracking_daily_visitor as dv')
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
