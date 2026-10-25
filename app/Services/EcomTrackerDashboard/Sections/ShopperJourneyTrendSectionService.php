<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Services\EcomActivityListService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShopperJourneyTrendSectionService
{
    private const TREND_LOG_SCALE_DAYS = 31;

    private const TREND_WEEKLY_THRESHOLD_DAYS = 32;

    private const TREND_MONTHLY_THRESHOLD_DAYS = 91;

    use \App\Services\EcomTrackerDashboard\Concerns\InteractsWithSessionFilterRatios;

    public function buildTrend(Collection $summaries, array $range, int $days, string $period): array
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
}
