<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EcomTrackerDashboardService
{
    private const PRODUCT_VIEW_TYPES = ['product_view', 'product_view_popup'];

    private const FUNNEL_STAGES = [
        ['key' => 'category_view', 'label' => 'Category view', 'types' => ['category_view']],
        ['key' => 'product_view', 'label' => 'Product view', 'types' => self::PRODUCT_VIEW_TYPES],
        ['key' => 'add_to_cart', 'label' => 'Add to cart', 'types' => ['add_to_cart']],
        ['key' => 'begin_checkout', 'label' => 'Begin checkout', 'types' => ['begin_checkout']],
        ['key' => 'proceed_checkout', 'label' => 'Proceed checkout', 'types' => ['proceed_checkout']],
        ['key' => 'payment_success', 'label' => 'Payment success', 'types' => ['payment_success']],
    ];

    private const TABLE_DISPLAY_LIMIT = 20;

    private const TREND_LOG_SCALE_DAYS = 31;

    private const TREND_WEEKLY_THRESHOLD_DAYS = 32;

    private const TREND_MONTHLY_THRESHOLD_DAYS = 91;

    public function __construct(
        private VisitorAnalyticsService $visitorAnalytics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $priorRange = $this->priorRange($range['from'], $range['to']);
        $extraFilters = $this->extractSessionFilters($filters);

        $currentSessions = $this->sessionsInRange($range['from'], $range['to']);
        $priorSessions = $this->sessionsInRange($priorRange['from'], $priorRange['to']);

        if ($extraFilters !== []) {
            $currentIds = $this->filteredSessionIds($range['from'], $range['to'], $extraFilters);
            $priorIds = $this->filteredSessionIds($priorRange['from'], $priorRange['to'], $extraFilters);
            $currentSessions = $currentSessions->only($currentIds->all());
            $priorSessions = $priorSessions->only($priorIds->all());
        }

        $currentKpis = $this->buildKpis($range['from'], $range['to'], $currentSessions);
        $priorKpis = $this->buildKpis($priorRange['from'], $priorRange['to'], $priorSessions);

        return [
            'filters' => $this->normalizeFilters($filters, $range),
            'range' => $range,
            'prior_range' => $priorRange,
            'live' => $this->buildLiveStatus(),
            'kpis' => $this->attachKpiDeltas($currentKpis, $priorKpis),
            'funnel' => $this->buildFunnel($range['from'], $range['to'], $extraFilters),
            'trend' => $this->buildTrend($range['from'], $range['to'], $extraFilters),
            'categories' => $this->buildCategoryPerformance($range['from'], $range['to'], filters: $extraFilters),
            'products' => $this->buildProductPerformance($range['from'], $range['to'], filters: $extraFilters),
            'colors' => $this->buildColorPerformance($range['from'], $range['to'], filters: $extraFilters),
            'cart_abandonment' => $this->buildCartAbandonment($range['from'], $range['to'], filters: $extraFilters),
            'checkout_abandonment' => $this->buildCheckoutAbandonment($range['from'], $range['to'], filters: $extraFilters),
            'devices' => $this->buildDeviceBreakdown($range['from'], $range['to'], $extraFilters),
            'traffic_sources' => $this->buildTrafficSources($range['from'], $range['to'], filters: $extraFilters),
            'geography' => $this->buildGeography($range['from'], $range['to'], filters: $extraFilters),
            'engagement' => $this->buildEngagement($range['from'], $range['to'], $extraFilters),
            'has_session_filters' => $extraFilters !== [],
        ];
    }

    /**
     * Chart.js payload for the dashboard page script.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function chartPayload(array $data): array
    {
        return [
            'trend' => $data['trend'],
            'devices' => $data['devices'],
            'engagement' => $data['engagement'],
        ];
    }

    /**
     * Flat rows for Excel export.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function buildExportRows(array $filters): array
    {
        $data = $this->getDashboardData($filters);

        return [
            'kpis' => collect($data['kpis'])->map(fn (array $kpi) => [
                'metric' => $kpi['label'],
                'value' => $kpi['value'],
                'formatted' => $kpi['formatted'],
                'delta' => $kpi['delta']['text'] ?? '',
            ])->values()->all(),
            'funnel' => collect($data['funnel'])->map(fn (array $row) => [
                'stage' => $row['stage'],
                'sessions' => $row['count'],
                'percent_of_top' => $row['percent_of_top'],
                'drop_off_percent' => $row['drop_off_percent'],
            ])->values()->all(),
            'trend' => collect($data['trend']['labels'])->map(function (string $label, int $index) use ($data) {
                return [
                    'date' => $label,
                    'sessions' => $data['trend']['sessions'][$index] ?? 0,
                    'purchases' => $data['trend']['purchases'][$index] ?? 0,
                    'conversion_rate' => $data['trend']['conversion_rates'][$index] ?? 0,
                ];
            })->values()->all(),
            'categories' => collect($data['categories'])->map(fn (array $row) => [
                'category' => $row['name'],
                'views' => $row['views'],
                'add_rate' => $row['add_rate'],
                'conversion_rate' => $row['conversion_rate'],
                'signal' => $row['signal_label'],
            ])->values()->all(),
            'products' => collect($data['products'])->map(fn (array $row) => [
                'product' => $row['name'],
                'code' => $row['code'],
                'views' => $row['views'],
                'add_to_cart' => $row['adds'],
                'purchases' => $row['purchases'],
                'revenue' => $row['revenue'],
            ])->values()->all(),
            'colors' => collect($data['colors']['products'] ?? [])
                ->flatMap(function (array $product) {
                    $rows = [[
                        'product' => $product['product'],
                        'sku' => $product['sku'],
                        'color' => '',
                        'viewed' => $product['viewed'],
                        'purchased' => $product['purchased'],
                    ]];

                    foreach ($product['variants'] as $variant) {
                        $rows[] = [
                            'product' => '',
                            'sku' => '',
                            'color' => $variant['color'],
                            'viewed' => $variant['viewed'],
                            'purchased' => $variant['purchased'],
                        ];
                    }

                    return $rows;
                })
                ->values()
                ->all(),
            'traffic_sources' => collect($data['traffic_sources'])->map(fn (array $row) => [
                'source' => $row['source'],
                'medium' => $row['medium'],
                'sessions' => $row['sessions'],
                'conversion_rate' => $row['conversion_rate'],
                'revenue' => $row['revenue'],
            ])->values()->all(),
            'geography' => collect($data['geography'])->map(fn (array $row) => [
                'location' => $row['location'],
                'sessions' => $row['sessions'],
                'revenue' => $row['revenue'],
            ])->values()->all(),
            'devices' => collect($data['devices']['legend'])->map(fn (array $row) => [
                'device' => $row['label'],
                'share' => $row['share'],
                'conversion_rate' => $row['conversion_rate'],
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: Carbon, to: Carbon, label: string, days: int}
     */
    public function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? '30d';

        if ($period === 'custom' && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $fromLocal = Carbon::parse($filters['date_from'], TrackerTime::timezone())->startOfDay();
            $toLocal = Carbon::parse($filters['date_to'], TrackerTime::timezone())->endOfDay();

            $from = $fromLocal->copy()->utc();
            $to = $toLocal->copy()->utc();

            return [
                'from' => $from,
                'to' => $to,
                'label' => $fromLocal?->format('d M Y').' – '.$toLocal?->format('d M Y'),
                'days' => (int) ($fromLocal?->diffInDays($toLocal) ?? 0) + 1,
            ];
        }

        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $toLocal = TrackerTime::localNow()->endOfDay();
        $fromLocal = TrackerTime::localNow()->subDays($days - 1)->startOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
            'label' => "Last {$days} days",
            'days' => $days,
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, days: int}
     */
    private function priorRange(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $priorTo = $from->copy()->subDay()->endOfDay();
        $priorFrom = $priorTo->copy()->subDays($days - 1)->startOfDay();

        return [
            'from' => $priorFrom,
            'to' => $priorTo,
            'label' => 'Prior '.$days.' days',
            'days' => $days,
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon, label: string, days: int}  $range
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters, array $range): array
    {
        $fromLocal = TrackerTime::toLocal($range['from']);
        $toLocal = TrackerTime::toLocal($range['to']);

        return [
            'period' => $filters['period'] ?? '30d',
            'date_from' => $filters['date_from'] ?? $fromLocal?->toDateString(),
            'date_to' => $filters['date_to'] ?? $toLocal?->toDateString(),
            'device_type' => $filters['device_type'] ?? '',
            'logged_in' => $filters['logged_in'] ?? '',
            'has_order' => $filters['has_order'] ?? '',
            'country' => $filters['country'] ?? '',
            'utm_source' => $filters['utm_source'] ?? '',
            'utm_medium' => $filters['utm_medium'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function extractSessionFilters(array $filters): array
    {
        return array_filter(
            array_intersect_key($filters, array_flip([
                'device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium',
            ])),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredSessionIds(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $query = ActivityEcomUser::query()
            ->where(function ($inner) use ($from, $to) {
                $inner->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('last_active_at', [$from, $to]);
            });

        if (! empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (isset($filters['logged_in']) && $filters['logged_in'] !== '' && $filters['logged_in'] !== null) {
            $query->where('is_logged_in', (bool) $filters['logged_in']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (! empty($filters['utm_source'])) {
            $query->where('utm_source', $filters['utm_source']);
        }

        if (! empty($filters['utm_medium'])) {
            $query->where('utm_medium', $filters['utm_medium']);
        }

        if (isset($filters['has_order']) && $filters['has_order'] !== '' && $filters['has_order'] !== null) {
            $orderSessionIds = ActivityEcomUserAction::query()
                ->where('action_type', 'payment_success')
                ->pluck('session_id');

            if ((bool) $filters['has_order']) {
                $query->whereIn('session_id', $orderSessionIds);
            } else {
                $query->whereNotIn('session_id', $orderSessionIds);
            }
        }

        return $query->pluck('session_id');
    }

    /**
     * @param  array<string, mixed>  $dateFilters
     * @param  array<string, mixed>  $extraFilters
     * @return array<string, mixed>
     */
    public function getSectionDetail(string $section, array $dateFilters, array $extraFilters = [], ?int $limit = null): array
    {
        $range = $this->resolveDateRange($dateFilters);
        $from = $range['from'];
        $to = $range['to'];
        $effectiveLimit = $limit;

        return match ($section) {
            'funnel' => ['section' => $section, 'range' => $range, 'data' => $this->buildFunnel($from, $to, $extraFilters)],
            'trend' => ['section' => $section, 'range' => $range, 'data' => $this->buildTrend($from, $to, $extraFilters)],
            'categories' => ['section' => $section, 'range' => $range, 'data' => $this->buildCategoryPerformance($from, $to, $effectiveLimit, $extraFilters)],
            'products' => ['section' => $section, 'range' => $range, 'data' => $this->buildProductPerformance($from, $to, $effectiveLimit, $extraFilters)],
            'colors' => ['section' => $section, 'range' => $range, 'data' => $this->buildColorPerformance($from, $to, $effectiveLimit, $extraFilters)],
            'cart-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildCartAbandonment($from, $to, $effectiveLimit, $extraFilters)],
            'checkout-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildCheckoutAbandonment($from, $to, $effectiveLimit, $extraFilters)],
            'devices' => ['section' => $section, 'range' => $range, 'data' => $this->buildDeviceBreakdown($from, $to, $extraFilters)],
            'traffic-sources' => ['section' => $section, 'range' => $range, 'data' => $this->buildTrafficSources($from, $to, $effectiveLimit, $extraFilters)],
            'geography' => ['section' => $section, 'range' => $range, 'data' => $this->buildGeography($from, $to, $effectiveLimit, $extraFilters)],
            'engagement' => ['section' => $section, 'range' => $range, 'data' => $this->buildEngagement($from, $to, $extraFilters)],
            default => abort(404),
        };
    }

    private function sessionsInRange(Carbon $from, Carbon $to): Collection
    {
        return ActivityEcomUser::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('last_active_at', [$from, $to]);
            })
            ->get()
            ->keyBy('session_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveStatus(): array
    {
        $lastAction = ActivityEcomUserAction::query()
            ->orderByDesc('created_at')
            ->first();

        if (! $lastAction?->created_at) {
            return [
                'last_event_at' => null,
                'seconds_ago' => null,
                'label' => 'No events yet',
            ];
        }

        $seconds = (int) TrackerTime::toUtc($lastAction->created_at)?->diffInSeconds(TrackerTime::nowUtc());

        return [
            'last_event_at' => TrackerTime::toLocal($lastAction->created_at)?->toIso8601String(),
            'seconds_ago' => $seconds,
            'label' => $this->formatIdleLabel($seconds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKpis(Carbon $from, Carbon $to, Collection $sessions): array
    {
        $sessionIds = $sessions->keys();
        $totalSessions = $sessionIds->count();

        $actions = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($sessionIds->isNotEmpty(), fn ($q) => $q->whereIn('session_id', $sessionIds))
            ->get()
            ->groupBy('session_id');

        $convertedSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'payment_success')
        )->count();

        $cartSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'add_to_cart')
        );
        $checkoutSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'proceed_checkout')
        );

        $cartAbandoned = $cartSessions->filter(
            fn (Collection $rows) => ! $rows->contains('action_type', 'payment_success')
        )->count();
        $checkoutAbandoned = $checkoutSessions->filter(
            fn (Collection $rows) => ! $rows->contains('action_type', 'payment_success')
        )->count();

        $revenue = $this->sumRevenue($from, $to, $sessionIds);
        $purchases = $this->countPurchases($from, $to, $sessionIds);

        $conversionRate = $totalSessions > 0 ? ($convertedSessions / $totalSessions) * 100 : 0;
        $aov = $purchases > 0 ? $revenue / $purchases : 0;
        $cartAbandonRate = $cartSessions->count() > 0 ? ($cartAbandoned / $cartSessions->count()) * 100 : 0;
        $checkoutAbandonRate = $checkoutSessions->count() > 0 ? ($checkoutAbandoned / $checkoutSessions->count()) * 100 : 0;

        return [
            'unique_visitors' => $this->visitorAnalytics->countNewVisitorsInRange($from, $to),
            'avg_stay_seconds' => $this->visitorAnalytics->avgSessionDurationInRange($from, $to),
            'sessions' => $totalSessions,
            'conversion_rate' => round($conversionRate, 2),
            'revenue' => round($revenue, 2),
            'aov' => round($aov, 2),
            'cart_abandonment_rate' => round($cartAbandonRate, 1),
            'checkout_abandonment_rate' => round($checkoutAbandonRate, 1),
            'cart_abandoned_sessions' => $cartAbandoned,
            'checkout_abandoned_sessions' => $checkoutAbandoned,
            'cart_at_stake' => round($this->sumCartAbandonValue($from, $to, $sessionIds), 2),
            'checkout_at_stake' => round($this->sumCheckoutAbandonValue($from, $to, $sessionIds), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $prior
     * @return array<int, array<string, mixed>>
     */
    private function attachKpiDeltas(array $current, array $prior): array
    {
        return [
            $this->kpiCard('Unique visitors', $current['unique_visitors'], $prior['unique_visitors'], 'number', false),
            $this->kpiCard('Avg stay time', $current['avg_stay_seconds'], $prior['avg_stay_seconds'], 'duration', false),
            $this->kpiCard('Sessions', $current['sessions'], $prior['sessions'], 'number', false),
            $this->kpiCard('Conversion rate', $current['conversion_rate'], $prior['conversion_rate'], 'percent', false),
            $this->kpiCard('Revenue', $current['revenue'], $prior['revenue'], 'currency', false),
            $this->kpiCard('AOV', $current['aov'], $prior['aov'], 'currency', false),
            $this->kpiCard('Cart abandonment', $current['cart_abandonment_rate'], $prior['cart_abandonment_rate'], 'percent', true),
            $this->kpiCard('Checkout abandonment', $current['checkout_abandonment_rate'], $prior['checkout_abandonment_rate'], 'percent', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiCard(string $label, float|int $value, float|int $prior, string $format, bool $lowerIsBetter): array
    {
        $delta = $this->buildDelta($value, $prior, $format, $lowerIsBetter);

        return [
            'label' => $label,
            'value' => $value,
            'formatted' => $this->formatKpiValue($value, $format),
            'delta' => $delta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDelta(float|int $value, float|int $prior, string $format, bool $lowerIsBetter): array
    {
        if ($prior == 0) {
            return [
                'direction' => 'neutral',
                'text' => '—',
            ];
        }

        $diff = $value - $prior;
        $pct = ($diff / $prior) * 100;
        $improved = $lowerIsBetter ? $diff < 0 : $diff > 0;
        $direction = abs($diff) < 0.0001 ? 'neutral' : ($improved ? 'up' : 'down');

        $text = match ($format) {
            'percent' => sprintf('%s%0.1fpp', $diff >= 0 ? '+' : '', $diff),
            'currency' => sprintf('%s%0.1f%%', $pct >= 0 ? '+' : '', $pct),
            default => sprintf('%s%0.1f%%', $pct >= 0 ? '+' : '', $pct),
        };

        if ($lowerIsBetter && $format === 'percent') {
            $text .= $diff < 0 ? ' (better)' : ($diff > 0 ? ' (worse)' : '');
        }

        return [
            'direction' => $direction,
            'text' => $text.' vs prior period',
        ];
    }

    private function formatKpiValue(float|int $value, string $format): string
    {
        return match ($format) {
            'currency' => '£'.number_format((float) $value, 2),
            'percent' => number_format((float) $value, $value >= 10 ? 1 : 2).'%',
            'duration' => $this->visitorAnalytics->formatDuration((int) $value),
            default => number_format((float) $value),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFunnel(Carbon $from, Carbon $to, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;
        $counts = [];

        foreach (self::FUNNEL_STAGES as $stage) {
            $query = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('action_type', $stage['types']);

            if ($sessionIds !== null) {
                $query->whereIn('session_id', $sessionIds);
            }

            $counts[$stage['key']] = (int) $query->distinct('session_id')->count('session_id');
        }

        $top = max(1, $counts['category_view'] ?: ($counts[array_key_first($counts)] ?? 1));
        $rows = [];
        $previous = null;

        foreach (self::FUNNEL_STAGES as $index => $stage) {
            $count = $counts[$stage['key']];
            $percentOfTop = round(($count / $top) * 100);
            $dropOff = ($index > 0 && $previous > 0)
                ? round(100 - (($count / $previous) * 100))
                : null;

            $rows[] = [
                'stage' => $stage['label'],
                'count' => $count,
                'percent_of_top' => $percentOfTop,
                'drop_off_percent' => $dropOff,
            ];

            $previous = $count;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrend(Carbon $from, Carbon $to, array $filters = []): array
    {
        $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay();
        $toLocal = TrackerTime::toLocal($to)?->copy()->endOfDay();

        if ($fromLocal === null || $toLocal === null) {
            $fromLocal = $from->copy();
            $toLocal = $to->copy();
        }

        $from = $fromLocal->copy()->utc();
        $to = $toLocal->copy()->utc();
        $totalDays = (int) $fromLocal->diffInDays($toLocal) + 1;
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $bucket = match (true) {
            $totalDays >= self::TREND_MONTHLY_THRESHOLD_DAYS => 'month',
            $totalDays >= self::TREND_WEEKLY_THRESHOLD_DAYS => 'week',
            default => 'day',
        };

        $labels = [];
        $sessions = [];
        $purchases = [];
        $conversionRates = [];

        foreach ($this->trendPeriods($fromLocal, $toLocal, $bucket) as [$periodStart, $periodEnd, $label]) {
            $labels[] = $label;

            $periodFrom = $periodStart->copy()->utc();
            $periodTo = $periodEnd->copy()->utc();

            $sessionQuery = ActivityEcomUser::query()
                ->whereBetween('created_at', [$periodFrom, $periodTo]);

            if ($sessionIds !== null) {
                $sessionQuery->whereIn('session_id', $sessionIds);
            }

            $periodSessionIds = $sessionQuery->pluck('session_id');

            $sessionCount = $periodSessionIds->count();
            $sessions[] = $sessionCount;

            if ($periodSessionIds->isEmpty()) {
                $purchases[] = 0;
                $conversionRates[] = 0;

                continue;
            }

            $converted = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('session_id', $periodSessionIds)
                ->where('action_type', 'payment_success')
                ->distinct('session_id')
                ->count('session_id');

            $purchases[] = $converted;
            $conversionRates[] = round(($converted / max(1, $sessionCount)) * 100, 1);
        }

        return [
            'labels' => $labels,
            'sessions' => $sessions,
            'purchases' => $purchases,
            'conversion_rates' => $conversionRates,
            'bucket' => $bucket,
            'total_days' => $totalDays,
            'use_log_scale' => $totalDays > self::TREND_LOG_SCALE_DAYS,
            'range_label' => $this->trendRangeLabel($totalDays, $bucket),
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

    private function trendRangeLabel(int $totalDays, string $bucket): string
    {
        return match ($bucket) {
            'week' => "{$totalDays} days · weekly buckets",
            'month' => "{$totalDays} days · monthly buckets",
            default => "{$totalDays} days · daily",
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryPerformance(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $viewsQuery = ActivityEcomUserAction::query()
            ->select('category_name', DB::raw('COUNT(DISTINCT session_id) as views'))
            ->whereBetween('created_at', [$from, $to])
            ->where('action_type', 'category_view')
            ->whereNotNull('category_name')
            ->groupBy('category_name')
            ->orderByDesc('views');

        if ($sessionIds !== null) {
            $viewsQuery->whereIn('session_id', $sessionIds);
        }

        if ($limit !== null) {
            $viewsQuery->limit($limit);
        }

        $views = $viewsQuery->get();

        return $views->map(function ($row) use ($from, $to) {
            $category = $row->category_name;
            $viewSessions = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('action_type', 'category_view')
                ->where('category_name', $category)
                ->distinct()
                ->pluck('session_id');

            $addSessions = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('action_type', 'add_to_cart')
                ->whereIn('session_id', $viewSessions)
                ->distinct()
                ->count('session_id');

            $converted = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('action_type', 'payment_success')
                ->whereIn('session_id', $viewSessions)
                ->distinct()
                ->count('session_id');

            $views = (int) $row->views;
            $addRate = $views > 0 ? round(($addSessions / $views) * 100, 1) : 0;
            $conv = $views > 0 ? round(($converted / $views) * 100, 1) : 0;

            return [
                'name' => $category,
                'views' => $views,
                'add_rate' => $addRate,
                'conversion_rate' => $conv,
                'signal' => $this->categorySignal($conv),
                'signal_label' => $this->categorySignalLabel($conv),
            ];
        })->values()->all();
    }

    private function categorySignal(float $conversion): string
    {
        if ($conversion >= 2.5) {
            return 'high';
        }

        if ($conversion >= 1.5) {
            return 'mid';
        }

        return 'low';
    }

    private function categorySignalLabel(float $conversion): string
    {
        return match ($this->categorySignal($conversion)) {
            'high' => 'Promote',
            'mid' => 'Steady',
            default => 'Investigate',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProductPerformance(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;
        /** @var Collection<string, array{name: string, code: string, views: int, adds: int, purchases: int, revenue: float}> $products */
        $products = collect();

        $baseQuery = fn () => ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($sessionIds !== null, fn ($q) => $q->whereIn('session_id', $sessionIds));

        $baseQuery()
            ->whereIn('action_type', self::PRODUCT_VIEW_TYPES)
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($products) {
                $this->accumulateProductRow($products, [
                    'code' => (string) ($action->product_code ?? ''),
                    'name' => (string) ($action->product_name ?? ''),
                    'product_id' => '',
                ], views: 1);
            });

        $baseQuery()
            ->where('action_type', 'add_to_cart')
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($products) {
                $cart = $action->add_to_cart ?? [];
                $lines = $this->cartPayloadLineItems($cart);

                if ($lines === []) {
                    $this->accumulateProductRow($products, [
                        'code' => (string) ($cart['product_code'] ?? $action->product_code ?? ''),
                        'name' => (string) ($action->product_name ?? ''),
                        'product_id' => (string) ($cart['product_id'] ?? ''),
                    ], adds: 1);

                    return;
                }

                foreach ($lines as $line) {
                    $this->accumulateProductRow($products, $line, adds: 1);
                }
            });

        $baseQuery()
            ->where('action_type', 'payment_success')
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($products) {
                $payload = $action->payment_success ?? [];
                $items = $payload['checkout_info']['items'] ?? [];

                foreach ($items as $item) {
                    $line = $this->extractPurchaseLineIdentity(is_array($item) ? $item : []);

                    if ($line === null) {
                        continue;
                    }

                    $this->accumulateProductRow(
                        $products,
                        $line,
                        purchases: (int) $line['qty'],
                        revenue: $line['qty'] * $line['price'],
                    );
                }

                if ($items === [] && ! empty($action->product_code)) {
                    $amount = (float) ($payload['amount_paid'] ?? 0);
                    $this->accumulateProductRow($products, [
                        'code' => (string) $action->product_code,
                        'name' => (string) ($action->product_name ?? ''),
                        'product_id' => '',
                    ], purchases: 1, revenue: $amount);
                }
            });

        $maxRevenue = max(1, (float) $products->max('revenue'));

        $result = $products
            ->map(function (array $product) use ($maxRevenue) {
                if ($product['purchases'] > 0 && $product['views'] < $product['purchases']) {
                    $product['views'] = $product['purchases'];
                }

                $product['revenue'] = round((float) $product['revenue'], 2);
                $product['revenue_bar_percent'] = (int) round(($product['revenue'] / $maxRevenue) * 100);

                return $product;
            })
            ->sortByDesc('revenue')
            ->values();

        if ($limit !== null) {
            $result = $result->take($limit);
        }

        return $result->values()->all();
    }

    /**
     * @param  Collection<string, array{name: string, code: string, views: int, adds: int, purchases: int, revenue: float}>  $products
     * @param  array{code?: string, name?: string, product_id?: string}  $identity
     */
    private function accumulateProductRow(
        Collection $products,
        array $identity,
        int $views = 0,
        int $adds = 0,
        int $purchases = 0,
        float $revenue = 0.0,
    ): void {
        $key = $this->productIdentityKey(
            (string) ($identity['name'] ?? ''),
            (string) ($identity['code'] ?? ''),
            (string) ($identity['product_id'] ?? ''),
        );

        $existing = $products->get($key, [
            'name' => '',
            'code' => '',
            'views' => 0,
            'adds' => 0,
            'purchases' => 0,
            'revenue' => 0.0,
        ]);

        $name = $existing['name'] ?: trim((string) ($identity['name'] ?? ''));
        $code = $existing['code'];
        $incomingCode = trim((string) ($identity['code'] ?? ''));

        if ($purchases > 0 && $incomingCode !== '') {
            $code = $incomingCode;
        } elseif ($incomingCode !== '' && (strlen($incomingCode) > strlen($code) || $code === '')) {
            $code = $incomingCode;
        }

        $products->put($key, [
            'name' => $name !== '' ? $name : ($code !== '' ? $code : 'Unknown product'),
            'code' => $code,
            'views' => $existing['views'] + $views,
            'adds' => $existing['adds'] + $adds,
            'purchases' => $existing['purchases'] + $purchases,
            'revenue' => $existing['revenue'] + $revenue,
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string, product_id: string}>
     */
    private function cartPayloadLineItems(array $payload): array
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn ($item) => [
                'code' => trim((string) ($item['product_code'] ?? '')),
                'name' => trim((string) ($item['product_name'] ?? '')),
                'product_id' => trim((string) ($item['product_id'] ?? '')),
            ])
            ->filter(fn (array $line) => $line['code'] !== '' || $line['name'] !== '' || $line['product_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{code: string, name: string, product_id: string, qty: float, price: float}|null
     */
    private function extractPurchaseLineIdentity(array $item): ?array
    {
        $code = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
        $name = trim((string) ($item['product_name'] ?? ''));
        $productId = trim((string) ($item['product_id'] ?? ''));

        if ($code === '' && $name === '' && $productId === '') {
            return null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'product_id' => $productId,
            'qty' => max(1, (float) ($item['qty'] ?? 1)),
            'price' => (float) ($item['price'] ?? 0),
        ];
    }

    private function productIdentityKey(string $name, string $code, string $productId = ''): string
    {
        $normalizedName = $this->normalizeProductName($name);

        if ($normalizedName !== '') {
            return 'name:'.$normalizedName;
        }

        $normalizedCode = strtoupper(trim($code));

        if ($normalizedCode !== '') {
            return 'code:'.$normalizedCode;
        }

        $normalizedId = trim($productId);

        if ($normalizedId !== '') {
            return 'id:'.$normalizedId;
        }

        return 'unknown:'.md5($name.$code.$productId);
    }

    private function normalizeProductName(string $name): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildColorPerformance(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;
        /** @var Collection<string, array{product_name: string, color_name: string, product_code: string, viewed: int, purchased: int}> $variants */
        $variants = collect();

        $actionQuery = fn () => ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($sessionIds !== null, fn ($q) => $q->whereIn('session_id', $sessionIds));

        $actionQuery()
            ->whereIn('action_type', self::PRODUCT_VIEW_TYPES)
            ->whereNotNull('general_color_name')
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($variants) {
                $sku = trim((string) ($action->product_code ?: $action->product_color_code ?: ''));

                if ($sku === '') {
                    return;
                }

                $this->incrementColorVariant(
                    $variants,
                    $sku,
                    (string) $action->general_color_name,
                    $action->product_name,
                    'viewed',
                );
            });

        $actionQuery()
            ->where('action_type', 'payment_success')
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($variants) {
                $items = $action->payment_success['checkout_info']['items'] ?? [];

                foreach ($items as $item) {
                    $identity = $this->extractCheckoutLineItem(is_array($item) ? $item : []);

                    if ($identity === null) {
                        continue;
                    }

                    $key = $this->resolveVariantKey(
                        $variants,
                        $identity['sku'],
                        $identity['color_name'],
                        $identity['product_name'],
                        $identity['product_id'],
                    );

                    $this->incrementColorVariantByKey(
                        $variants,
                        $key,
                        $identity['sku'],
                        $identity['color_name'],
                        $identity['product_name'],
                        'purchased',
                        $identity['quantity'],
                    );
                }

                if ($items === [] && $action->general_color_name) {
                    $sku = trim((string) ($action->product_code ?: ''));

                    if ($sku === '') {
                        return;
                    }

                    $this->incrementColorVariant(
                        $variants,
                        $sku,
                        (string) $action->general_color_name,
                        $action->product_name,
                        'purchased',
                    );
                }
            });

        $productRows = $variants
            ->groupBy(fn (array $row) => $this->productGroupKey($row['product_name'], $row['product_code']))
            ->map(function (Collection $group) {
                $primary = $group->sortByDesc(fn (array $row) => $row['viewed'] + $row['purchased'])->first();

                $variants = $group
                    ->map(fn (array $row) => [
                        'color' => $row['color_name'],
                        'sku' => $row['product_code'],
                        'viewed' => $row['viewed'],
                        'purchased' => $row['purchased'],
                    ])
                    ->sortByDesc(fn (array $row) => $row['viewed'] + $row['purchased'])
                    ->values()
                    ->all();

                return [
                    'product' => $primary['product_name'],
                    'sku' => $primary['product_code'],
                    'viewed' => (int) $group->sum('viewed'),
                    'purchased' => (int) $group->sum('purchased'),
                    'variants' => $variants,
                ];
            })
            ->sortByDesc(fn (array $product) => $product['viewed'] + $product['purchased'])
            ->values();

        if ($limit !== null) {
            $productRows = $productRows->take($limit);
        }

        $products = $productRows->values()->all();

        return [
            'products' => $products,
        ];
    }

    /**
     * @return array{sku: string, product_id: string, product_name: string, color_name: string, quantity: int}|null
     */
    private function extractCheckoutLineItem(array $item): ?array
    {
        $color = $this->normalizeColorName((string) (
            $item['color_name']
            ?? $item['general_color_name']
            ?? ($item['options']['general_color'] ?? '')
            ?? ''
        ));

        if ($color === '') {
            return null;
        }

        $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
        $productId = trim((string) ($item['product_id'] ?? ''));
        $productName = trim((string) ($item['product_name'] ?? ''));

        if ($sku === '' && $productId === '' && $productName === '') {
            return null;
        }

        return [
            'sku' => $sku !== '' ? $sku : $productId,
            'product_id' => $productId,
            'product_name' => $productName !== '' ? $productName : 'Unknown product',
            'color_name' => $color,
            'quantity' => max(1, (int) ($item['qty'] ?? $item['quantity'] ?? 1)),
        ];
    }

    /**
     * @param  Collection<string, array{product_name: string, color_name: string, product_code: string, viewed: int, purchased: int}>  $variants
     */
    private function resolveVariantKey(
        Collection $variants,
        string $sku,
        string $colorName,
        string $productName,
        string $productId = '',
    ): string {
        foreach ([$sku, $productId] as $candidateSku) {
            if ($candidateSku === '') {
                continue;
            }

            $key = $this->colorVariantKey($candidateSku, $colorName);

            if ($variants->has($key)) {
                return $key;
            }
        }

        $normalizedColor = $this->normalizeColorName($colorName);
        $matchedKey = $variants->search(function (array $variant) use ($normalizedColor, $productName, $sku, $productId) {
            if ($this->normalizeColorName($variant['color_name']) !== $normalizedColor) {
                return false;
            }

            if ($productName !== '' && strcasecmp($variant['product_name'], $productName) === 0) {
                return true;
            }

            if ($sku !== '' && strcasecmp($variant['product_code'], $sku) === 0) {
                return true;
            }

            return $productId !== '' && strcasecmp($variant['product_code'], $productId) === 0;
        });

        if ($matchedKey !== false) {
            return (string) $matchedKey;
        }

        return $this->colorVariantKey($sku !== '' ? $sku : ($productId !== '' ? $productId : $productName), $colorName);
    }

    private function productGroupKey(string $productName, string $productCode): string
    {
        $code = strtoupper(trim($productCode));

        if ($code !== '') {
            return 'code:'.$code;
        }

        return 'name:'.strtolower(trim($productName));
    }

    private function normalizeColorName(string $colorName): string
    {
        return strtolower(trim($colorName));
    }

    /**
     * @param  Collection<string, array{product_name: string, color_name: string, product_code: string, viewed: int, purchased: int}>  $variants
     */
    private function incrementColorVariant(
        Collection $variants,
        string $productCode,
        string $colorName,
        ?string $productName,
        string $metric,
        int $quantity = 1,
    ): void {
        $this->incrementColorVariantByKey(
            $variants,
            $this->colorVariantKey($productCode, $colorName),
            $productCode,
            $colorName,
            $productName,
            $metric,
            $quantity,
        );
    }

    /**
     * @param  Collection<string, array{product_name: string, color_name: string, product_code: string, viewed: int, purchased: int}>  $variants
     */
    private function incrementColorVariantByKey(
        Collection $variants,
        string $key,
        string $productCode,
        string $colorName,
        ?string $productName,
        string $metric,
        int $quantity = 1,
    ): void {
        $productCode = trim($productCode);
        $colorName = trim($colorName);

        if ($productCode === '' || $colorName === '') {
            return;
        }

        $existing = $variants->get($key, [
            'product_name' => $productName ?: 'Unknown product',
            'color_name' => $colorName,
            'product_code' => $productCode,
            'viewed' => 0,
            'purchased' => 0,
        ]);

        if ($productName) {
            $existing['product_name'] = $productName;
        }

        if ($metric === 'viewed') {
            $existing['viewed'] += $quantity;
        } else {
            $existing['purchased'] += $quantity;
        }

        if ($existing['product_code'] === '' || (strlen($productCode) > strlen($existing['product_code']))) {
            $existing['product_code'] = $productCode;
        }

        $variants->put($key, $existing);
    }

    private function colorVariantKey(string $productCode, string $colorName): string
    {
        return strtoupper(trim($productCode)).'|'.$this->normalizeColorName($colorName);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCartAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', $limit, $filters);

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', $limit, $filters);

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array{total_count: int, total_at_stake: float, rows: array<int, array<string, mixed>>}
     */
    private function abandonedSessions(Carbon $from, Carbon $to, string $stage, string $payloadKey, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $allowedSessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $candidatesQuery = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('action_type', $stage)
            ->orderByDesc('created_at');

        if ($allowedSessionIds !== null) {
            $candidatesQuery->whereIn('session_id', $allowedSessionIds);
        }

        $candidates = $candidatesQuery->get()->groupBy('session_id');

        $rows = [];

        foreach ($candidates as $sessionId => $stageActions) {
            $hasPurchase = ActivityEcomUserAction::query()
                ->where('session_id', $sessionId)
                ->where('action_type', 'payment_success')
                ->exists();

            if ($hasPurchase) {
                continue;
            }

            $latest = $stageActions->first();
            $payload = $latest->{$payloadKey} ?? [];
            $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

            $rows[] = [
                'session_id' => $sessionId,
                'session_label' => substr($sessionId, 0, 12).'…',
                'detail' => $stage === 'add_to_cart'
                    ? ($latest->product_name ?: ($payload['items'][0]['product_name'] ?? '—'))
                    : ($payload['coupon_code'] ?? '—'),
                'value' => (float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0),
                'idle' => $this->formatIdleLabel((int) (TrackerTime::toUtc($session?->last_active_at)?->diffInSeconds(TrackerTime::nowUtc()) ?? 0)),
                'activity_url' => route('admin.ecom-activity.show', ['session' => $sessionId]),
                'abandoned_at' => $latest->created_at,
            ];
        }

        $rows = collect($rows)
            ->sortByDesc(fn (array $row) => $row['abandoned_at']?->timestamp ?? 0)
            ->values();

        $totalAtStake = round($rows->sum('value'), 2);
        $limited = $limit !== null ? $rows->take($limit) : $rows;
        $displayRows = $limited
            ->map(function (array $row) {
                unset($row['abandoned_at']);

                return $row;
            })
            ->all();

        return [
            'total_count' => $rows->count(),
            'total_at_stake' => $totalAtStake,
            'rows' => $displayRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeviceBreakdown(Carbon $from, Carbon $to, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $devicesQuery = ActivityEcomUser::query()
            ->select('device_type', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('device_type')
            ->orderByDesc('total');

        if ($sessionIds !== null) {
            $devicesQuery->whereIn('session_id', $sessionIds);
        }

        $devices = $devicesQuery->get();

        $total = max(1, $devices->sum('total'));
        $labels = [];
        $values = [];
        $legend = [];

        foreach ($devices as $device) {
            $label = ucfirst($device->device_type ?: 'Unknown');
            $labels[] = $label;
            $values[] = (int) $device->total;

            $conv = $this->deviceConversionRate($from, $to, $device->device_type);
            $legend[] = [
                'label' => $label,
                'share' => round(($device->total / $total) * 100, 1),
                'conversion_rate' => $conv,
            ];
        }

        $loginQuery = ActivityEcomUser::query()
            ->select('is_logged_in', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('is_logged_in');

        if ($sessionIds !== null) {
            $loginQuery->whereIn('session_id', $sessionIds);
        }

        $login = $loginQuery->pluck('total', 'is_logged_in');

        return [
            'labels' => $labels,
            'values' => $values,
            'legend' => $legend,
            'login' => [
                'labels' => ['Guest', 'Logged-in'],
                'values' => [
                    (int) ($login[0] ?? $login[false] ?? 0),
                    (int) ($login[1] ?? $login[true] ?? 0),
                ],
            ],
        ];
    }

    private function deviceConversionRate(Carbon $from, Carbon $to, ?string $deviceType): float
    {
        $sessionIds = ActivityEcomUser::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('device_type', $deviceType)
            ->pluck('session_id');

        if ($sessionIds->isEmpty()) {
            return 0;
        }

        $converted = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('session_id', $sessionIds)
            ->where('action_type', 'payment_success')
            ->distinct('session_id')
            ->count('session_id');

        return round(($converted / $sessionIds->count()) * 100, 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTrafficSources(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $sourcesQuery = ActivityEcomUser::query()
            ->select(
                DB::raw("COALESCE(NULLIF(utm_source, ''), '(direct)') as source"),
                DB::raw("COALESCE(NULLIF(utm_medium, ''), 'none') as medium"),
                DB::raw('COUNT(*) as sessions')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('source', 'medium')
            ->orderByDesc('sessions');

        if ($sessionIds !== null) {
            $sourcesQuery->whereIn('session_id', $sessionIds);
        }

        if ($limit !== null) {
            $sourcesQuery->limit($limit);
        }

        $sources = $sourcesQuery->get();

        return $sources->map(function ($row) use ($from, $to) {
            $sessionQuery = ActivityEcomUser::query()->whereBetween('created_at', [$from, $to]);

            if ($row->source === '(direct)') {
                $sessionQuery->where(function ($query) {
                    $query->whereNull('utm_source')->orWhere('utm_source', '');
                });
            } else {
                $sessionQuery->where('utm_source', $row->source);
            }

            if ($row->medium === 'none') {
                $sessionQuery->where(function ($query) {
                    $query->whereNull('utm_medium')->orWhere('utm_medium', '');
                });
            } else {
                $sessionQuery->where('utm_medium', $row->medium);
            }

            $sessionIds = $sessionQuery->pluck('session_id');

            $converted = ActivityEcomUserAction::query()
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('session_id', $sessionIds)
                ->where('action_type', 'payment_success')
                ->distinct('session_id')
                ->count('session_id');

            $revenue = $this->sumRevenueForSessions($from, $to, $sessionIds);

            return [
                'source' => $row->source,
                'medium' => $row->medium,
                'sessions' => (int) $row->sessions,
                'conversion_rate' => $row->sessions > 0 ? round(($converted / $row->sessions) * 100, 1) : 0,
                'revenue' => round($revenue, 2),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGeography(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $locationsQuery = ActivityEcomUser::query()
            ->select(
                DB::raw("COALESCE(NULLIF(city, ''), 'Unknown') as city"),
                DB::raw("COALESCE(NULLIF(country, ''), 'Unknown') as country"),
                DB::raw('COUNT(*) as sessions')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('city', 'country')
            ->orderByDesc('sessions');

        if ($sessionIds !== null) {
            $locationsQuery->whereIn('session_id', $sessionIds);
        }

        if ($limit !== null) {
            $locationsQuery->limit($limit);
        }

        $locations = $locationsQuery->get();

        return $locations->map(function ($row) use ($from, $to) {
            $sessionIds = ActivityEcomUser::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('city', $row->city === 'Unknown' ? null : $row->city)
                ->where('country', $row->country === 'Unknown' ? null : $row->country)
                ->pluck('session_id');

            return [
                'location' => $row->city.', '.$row->country,
                'sessions' => (int) $row->sessions,
                'revenue' => round($this->sumRevenueForSessions($from, $to, $sessionIds), 2),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEngagement(Carbon $from, Carbon $to, array $filters = []): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

        $buyerQuery = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('action_type', 'payment_success')
            ->distinct();

        if ($sessionIds !== null) {
            $buyerQuery->whereIn('session_id', $sessionIds);
        }

        $buyerSessions = $buyerQuery->pluck('session_id');

        return [
            'labels' => ['Category page', 'Product page'],
            'buyers' => [
                $this->averageDwell($from, $to, $buyerSessions, ['category_view']),
                $this->averageDwell($from, $to, $buyerSessions, self::PRODUCT_VIEW_TYPES),
            ],
            'non_buyers' => [
                $this->averageDwell($from, $to, null, ['category_view'], $buyerSessions),
                $this->averageDwell($from, $to, null, self::PRODUCT_VIEW_TYPES, $buyerSessions),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $actionTypes
     * @param  Collection<int, string>|null  $includeSessions
     * @param  Collection<int, string>|null  $excludeSessions
     */
    private function averageDwell(
        Carbon $from,
        Carbon $to,
        ?Collection $includeSessions,
        array $actionTypes,
        ?Collection $excludeSessions = null,
    ): int {
        $query = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('action_type', $actionTypes)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time');

        if ($includeSessions) {
            $query->whereIn('session_id', $includeSessions);
        }

        if ($excludeSessions && $excludeSessions->isNotEmpty()) {
            $query->whereNotIn('session_id', $excludeSessions);
        }

        $seconds = $query->get()->map(function (ActivityEcomUserAction $action) {
            return (int) $action->start_time->diffInSeconds($action->end_time);
        });

        if ($seconds->isEmpty()) {
            return 0;
        }

        return (int) round($seconds->avg());
    }

    private function sumRevenue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return $this->sumRevenueForSessions($from, $to, $sessionIds);
    }

    private function sumRevenueForSessions(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        if ($sessionIds->isEmpty()) {
            return 0;
        }

        return ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('session_id', $sessionIds)
            ->where('action_type', 'payment_success')
            ->get()
            ->sum(function (ActivityEcomUserAction $action) {
                $payload = $action->payment_success ?? [];

                return (float) ($payload['amount_paid'] ?? $payload['checkout_info']['totals']['grand_total'] ?? 0);
            });
    }

    private function countPurchases(Carbon $from, Carbon $to, Collection $sessionIds): int
    {
        if ($sessionIds->isEmpty()) {
            return 0;
        }

        return ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('session_id', $sessionIds)
            ->where('action_type', 'payment_success')
            ->distinct('session_id')
            ->count('session_id');
    }

    private function sumCartAbandonValue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return collect($this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart'))
            ->sum('value');
    }

    private function sumCheckoutAbandonValue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return collect($this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout'))
            ->sum('value');
    }

    private function formatIdleLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s ago';
        }

        if ($seconds < 3600) {
            return max(1, (int) round($seconds / 60)).'m ago';
        }

        if ($seconds < 86400) {
            return max(1, (int) round($seconds / 3600)).'h ago';
        }

        return max(1, (int) round($seconds / 86400)).'d ago';
    }
}
