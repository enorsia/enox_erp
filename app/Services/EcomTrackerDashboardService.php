<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Support\EcomTrackerViewData;
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
        ['key' => 'payment_success', 'label' => 'Purchase', 'types' => ['payment_success']],
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
        $cache = app(\App\Support\TrackerRedisCache::class);
        $ttl = (int) config('tracker.analytics_cache_ttl_seconds', 300);
        $cacheKey = 'dashboard:v2:'.hash('sha256', json_encode($this->cacheableFilters($filters)));

        $cached = $cache->remember($cacheKey, $ttl, fn () => $this->buildDashboardData($filters));
        $data = $cache->payload($cached);
        $data['live'] = $this->buildLiveStatus();
        $data['analytics_cache'] = [
            'enabled' => (bool) config('tracker.analytics_cache_enabled', true),
            'cached_at' => $cache->cachedAt($cached),
            'ttl_seconds' => $ttl,
        ];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildDashboardData(array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $extraFilters = $this->extractSessionFilters($filters);
        $productCatalogOptions = $this->extractProductCatalogOptions($filters);

        $currentSessions = $this->sessionsInRange($range['from'], $range['to']);

        if ($extraFilters !== []) {
            $currentIds = $this->filteredSessionIds($range['from'], $range['to'], $extraFilters);
            $currentSessions = $currentSessions->only($currentIds->all());
        }

        $currentKpis = $this->buildKpis($range['from'], $range['to'], $currentSessions);
        $productCatalog = $this->buildProductCatalogPerformance(
            $range['from'],
            $range['to'],
            self::TABLE_DISPLAY_LIMIT,
            $extraFilters,
            $productCatalogOptions,
        );

        return [
            'filters' => $this->normalizeFilters($filters, $range),
            'range' => $range,
            'kpis' => $this->buildKpiCards($currentKpis),
            'funnel' => $this->buildFunnel($range['from'], $range['to'], $extraFilters),
            'trend' => $this->buildTrend($range['from'], $range['to'], $extraFilters, $range['period'] ?? null),
            'categories' => $this->buildCategoryPerformance($range['from'], $range['to'], filters: $extraFilters),
            'products' => $productCatalog['products'],
            'product_filter_options' => $productCatalog['filter_options'],
            'product_sort_by' => $productCatalog['sort_by'],
            'cart_abandonment' => $this->buildCartAbandonment($range['from'], $range['to'], filters: $extraFilters),
            'begin_checkout_abandonment' => $this->buildBeginCheckoutAbandonment($range['from'], $range['to'], filters: $extraFilters),
            'proceed_checkout_abandonment' => $this->buildProceedCheckoutAbandonment($range['from'], $range['to'], filters: $extraFilters),
            'devices' => $this->buildDeviceBreakdown($range['from'], $range['to'], $extraFilters),
            'traffic_sources' => $this->buildTrafficSources($range['from'], $range['to'], filters: $extraFilters),
            'geography' => $this->buildGeography($range['from'], $range['to'], filters: $extraFilters),
            'engagement' => $this->buildEngagement($range['from'], $range['to'], $extraFilters),
            'has_session_filters' => $extraFilters !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cacheableFilters(array $filters): array
    {
        ksort($filters);

        return array_filter($filters, static fn ($value) => $value !== null && $value !== '');
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
                'category' => $row['category'] ?? '',
                'views' => $row['views'],
                'add_to_cart' => $row['adds'],
                'purchases' => $row['purchases'],
                'qty' => $row['qty'] ?? 0,
                'sale' => $row['revenue'],
            ])->values()->all(),
            'variants' => collect($data['products'])
                ->flatMap(function (array $product) {
                    $rows = [];

                    foreach ($product['variants'] ?? [] as $variant) {
                        $rows[] = [
                            'product' => $product['name'],
                            'code' => $product['code'],
                            'category' => $variant['category'] ?: ($product['category'] ?? ''),
                            'color' => $variant['color'] ?: '—',
                            'size' => $variant['size'] ?: '—',
                            'sku' => $variant['sku'] ?: '—',
                            'views' => $variant['views'],
                            'add_to_cart' => $variant['adds'],
                            'purchases' => $variant['purchases'],
                            'qty' => $variant['qty'],
                            'sale' => $variant['revenue'],
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
                'sale' => $row['revenue'],
            ])->values()->all(),
            'geography' => collect($data['geography'])->map(fn (array $row) => [
                'location' => $row['location'],
                'sessions' => $row['sessions'],
                'sale' => $row['revenue'],
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
        $period = $filters['period'] ?? '24h';
        if ($period === '' || $period === null) {
            $period = '24h';
        }

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
                'period' => 'custom',
            ];
        }

        if ($period === '24h') {
            $toLocal = TrackerTime::localNow();
            $fromLocal = $toLocal->copy()->subHours(24);

            return [
                'from' => $fromLocal->copy()->utc(),
                'to' => $toLocal->copy()->utc(),
                'label' => 'Last 24 hours',
                'days' => 1,
                'period' => '24h',
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
            'period' => $period === '7d' || $period === '90d' ? $period : '30d',
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
            'period' => $filters['period'] ?? '24h',
            'date_from' => $filters['date_from'] ?? $fromLocal?->toDateString(),
            'date_to' => $filters['date_to'] ?? $toLocal?->toDateString(),
            'device_type' => $filters['device_type'] ?? '',
            'logged_in' => $filters['logged_in'] ?? '',
            'has_order' => $filters['has_order'] ?? '',
            'country' => $filters['country'] ?? '',
            'visitor_type' => $filters['visitor_type'] ?? '',
            'utm_source' => $filters['utm_source'] ?? '',
            'utm_medium' => $filters['utm_medium'] ?? '',
            'search' => $filters['search'] ?? '',
            'category' => $filters['category'] ?? '',
            'color' => $filters['color'] ?? '',
            'size' => $filters['size'] ?? '',
            'sort_by' => $filters['sort_by'] ?? '',
            'activity' => $filters['activity'] ?? '',
            'has_purchases' => $filters['has_purchases'] ?? '',
            'has_views' => $filters['has_views'] ?? '',
            'has_adds' => $filters['has_adds'] ?? '',
            'event_scenario' => $filters['event_scenario'] ?? '',
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
                'device_type', 'logged_in', 'has_order', 'country', 'visitor_type', 'utm_source', 'utm_medium',
            ])),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function extractProductCatalogOptions(array $filters): array
    {
        return array_filter(
            array_intersect_key($filters, array_flip([
                'search', 'category', 'color', 'size', 'sort_by', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario',
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
            $query->where(function ($inner) use ($filters) {
                $inner->where('country', $filters['country'])
                    ->orWhereHas('botContext', fn ($b) => $b->where('ip_country', $filters['country']));
            });
        }

        $visitorType = $filters['visitor_type'] ?? '';

        if ($visitorType === 'bot') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', true));
        } elseif ($visitorType === 'human') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', false));
        } elseif ($visitorType === 'unclassified') {
            $query->whereDoesntHave('botContext');
        }

        TrackerUtmFilter::applySourceFilter($query, $filters['utm_source'] ?? null);
        TrackerUtmFilter::applyMediumFilter($query, $filters['utm_medium'] ?? null);

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
            'products', 'colors' => [
                'section' => 'products',
                'range' => $range,
                'data' => $this->buildProductCatalogPerformance(
                    $from,
                    $to,
                    $effectiveLimit,
                    $this->extractSessionFilters($extraFilters),
                    $this->extractProductCatalogOptions($extraFilters),
                ),
            ],
            'cart-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildCartAbandonment($from, $to, $effectiveLimit, $extraFilters)],
            'begin-checkout-abandonment', 'checkout-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildBeginCheckoutAbandonment($from, $to, $effectiveLimit, $extraFilters)],
            'proceed-checkout-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildProceedCheckoutAbandonment($from, $to, $effectiveLimit, $extraFilters)],
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
            fn (Collection $rows) => $rows
                ->where('action_type', 'payment_success')
                ->contains(fn (ActivityEcomUserAction $action) => $this->paymentSaleAmount($action->payment_success ?? []) > 0)
        )->count();

        $cartSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'add_to_cart')
        );
        $beginCheckoutSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'begin_checkout')
        );
        $proceedCheckoutSessions = $actions->filter(
            fn (Collection $rows) => $rows->contains('action_type', 'proceed_checkout')
        );

        $cartAbandoned = $cartSessions->filter(
            fn (Collection $rows) => ! $rows->contains('action_type', 'begin_checkout')
        )->count();
        $beginCheckoutAbandoned = $beginCheckoutSessions->filter(
            fn (Collection $rows) => ! $rows->contains('action_type', 'proceed_checkout')
        )->count();
        $proceedCheckoutAbandoned = $proceedCheckoutSessions->filter(
            fn (Collection $rows) => ! $rows->contains('action_type', 'payment_success')
        )->count();

        $revenue = $this->sumRevenue($from, $to, $sessionIds);
        $purchases = $this->countPurchases($from, $to, $sessionIds);

        $conversionRate = $totalSessions > 0 ? ($convertedSessions / $totalSessions) * 100 : 0;
        $aov = $purchases > 0 ? $revenue / $purchases : 0;
        $cartAbandonRate = $cartSessions->count() > 0 ? ($cartAbandoned / $cartSessions->count()) * 100 : 0;
        $beginCheckoutAbandonRate = $beginCheckoutSessions->count() > 0 ? ($beginCheckoutAbandoned / $beginCheckoutSessions->count()) * 100 : 0;
        $proceedCheckoutAbandonRate = $proceedCheckoutSessions->count() > 0 ? ($proceedCheckoutAbandoned / $proceedCheckoutSessions->count()) * 100 : 0;

        return [
            'unique_visitors' => $this->visitorAnalytics->countNewVisitorsInRange($from, $to),
            'avg_stay_seconds' => $this->visitorAnalytics->avgSessionDurationInRange($from, $to),
            'sessions' => $totalSessions,
            'conversion_rate' => round($conversionRate, 2),
            'revenue' => round($revenue, 2),
            'aov' => round($aov, 2),
            'cart_abandonment_rate' => round($cartAbandonRate, 1),
            'begin_checkout_abandonment_rate' => round($beginCheckoutAbandonRate, 1),
            'proceed_checkout_abandonment_rate' => round($proceedCheckoutAbandonRate, 1),
            'cart_abandoned_sessions' => $cartAbandoned,
            'begin_checkout_abandoned_sessions' => $beginCheckoutAbandoned,
            'proceed_checkout_abandoned_sessions' => $proceedCheckoutAbandoned,
            'cart_at_stake' => round($this->sumCartAbandonValue($from, $to, $sessionIds), 2),
            'begin_checkout_at_stake' => round($this->sumBeginCheckoutAbandonValue($from, $to, $sessionIds), 2),
            'proceed_checkout_at_stake' => round($this->sumProceedCheckoutAbandonValue($from, $to, $sessionIds), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<int, array<string, mixed>>
     */
    private function buildKpiCards(array $current): array
    {
        return [
            $this->kpiCard('Unique visitors', $current['unique_visitors'], 'number'),
            $this->kpiCard('Avg stay time', $current['avg_stay_seconds'], 'duration'),
            $this->kpiCard('Sessions', $current['sessions'], 'number'),
            $this->kpiCard('Conversion rate', $current['conversion_rate'], 'percent'),
            $this->kpiCard('Sale', $current['revenue'], 'currency'),
            $this->kpiCard('Average sale', $current['aov'], 'currency'),
            $this->kpiCard('Cart abandonment', $current['cart_abandonment_rate'], 'percent'),
            $this->kpiCard('Begin checkout abandonment', $current['begin_checkout_abandonment_rate'], 'percent'),
            $this->kpiCard('Proceed checkout abandonment', $current['proceed_checkout_abandonment_rate'], 'percent'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiCard(string $label, float|int $value, string $format): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'formatted' => $this->formatKpiValue($value, $format),
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
    private function buildTrend(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): array
    {
        if ($period === '24h') {
            $fromLocal = TrackerTime::toLocal($from)?->copy();
            $toLocal = TrackerTime::toLocal($to)?->copy();

            if ($fromLocal === null || $toLocal === null) {
                $fromLocal = $from->copy();
                $toLocal = $to->copy();
            }

            $from = $fromLocal->copy()->utc();
            $to = $toLocal->copy()->utc();
            $bucket = 'hour';
            $totalDays = 1;
        } else {
            $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay();
            $toLocal = TrackerTime::toLocal($to)?->copy()->endOfDay();

            if ($fromLocal === null || $toLocal === null) {
                $fromLocal = $from->copy();
                $toLocal = $to->copy();
            }

            $from = $fromLocal->copy()->utc();
            $to = $toLocal->copy()->utc();
            $totalDays = (int) $fromLocal->diffInDays($toLocal) + 1;
            $bucket = match (true) {
                $totalDays >= self::TREND_MONTHLY_THRESHOLD_DAYS => 'month',
                $totalDays >= self::TREND_WEEKLY_THRESHOLD_DAYS => 'week',
                default => 'day',
            };
        }

        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;

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
            } elseif ($bucket === 'hour') {
                $periodStart = $cursor->copy()->startOfHour();
                $periodEnd = $cursor->copy()->endOfHour();

                if ($periodEnd > $to) {
                    $periodEnd = $to->copy();
                }

                $label = $periodStart->format('H:i');
                $cursor = $periodEnd->copy()->addSecond();
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
            'hour' => 'Last 24 hours · hourly',
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
                        purchases: 1,
                        revenue: (float) $line['revenue'],
                    );
                }

                if ($items === [] && ! empty($action->product_code)) {
                    $amount = $this->paymentSaleAmount($payload);
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
                'color_name' => trim((string) ($item['color_name'] ?? '')),
                'size_name' => trim((string) ($item['size_name'] ?? '')),
                'category' => trim((string) ($item['category_name'] ?? '')),
            ])
            ->filter(fn (array $line) => $line['code'] !== '' || $line['name'] !== '' || $line['product_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{code: string, name: string, product_id: string, qty: int, revenue: float}|null
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
            'qty' => $this->resolvePurchaseLineQty($item),
            'revenue' => $this->resolvePurchaseLineRevenue($item),
        ];
    }

    private function resolvePurchaseLineQty(array $item): int
    {
        $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);

        return (int) max(1, $qty);
    }

    private function resolvePurchaseLineRevenue(array $item): float
    {
        foreach (['line_total', 'total', 'row_total', 'subtotal'] as $field) {
            $lineTotal = (float) ($item[$field] ?? 0);

            if ($lineTotal > 0) {
                return round($lineTotal, 2);
            }
        }

        $qty = $this->resolvePurchaseLineQty($item);
        $unitPrice = (float) ($item['price'] ?? $item['unit_price'] ?? $item['discount_price'] ?? 0);

        return round(max(0, $qty) * max(0, $unitPrice), 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ActivityEcomUserAction>  $actions
     * @return \Illuminate\Support\Collection<int, ActivityEcomUserAction>
     */
    private function uniquePaymentSuccessActions(Collection $actions): Collection
    {
        return $actions
            ->unique(function (ActivityEcomUserAction $action) {
                $orderId = $action->payment_success['order_id'] ?? null;

                return filled($orderId) ? (string) $orderId : $action->event_id;
            })
            ->values();
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
     * @return array<string, string>
     */
    public function productCatalogEventScenarioOptions(): array
    {
        return [
            '' => 'All',
            'viewed_not_purchased' => 'Viewed · not purchased',
            'added_not_purchased' => 'Added to cart · not purchased',
            'viewed_not_added' => 'Viewed · not added to cart',
            'viewed_and_added' => 'Viewed and added to cart',
            'viewed_added_not_purchased' => 'Viewed + cart · not purchased',
            'full_funnel' => 'Full funnel (view → cart → purchase)',
            'engagement_no_purchase' => 'Engaged · not purchased',
            'purchased_only' => 'Purchases only',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function productCatalogActivityFilterOptions(): array
    {
        return [
            '' => 'All',
            'views' => 'Views',
            'adds' => 'Cart adds',
            'purchases' => 'Purchases',
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{views: bool, adds: bool, purchases: bool}
     */
    public function resolveProductCatalogActivityFlags(array $options): array
    {
        $activity = (string) ($options['activity'] ?? '');

        if ($activity !== '') {
            return match ($activity) {
                'views' => ['views' => true, 'adds' => false, 'purchases' => false],
                'adds' => ['views' => false, 'adds' => true, 'purchases' => false],
                'purchases' => ['views' => false, 'adds' => false, 'purchases' => true],
                default => ['views' => false, 'adds' => false, 'purchases' => false],
            };
        }

        return [
            'views' => ($options['has_views'] ?? '') === '1',
            'adds' => ($options['has_adds'] ?? '') === '1',
            'purchases' => ($options['has_purchases'] ?? '') === '1',
        ];
    }

    public function resolveProductCatalogEventScenario(?string $scenario): string
    {
        $options = $this->productCatalogEventScenarioOptions();

        return array_key_exists($scenario ?? '', $options) ? (string) $scenario : '';
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function productMatchesEventScenario(array $product, string $scenario): bool
    {
        if ($scenario === '') {
            return true;
        }

        $views = (int) ($product['views'] ?? 0);
        $adds = (int) ($product['adds'] ?? 0);
        $purchases = (int) ($product['purchases'] ?? 0);

        return match ($scenario) {
            'viewed_not_purchased' => $views > 0 && $purchases === 0,
            'added_not_purchased' => $adds > 0 && $purchases === 0,
            'viewed_not_added' => $views > 0 && $adds === 0 && $purchases === 0,
            'viewed_and_added' => $views > 0 && $adds > 0,
            'viewed_added_not_purchased' => $views > 0 && $adds > 0 && $purchases === 0,
            'full_funnel' => $views > 0 && $adds > 0 && $purchases > 0,
            'engagement_no_purchase' => ($views > 0 || $adds > 0) && $purchases === 0,
            'purchased_only' => $purchases > 0,
            default => true,
        };
    }

    /**
     * @return array<int, array{label: string, presets?: bool, options: array<string, array{label: string, hint: string}>}>
     */
    public function productCatalogSortGroups(): array
    {
        return [
            [
                'label' => 'Metrics',
                'presets' => true,
                'options' => [
                    'top_revenue' => ['label' => 'Revenue', 'hint' => 'Highest purchase revenue first'],
                    'top_views' => ['label' => 'Views', 'hint' => 'Most product_view events first'],
                    'top_adds' => ['label' => 'Cart adds', 'hint' => 'Most add_to_cart events first'],
                    'top_purchases' => ['label' => 'Purchases', 'hint' => 'Most purchase orders first'],
                    'top_qty' => ['label' => 'Units sold', 'hint' => 'Highest quantity purchased first'],
                ],
            ],
            [
                'label' => 'Insights',
                'combinations' => true,
                'options' => [
                    'insight_engagement' => ['label' => 'Views + cart adds', 'hint' => 'Combined view and cart activity'],
                    'insight_cart_abandon' => ['label' => 'Cart adds · no purchase', 'hint' => 'Added to cart without purchase'],
                    'insight_window_shoppers' => ['label' => 'Views · low purchase rate', 'hint' => 'High views with weak purchase conversion'],
                    'insight_unconverted_views' => ['label' => 'Views · no purchase', 'hint' => 'Views with zero purchases'],
                ],
            ],
            [
                'label' => 'Alphabetical',
                'options' => [
                    'product_asc' => ['label' => 'Product A–Z', 'hint' => 'Sort products alphabetically'],
                    'product_desc' => ['label' => 'Product Z–A', 'hint' => 'Reverse alphabetical order'],
                    'code_asc' => ['label' => 'SKU A–Z', 'hint' => 'Sort by SKU ascending'],
                    'code_desc' => ['label' => 'SKU Z–A', 'hint' => 'Sort by SKU descending'],
                    'color_asc' => ['label' => 'Color A–Z', 'hint' => 'Primary color ascending'],
                    'color_desc' => ['label' => 'Color Z–A', 'hint' => 'Primary color descending'],
                    'size_asc' => ['label' => 'Size A–Z', 'hint' => 'Primary size ascending'],
                    'size_desc' => ['label' => 'Size Z–A', 'hint' => 'Primary size descending'],
                ],
            ],
            [
                'label' => 'More metrics',
                'options' => [
                    'revenue_asc' => ['label' => 'Sale · lowest first', 'hint' => 'Lowest revenue at the top'],
                    'views_asc' => ['label' => 'Views · lowest first', 'hint' => 'Fewest views at the top'],
                    'purchases_asc' => ['label' => 'Purchases · lowest first', 'hint' => 'Fewest purchases at the top'],
                    'qty_asc' => ['label' => 'Qty · lowest first', 'hint' => 'Lowest quantity sold first'],
                    'adds_asc' => ['label' => 'Adds · lowest first', 'hint' => 'Fewest add-to-cart events first'],
                    'variants_desc' => ['label' => 'Most variants', 'hint' => 'Products with the most color/size variants'],
                    'category_asc' => ['label' => 'Category A–Z', 'hint' => 'Sort by category name'],
                    'category_desc' => ['label' => 'Category Z–A', 'hint' => 'Reverse category order'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function productCatalogSortOptions(): array
    {
        return collect($this->productCatalogSortGroups())
            ->flatMap(fn (array $group) => collect($group['options'])->mapWithKeys(
                fn (array $option, string $key) => [$key => $option['label']]
            ))
            ->all();
    }

    public function productCatalogSortHint(string $sortBy): string
    {
        foreach ($this->productCatalogSortGroups() as $group) {
            if (isset($group['options'][$sortBy]['hint'])) {
                return $group['options'][$sortBy]['hint'];
            }
        }

        return '';
    }

    /**
     * @return array<int, array{key: string, label: string, hint: string}>
     */
    public function productCatalogSortCombinations(): array
    {
        return collect($this->productCatalogSortGroups())
            ->filter(fn (array $group) => ! empty($group['combinations']))
            ->flatMap(fn (array $group) => collect($group['options'])->map(
                fn (array $option, string $key) => [
                    'key' => $key,
                    'label' => $option['label'],
                    'hint' => $option['hint'],
                ]
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, hint: string}>
     */
    public function productCatalogSortPresets(): array
    {
        return collect($this->productCatalogSortGroups())
            ->filter(fn (array $group) => ! empty($group['presets']))
            ->flatMap(fn (array $group) => collect($group['options'])->map(
                fn (array $option, string $key) => [
                    'key' => $key,
                    'label' => $option['label'],
                    'hint' => $option['hint'],
                ]
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, hint: string}>
     */
    public function productCatalogPrimarySortFilters(): array
    {
        return array_merge(
            $this->productCatalogSortPresets(),
            $this->productCatalogSortCombinations(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function productCatalogAdditionalSortOptions(): array
    {
        $primaryKeys = collect($this->productCatalogPrimarySortFilters())
            ->pluck('key')
            ->all();

        return collect($this->productCatalogSortGroups())
            ->filter(fn (array $group) => empty($group['presets']) && empty($group['combinations']))
            ->flatMap(fn (array $group) => collect($group['options'])->mapWithKeys(
                fn (array $option, string $key) => [$key => $option['label']]
            ))
            ->reject(fn (string $label, string $key) => in_array($key, $primaryKeys, true))
            ->all();
    }

    public function productCatalogDefaultSort(): string
    {
        return 'top_revenue';
    }

    public function resolveProductCatalogSort(?string $sortBy): string
    {
        $legacy = [
            'revenue_desc' => 'top_revenue',
            'views_desc' => 'top_views',
            'adds_desc' => 'top_adds',
            'purchases_desc' => 'top_purchases',
            'qty_desc' => 'top_qty',
        ];

        $sortBy = $legacy[$sortBy ?? ''] ?? $sortBy;
        $keys = array_keys($this->productCatalogSortOptions());

        return in_array($sortBy ?? '', $keys, true) ? (string) $sortBy : 'top_revenue';
    }

    private function normalizeProductCatalogSortKey(string $sortBy): string
    {
        return match ($sortBy) {
            'top_revenue' => 'revenue_desc',
            'top_views' => 'views_desc',
            'top_adds' => 'adds_desc',
            'top_purchases' => 'purchases_desc',
            'top_qty' => 'qty_desc',
            default => $sortBy,
        };
    }

    /**
     * Unified product + color/size variant performance.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @return array{products: array<int, array<string, mixed>>, filter_options: array{categories: array<int, string>, colors: array<int, string>, sizes: array<int, string>}, sort_by: string}
     */
    public function buildProductCatalogPerformance(
        Carbon $from,
        Carbon $to,
        ?int $limit = self::TABLE_DISPLAY_LIMIT,
        array $filters = [],
        array $options = [],
    ): array {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters) : null;
        /** @var Collection<string, array{key: string, name: string, code: string, category: string, variants: Collection<string, array<string, mixed>>}> $catalog */
        $catalog = collect();

        $actions = ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($sessionIds !== null, fn ($q) => $q->whereIn('session_id', $sessionIds))
            ->whereIn('action_type', array_merge(self::PRODUCT_VIEW_TYPES, ['add_to_cart', 'payment_success']))
            ->get();

        foreach ($actions as $action) {
            if (in_array($action->action_type, self::PRODUCT_VIEW_TYPES, true)) {
                $this->accumulateCatalogEvent($catalog, [
                    'name' => (string) ($action->product_name ?? ''),
                    'code' => (string) ($action->product_code ?? ''),
                    'product_id' => '',
                ], [
                    'color' => (string) ($action->general_color_name ?? ''),
                    'size' => '',
                    'sku' => trim((string) ($action->product_code ?: $action->product_color_code ?: '')),
                    'category' => (string) ($action->category_name ?? ''),
                ], views: 1);

                continue;
            }

            if ($action->action_type === 'add_to_cart') {
                $cart = $action->add_to_cart ?? [];
                $lines = $this->cartPayloadLineItems($cart);
                $defaultCategory = (string) ($action->category_name ?? '');

                if ($lines === []) {
                    $this->accumulateCatalogEvent($catalog, [
                        'name' => (string) ($action->product_name ?? ''),
                        'code' => (string) ($cart['product_code'] ?? $action->product_code ?? ''),
                        'product_id' => (string) ($cart['product_id'] ?? ''),
                    ], [
                        'color' => (string) ($cart['color_name'] ?? $action->general_color_name ?? ''),
                        'size' => (string) ($cart['size_name'] ?? ''),
                        'sku' => trim((string) ($cart['product_code'] ?? $action->product_code ?? '')),
                        'category' => $defaultCategory,
                    ], adds: 1);

                    continue;
                }

                foreach ($lines as $line) {
                    $this->accumulateCatalogEvent($catalog, $line, [
                        'color' => (string) ($line['color_name'] ?? $cart['color_name'] ?? $action->general_color_name ?? ''),
                        'size' => (string) ($line['size_name'] ?? $cart['size_name'] ?? ''),
                        'sku' => trim((string) ($line['code'] ?? '')),
                        'category' => (string) ($line['category'] ?? $defaultCategory),
                    ], adds: 1);
                }

                continue;
            }
        }

        $this->uniquePaymentSuccessActions($actions->where('action_type', 'payment_success'))->each(function (ActivityEcomUserAction $action) use ($catalog) {
            $payload = $action->payment_success ?? [];
            $items = $payload['checkout_info']['items'] ?? [];
            $resolvedLines = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $line = $this->extractPurchaseLineIdentity($item);

                if ($line === null) {
                    continue;
                }

                $resolvedLines[] = [
                    'item' => $item,
                    'line' => $line,
                ];
            }

            if ($resolvedLines === [] && ! empty($action->product_code)) {
                $amount = $this->paymentSaleAmount($payload);
                $this->accumulateCatalogEvent($catalog, [
                    'name' => (string) ($action->product_name ?? ''),
                    'code' => (string) $action->product_code,
                    'product_id' => '',
                ], [
                    'color' => (string) ($action->general_color_name ?? ''),
                    'size' => '',
                    'sku' => trim((string) $action->product_code),
                    'category' => (string) ($action->category_name ?? ''),
                ], purchases: 1, qty: 1, revenue: $amount);

                return;
            }

            $lineRevenueTotal = collect($resolvedLines)->sum(fn (array $row) => (float) $row['line']['revenue']);
            $orderAmount = $lineRevenueTotal <= 0 ? $this->paymentSaleAmount($payload) : 0.0;
            $fallbackShare = ($orderAmount > 0 && $resolvedLines !== [])
                ? round($orderAmount / count($resolvedLines), 2)
                : 0.0;

            foreach ($resolvedLines as $row) {
                $line = $row['line'];
                $item = $row['item'];
                $revenue = (float) $line['revenue'];

                if ($revenue <= 0 && $fallbackShare > 0) {
                    $revenue = $fallbackShare;
                }

                $this->accumulateCatalogEvent($catalog, $line, [
                    'color' => (string) ($item['color_name'] ?? $item['general_color_name'] ?? ($item['options']['general_color'] ?? '')),
                    'size' => (string) ($item['size_name'] ?? ''),
                    'sku' => $line['code'],
                    'category' => (string) ($item['category_name'] ?? $action->category_name ?? ''),
                ], purchases: 1, qty: (int) $line['qty'], revenue: $revenue);
            }
        });

        $sortBy = $this->resolveProductCatalogSort($options['sort_by'] ?? null);

        if ($this->shouldLimitProductCatalogToPurchases($filters, $options)) {
            $options['purchased_only'] = true;
        }

        $filterOptions = $this->buildProductCatalogFilterOptions($catalog);
        $products = $this->finalizeProductCatalog($catalog, $options, $sortBy);

        if ($limit !== null) {
            $products = array_slice($products, 0, $limit);
        }

        return [
            'products' => $products,
            'filter_options' => $filterOptions,
            'sort_by' => $sortBy,
        ];
    }

    /**
     * @param  Collection<string, array{key: string, name: string, code: string, category: string, variants: Collection<string, array<string, mixed>>}>  $catalog
     * @param  array{name?: string, code?: string, product_id?: string}  $identity
     * @param  array{color?: string, size?: string, sku?: string, category?: string}  $variant
     */
    private function accumulateCatalogEvent(
        Collection $catalog,
        array $identity,
        array $variant,
        int $views = 0,
        int $adds = 0,
        int $purchases = 0,
        int $qty = 0,
        float $revenue = 0.0,
    ): void {
        $productKey = $this->productIdentityKey(
            (string) ($identity['name'] ?? ''),
            (string) ($identity['code'] ?? ''),
            (string) ($identity['product_id'] ?? ''),
        );

        $product = $catalog->get($productKey, [
            'key' => $productKey,
            'name' => '',
            'code' => '',
            'category' => '',
            'variants' => collect(),
        ]);

        $name = $product['name'] ?: trim((string) ($identity['name'] ?? ''));
        $incomingCode = trim((string) ($identity['code'] ?? ''));
        $code = $product['code'];

        if ($purchases > 0 && $incomingCode !== '') {
            $code = $incomingCode;
        } elseif ($incomingCode !== '' && (strlen($incomingCode) > strlen($code) || $code === '')) {
            $code = $incomingCode;
        }

        $category = $product['category'] ?: trim((string) ($variant['category'] ?? ''));

        $variantKey = $this->catalogVariantKey(
            (string) ($variant['color'] ?? ''),
            (string) ($variant['size'] ?? ''),
            (string) ($variant['sku'] ?? ''),
        );

        $variants = $product['variants'];
        $variantRow = $variants->get($variantKey, [
            'color' => trim((string) ($variant['color'] ?? '')),
            'size' => trim((string) ($variant['size'] ?? '')),
            'sku' => trim((string) ($variant['sku'] ?? '')),
            'category' => $category,
            'views' => 0,
            'adds' => 0,
            'purchases' => 0,
            'qty' => 0,
            'revenue' => 0.0,
        ]);

        if ($variantRow['category'] === '' && $category !== '') {
            $variantRow['category'] = $category;
        }

        if ($variantRow['sku'] === '' && trim((string) ($variant['sku'] ?? '')) !== '') {
            $variantRow['sku'] = trim((string) $variant['sku']);
        }

        $variantRow['views'] += $views;
        $variantRow['adds'] += $adds;
        $variantRow['purchases'] += $purchases;
        $variantRow['qty'] += $qty;
        $variantRow['revenue'] += $revenue;
        $variants->put($variantKey, $variantRow);

        $catalog->put($productKey, [
            'key' => $productKey,
            'name' => $name !== '' ? $name : ($code !== '' ? $code : 'Unknown product'),
            'code' => $code,
            'category' => $category,
            'variants' => $variants,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     */
    private function shouldLimitProductCatalogToPurchases(array $filters, array $options): bool
    {
        if (($filters['has_order'] ?? '') === '1') {
            return true;
        }

        if (($options['event_scenario'] ?? '') === 'purchased_only') {
            return true;
        }

        if (($options['activity'] ?? '') === 'purchases') {
            return true;
        }

        return ($options['has_purchases'] ?? '') === '1';
    }

    private function catalogVariantKey(string $color, string $size, string $sku): string
    {
        return strtolower(trim($color)).'|'.strtolower(trim($size)).'|'.strtoupper(trim($sku));
    }

    /**
     * @param  Collection<string, array{key: string, name: string, code: string, category: string, variants: Collection<string, array<string, mixed>>}>  $catalog
     * @return array{categories: array<int, string>, colors: array<int, string>, sizes: array<int, string>}
     */
    private function buildProductCatalogFilterOptions(Collection $catalog): array
    {
        $categories = collect();
        $colors = collect();
        $sizes = collect();

        foreach ($catalog as $product) {
            if ($product['category'] !== '') {
                $categories->push($product['category']);
            }

            foreach ($product['variants'] as $variant) {
                if (($variant['category'] ?? '') !== '') {
                    $categories->push($variant['category']);
                }
                if (($variant['color'] ?? '') !== '') {
                    $colors->push($variant['color']);
                }
                if (($variant['size'] ?? '') !== '') {
                    $sizes->push($variant['size']);
                }
            }
        }

        $sortValues = fn (Collection $values) => $values
            ->filter()
            ->unique(fn (string $value) => strtolower($value))
            ->sort(fn (string $a, string $b) => strcasecmp($a, $b))
            ->values()
            ->all();

        return [
            'categories' => $sortValues($categories),
            'colors' => $sortValues($colors),
            'sizes' => $sortValues($sizes),
        ];
    }

    /**
     * @param  Collection<string, array{key: string, name: string, code: string, category: string, variants: Collection<string, array<string, mixed>>}>  $catalog
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function finalizeProductCatalog(Collection $catalog, array $options, string $sortBy): array
    {
        $search = strtolower(trim((string) ($options['search'] ?? '')));
        $categoryFilter = trim((string) ($options['category'] ?? ''));
        $colorFilter = trim((string) ($options['color'] ?? ''));
        $sizeFilter = trim((string) ($options['size'] ?? ''));
        $hasPurchases = ($options['has_purchases'] ?? '') === '1';
        $hasViews = ($options['has_views'] ?? '') === '1';
        $hasAdds = ($options['has_adds'] ?? '') === '1';
        $eventScenario = $this->resolveProductCatalogEventScenario($options['event_scenario'] ?? null);
        $activityFlags = $this->resolveProductCatalogActivityFlags($options);
        $hasViews = $hasViews || $activityFlags['views'];
        $hasAdds = $hasAdds || $activityFlags['adds'];
        $hasPurchases = $hasPurchases || $activityFlags['purchases'];
        $purchasedOnly = (bool) ($options['purchased_only'] ?? false);

        $products = $catalog->map(function (array $product) use ($colorFilter, $sizeFilter, $sortBy, $purchasedOnly) {
            $variants = $product['variants']->map(function (array $variant) {
                if ($variant['purchases'] > 0 && $variant['views'] < $variant['purchases']) {
                    $variant['views'] = $variant['purchases'];
                }

                $variant['revenue'] = round((float) $variant['revenue'], 2);

                return $variant;
            });

            if ($colorFilter !== '') {
                $variants = $variants->filter(
                    fn (array $variant) => strcasecmp((string) $variant['color'], $colorFilter) === 0
                );
            }

            if ($sizeFilter !== '') {
                $variants = $variants->filter(
                    fn (array $variant) => strcasecmp((string) $variant['size'], $sizeFilter) === 0
                );
            }

            if ($purchasedOnly) {
                $variants = $variants->filter(
                    fn (array $variant) => (int) $variant['purchases'] > 0
                );
            }

            $variants = $this->sortCatalogVariants($variants->values(), $sortBy)->values();

            $category = $product['category'];
            if ($category === '') {
                $category = (string) ($variants->first()['category'] ?? '');
            }

            return [
                'key' => $product['key'],
                'name' => $product['name'],
                'code' => $product['code'],
                'category' => $category,
                'views' => (int) $variants->sum('views'),
                'adds' => (int) $variants->sum('adds'),
                'purchases' => (int) $variants->sum('purchases'),
                'qty' => (int) $variants->sum('qty'),
                'revenue' => round((float) $variants->sum('revenue'), 2),
                'variant_count' => $variants->count(),
                'variants' => $variants->values()->all(),
            ];
        })->values();

        if ($search !== '') {
            $products = $products->filter(function (array $product) use ($search) {
                return str_contains(strtolower($product['name']), $search)
                    || str_contains(strtolower($product['code']), $search);
            });
        }

        if ($categoryFilter !== '') {
            $products = $products->filter(
                fn (array $product) => strcasecmp((string) $product['category'], $categoryFilter) === 0
            );
        }

        if ($hasPurchases || $hasViews || $hasAdds) {
            $products = $products->filter(function (array $product) use ($hasPurchases, $hasViews, $hasAdds) {
                $matches = [];

                if ($hasViews) {
                    $matches[] = $product['views'] > 0;
                }

                if ($hasAdds) {
                    $matches[] = $product['adds'] > 0;
                }

                if ($hasPurchases) {
                    $matches[] = $product['purchases'] > 0;
                }

                return in_array(true, $matches, true);
            });
        }

        if ($eventScenario !== '') {
            $products = $products->filter(
                fn (array $product) => $this->productMatchesEventScenario($product, $eventScenario)
            );
        }

        if ($purchasedOnly) {
            $products = $products->filter(fn (array $product) => $product['purchases'] > 0);
        }

        $products = $products->filter(fn (array $product) => $product['variant_count'] > 0 || (
            $product['views'] + $product['adds'] + $product['purchases']
        ) > 0);

        $products = $this->sortProductCatalog($products, $sortBy)->values();

        $maxRevenue = max(1.0, (float) $products->max('revenue'));

        return $products
            ->map(function (array $product) use ($maxRevenue) {
                $product['revenue_bar_percent'] = (int) round(($product['revenue'] / $maxRevenue) * 100);

                return $product;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductCatalog(Collection $products, string $sortBy): Collection
    {
        if (str_starts_with($sortBy, 'insight_')) {
            return $this->sortProductCatalogByInsight($products, $sortBy);
        }

        $sortBy = $this->normalizeProductCatalogSortKey($sortBy);
        $desc = str_ends_with($sortBy, '_desc');
        $field = match (true) {
            str_starts_with($sortBy, 'views') => 'views',
            str_starts_with($sortBy, 'purchases') => 'purchases',
            str_starts_with($sortBy, 'qty') => 'qty',
            str_starts_with($sortBy, 'adds') => 'adds',
            str_starts_with($sortBy, 'product') => 'name',
            str_starts_with($sortBy, 'code') => 'code',
            str_starts_with($sortBy, 'category') => 'category',
            str_starts_with($sortBy, 'variants') => 'variant_count',
            str_starts_with($sortBy, 'color') => 'primary_color',
            str_starts_with($sortBy, 'size') => 'primary_size',
            default => 'revenue',
        };

        $prepared = $products->map(function (array $product) {
            $product['primary_color'] = (string) (($product['variants'][0]['color'] ?? '') ?: '');
            $product['primary_size'] = (string) (($product['variants'][0]['size'] ?? '') ?: '');

            return $product;
        });

        return $prepared->sortBy(
            fn (array $product) => match ($field) {
                'name', 'code', 'category', 'primary_color', 'primary_size' => strtolower((string) $product[$field]),
                default => $product[$field],
            },
            SORT_REGULAR,
            $desc,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductCatalogByInsight(Collection $products, string $sortBy): Collection
    {
        return $products->sortByDesc(function (array $product) use ($sortBy) {
            $views = (int) $product['views'];
            $adds = (int) $product['adds'];
            $purchases = (int) $product['purchases'];

            return match ($sortBy) {
                'insight_engagement' => ($views * 1.0) + ($adds * 2.5) + ($purchases * 4.0),
                'insight_cart_abandon' => $adds > 0
                    ? ($adds * 1000) + max(0, $adds - $purchases)
                    : 0,
                'insight_window_shoppers' => $purchases === 0
                    ? ($views * 1000) + $adds
                    : max(0, $views - ($purchases * 5)),
                'insight_unconverted_views' => $purchases === 0 ? $views : 0,
                default => 0,
            };
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $variants
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCatalogVariants(Collection $variants, string $sortBy): Collection
    {
        if (str_starts_with($sortBy, 'insight_')) {
            return $variants->sortByDesc(fn (array $variant) => ($variant['views'] * 1.0) + ($variant['adds'] * 2.5) + ($variant['purchases'] * 4.0));
        }

        $sortBy = $this->normalizeProductCatalogSortKey($sortBy);
        $desc = str_ends_with($sortBy, '_desc');
        $field = match (true) {
            str_starts_with($sortBy, 'views') => 'views',
            str_starts_with($sortBy, 'purchases') => 'purchases',
            str_starts_with($sortBy, 'qty') => 'qty',
            str_starts_with($sortBy, 'adds') => 'adds',
            str_starts_with($sortBy, 'product') => 'sku',
            str_starts_with($sortBy, 'code') => 'sku',
            str_starts_with($sortBy, 'category') => 'category',
            str_starts_with($sortBy, 'color') => 'color',
            str_starts_with($sortBy, 'size') => 'size',
            default => 'revenue',
        };

        return $variants->sortBy(
            fn (array $variant) => in_array($field, ['color', 'size', 'category', 'sku'], true)
                ? strtolower((string) $variant[$field])
                : $variant[$field],
            SORT_REGULAR,
            $desc,
        );
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
        $abandonment = $this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', $limit, $filters, 'begin_checkout');

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBeginCheckoutAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'begin_checkout', 'begin_checkout', $limit, $filters, 'proceed_checkout');

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProceedCheckoutAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = []): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', $limit, $filters, 'payment_success');

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array{total_count: int, total_at_stake: float, rows: array<int, array<string, mixed>>}
     */
    private function abandonedSessions(Carbon $from, Carbon $to, string $stage, string $payloadKey, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], string $excludeActionType = 'payment_success'): array
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
            $hasExcludedAction = ActivityEcomUserAction::query()
                ->where('session_id', $sessionId)
                ->where('action_type', $excludeActionType)
                ->exists();

            if ($hasExcludedAction) {
                continue;
            }

            $latest = $stageActions->first();
            $payload = $latest->{$payloadKey} ?? [];
            $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

            $rows[] = [
                'session_id' => $sessionId,
                'session_label' => substr($sessionId, 0, 8).'…',
                'detail' => match ($stage) {
                    'add_to_cart' => $latest->product_name ?: ($payload['items'][0]['product_name'] ?? '—'),
                    'begin_checkout', 'proceed_checkout' => $payload['coupon_code'] ?? '—',
                    default => '—',
                },
                'value' => (float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0),
                'idle' => $this->formatIdleLabel((int) (TrackerTime::toUtc($session?->last_active_at)?->diffInSeconds(TrackerTime::nowUtc()) ?? 0)),
                'activity_url' => EcomTrackerViewData::activityShowUrl($sessionId),
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentSaleAmount(array $payload): float
    {
        $checkoutInfo = $payload['checkout_info'] ?? null;

        if (! is_array($checkoutInfo)) {
            return 0.0;
        }

        $items = $checkoutInfo['items'] ?? [];

        if (is_array($items) && $items !== []) {
            return collect($items)
                ->filter(fn ($item) => is_array($item))
                ->sum(fn (array $item) => $this->resolvePurchaseLineRevenue($item));
        }

        $grandTotal = (float) ($checkoutInfo['totals']['grand_total'] ?? 0);

        if ($grandTotal > 0) {
            return $grandTotal;
        }

        return (float) ($payload['amount_paid'] ?? 0);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ActivityEcomUserAction>
     */
    private function qualifyingPaymentActions(Carbon $from, Carbon $to, Collection $sessionIds): Collection
    {
        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return ActivityEcomUserAction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('session_id', $sessionIds)
            ->where('action_type', 'payment_success')
            ->get()
            ->unique(function (ActivityEcomUserAction $action) {
                $orderId = $action->payment_success['order_id'] ?? null;

                return filled($orderId) ? (string) $orderId : $action->event_id;
            })
            ->filter(function (ActivityEcomUserAction $action) {
                return $this->paymentSaleAmount($action->payment_success ?? []) > 0;
            })
            ->values();
    }

    private function sumRevenueForSessions(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return $this->qualifyingPaymentActions($from, $to, $sessionIds)
            ->sum(fn (ActivityEcomUserAction $action) => $this->paymentSaleAmount($action->payment_success ?? []));
    }

    private function countPurchases(Carbon $from, Carbon $to, Collection $sessionIds): int
    {
        return $this->qualifyingPaymentActions($from, $to, $sessionIds)->count();
    }

    private function sumCartAbandonValue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return $this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', excludeActionType: 'begin_checkout')['total_at_stake'];
    }

    private function sumBeginCheckoutAbandonValue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return $this->abandonedSessions($from, $to, 'begin_checkout', 'begin_checkout', excludeActionType: 'proceed_checkout')['total_at_stake'];
    }

    private function sumProceedCheckoutAbandonValue(Carbon $from, Carbon $to, Collection $sessionIds): float
    {
        return $this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', excludeActionType: 'payment_success')['total_at_stake'];
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
