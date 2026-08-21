<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Support\EcomTrackerViewData;
use App\Support\SessionDurationBuckets;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerCategoryIdentity;
use App\Support\TrackerPaymentCheckoutEnricher;
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

    private const DEVICE_BROWSER_DISPLAY_LIMIT = 10;

    private const TREND_FUNNEL_SERIES = [
        ['key' => 'category_views', 'label' => 'Category view', 'types' => ['category_view']],
        ['key' => 'product_views', 'label' => 'Product view', 'types' => self::PRODUCT_VIEW_TYPES],
        ['key' => 'add_to_cart', 'label' => 'Add to cart', 'types' => ['add_to_cart']],
        ['key' => 'begin_checkout', 'label' => 'Begin checkout', 'types' => ['begin_checkout']],
        ['key' => 'proceed_checkout', 'label' => 'Proceed checkout', 'types' => ['proceed_checkout']],
        ['key' => 'purchases', 'label' => 'Purchase', 'types' => ['payment_success']],
    ];

    private const TREND_LOG_SCALE_DAYS = 31;

    private const TREND_WEEKLY_THRESHOLD_DAYS = 32;

    private const TREND_MONTHLY_THRESHOLD_DAYS = 91;

    private const SESSION_ID_CHUNK = 1000;

    /** @var array<string, mixed> */
    private array $queryCache = [];

    public function __construct(
        private VisitorAnalyticsService $visitorAnalytics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters): array
    {
        $data = $this->buildDashboardData($filters);
        $data['live'] = $this->buildLiveStatus();
        $data['analytics_cache'] = [
            'enabled' => false,
            'cached_at' => null,
            'ttl_seconds' => 0,
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

        $period = $range['period'] ?? null;
        $currentSessions = $this->sessionsInRange($range['from'], $range['to'], $period);

        if ($extraFilters !== []) {
            $currentIds = $this->filteredSessionIds($range['from'], $range['to'], $extraFilters, $period);
            $currentSessions = $currentSessions->only($currentIds->all());
        }

        $currentKpis = $this->buildKpis($range['from'], $range['to'], $currentSessions);
        $productCatalog = $this->buildProductCatalogPerformance(
            $range['from'],
            $range['to'],
            null,
            $extraFilters,
            array_merge($productCatalogOptions, ['period' => $period]),
        );
        $categories = $this->buildCategoryPerformance($range['from'], $range['to'], null, $extraFilters, $period);

        return [
            'filters' => $this->normalizeFilters($filters, $range),
            'range' => $range,
            'kpis' => $this->buildKpiCards($currentKpis, $range, $extraFilters),
            'sale_conversion' => $this->buildSaleConversionMetrics(
                $range['from'],
                $range['to'],
                $currentSessions,
                $range,
                $extraFilters,
            ),
            'funnel_dropoff' => $this->buildFunnelDropoffMetrics(
                $range['from'],
                $range['to'],
                $currentSessions,
                $range,
                $extraFilters,
            ),
            'funnel' => $this->buildFunnel($range['from'], $range['to'], $extraFilters, $period),
            'trend' => $this->buildTrend($range['from'], $range['to'], $extraFilters, $range['period'] ?? null),
            'categories' => array_slice($categories, 0, self::TABLE_DISPLAY_LIMIT),
            'category_catalog_totals' => [
                'category_count' => count($categories),
                'views' => (int) collect($categories)->sum('views'),
                'adds' => (int) collect($categories)->sum('adds'),
                'sale_items' => (int) collect($categories)->sum('sale_items'),
                'sale_amount' => round((float) collect($categories)->sum('sale_amount'), 2),
            ],
            'category_departments' => $this->groupCategoryPerformanceByDepartment(array_slice($categories, 0, self::TABLE_DISPLAY_LIMIT)),
            'products' => array_slice($productCatalog['products'], 0, self::TABLE_DISPLAY_LIMIT),
            'product_catalog_totals' => [
                'product_count' => count($productCatalog['products']),
                'views' => (int) collect($productCatalog['products'])->sum('views'),
                'adds' => (int) collect($productCatalog['products'])->sum('adds'),
                'proceed_checkouts' => (int) collect($productCatalog['products'])->sum('proceed_checkouts'),
                'qty' => (int) collect($productCatalog['products'])->sum('qty'),
                'revenue' => round((float) collect($productCatalog['products'])->sum('revenue'), 2),
            ],
            'product_filter_options' => $productCatalog['filter_options'],
            'product_sort_by' => $productCatalog['sort_by'],
            'cart_abandonment' => $this->buildCartAbandonment($range['from'], $range['to'], filters: $extraFilters, period: $period),
            'begin_checkout_abandonment' => $this->buildBeginCheckoutAbandonment($range['from'], $range['to'], filters: $extraFilters, period: $period),
            'proceed_checkout_abandonment' => $this->buildProceedCheckoutAbandonment($range['from'], $range['to'], filters: $extraFilters, period: $period),
            'payment_success_events' => $this->buildPaymentSuccessEvents($range['from'], $range['to'], filters: $extraFilters, period: $period),
            'devices' => $this->buildDeviceBreakdown($range['from'], $range['to'], $extraFilters, $period),
            'traffic_sources' => $this->buildTrafficSources($range['from'], $range['to'], filters: $extraFilters, period: $period),
            'geography' => $this->buildGeography($range['from'], $range['to'], filters: $extraFilters),
            'engagement' => $this->buildEngagement($range['from'], $range['to'], $extraFilters),
            'has_session_filters' => $extraFilters !== [],
            'visitor_quality' => app(BotTrafficAnalyticsService::class)->summaryOnly($filters),
            'duration_distribution' => $this->buildDurationDistribution($currentSessions),
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
            'duration_distribution' => $data['duration_distribution'] ?? null,
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
            'sale_conversion' => [
                ['metric' => 'Items sold', 'value' => $data['sale_conversion']['item_qty']['value'] ?? 0, 'formatted' => $data['sale_conversion']['item_qty']['formatted'] ?? '0'],
                ['metric' => 'Sale amount', 'value' => $data['sale_conversion']['revenue']['value'] ?? 0, 'formatted' => $data['sale_conversion']['revenue']['formatted'] ?? '£0.00'],
            ],
            'funnel_dropoff' => collect($data['funnel_dropoff'] ?? [])->map(fn (array $metric) => [
                'metric' => $metric['label'],
                'value' => $metric['value'],
                'formatted' => $metric['formatted'],
            ])->values()->all(),
            'funnel' => collect($data['funnel'])->map(fn (array $row) => [
                'stage' => $row['stage'],
                'sessions' => $row['count'],
                'percent_of_top' => $row['percent_of_top'],
                'drop_off_percent' => $row['drop_off_percent'],
            ])->values()->all(),
            'trend' => collect($data['trend']['labels'])->map(function (string $label, int $index) use ($data) {
                $row = [
                    'date' => $label,
                    'unique_visitors' => $data['trend']['unique_visitors'][$index] ?? 0,
                    'sessions' => $data['trend']['sessions'][$index] ?? 0,
                    'conversion_rate' => $data['trend']['conversion_rates'][$index] ?? 0,
                ];

                foreach ($data['trend']['series'] ?? [] as $series) {
                    if (in_array($series['key'], ['unique_visitors', 'sessions', 'conversion_rate'], true)) {
                        continue;
                    }

                    $row[$series['key']] = $series['data'][$index] ?? 0;
                }

                return $row;
            })->values()->all(),
            'categories' => collect($data['categories'])->map(fn (array $row) => [
                'category' => $row['label'],
                'views' => $row['views'],
                'adds' => $row['adds'],
                'proceed_checkouts' => $row['proceed_checkouts'] ?? 0,
                'purchases' => $row['purchases'],
                'sale_items' => $row['sale_items'],
                'sale_amount' => $row['sale_amount'],
            ])->values()->all(),
            'products' => collect($data['products'])->map(fn (array $row) => [
                'product' => $row['name'],
                'product_code' => $row['product_code'] ?? $row['code'],
                'category' => $row['category'] ?? '',
                'views' => $row['views'],
                'add_to_cart' => $row['adds'],
                'proceed_checkout' => $row['proceed_checkouts'] ?? 0,
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
                            'product_code' => $product['product_code'] ?? $product['code'],
                            'category' => $variant['category'] ?: ($product['category'] ?? ''),
                            'color' => $variant['color'] ?: '—',
                            'size' => $variant['size'] ?: '—',
                            'sku' => $variant['sku'] ?: '—',
                            'views' => $variant['views'],
                            'add_to_cart' => $variant['adds'],
                            'proceed_checkout' => $variant['proceed_checkouts'] ?? 0,
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
                'views' => $row['views'],
                'add_to_cart' => $row['add_to_cart'],
                'begin_checkout' => $row['begin_checkout'],
                'proceed_checkout' => $row['proceed_checkout'],
                'payment_success' => $row['payment_success'],
                'sold_qty' => $row['sold_qty'],
                'conversion_rate' => $row['conversion_rate'],
                'sale' => $row['revenue'],
            ])->values()->all(),
            'geography' => collect($data['geography'])->map(fn (array $row) => [
                'location' => $row['location'],
                'sessions' => $row['sessions'],
                'sale' => $row['revenue'],
            ])->values()->all(),
            'devices' => collect($data['devices']['by_device'] ?? [])->map(fn (array $row) => [
                'dimension' => 'Device',
                'label' => $row['label'],
                'sessions' => $row['sessions'],
                'share' => $row['share'],
                'views' => $row['views'],
                'add_to_cart' => $row['add_to_cart'],
                'begin_checkout' => $row['begin_checkout'],
                'proceed_checkout' => $row['proceed_checkout'],
                'sold_qty' => $row['sold_qty'],
                'conversion_rate' => $row['conversion_rate'],
            ])->merge(collect($data['devices']['by_browser'] ?? [])->map(fn (array $row) => [
                'dimension' => 'Browser',
                'label' => $row['label'],
                'sessions' => $row['sessions'],
                'share' => $row['share'],
                'views' => $row['views'],
                'add_to_cart' => $row['add_to_cart'],
                'begin_checkout' => $row['begin_checkout'],
                'proceed_checkout' => $row['proceed_checkout'],
                'sold_qty' => $row['sold_qty'],
                'conversion_rate' => $row['conversion_rate'],
            ]))->values()->all(),
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
            $today = TrackerTime::todayRangeUtc();

            return [
                'from' => $today['from'],
                'to' => $today['to'],
                'label' => TrackerTime::todayPresetLabel(),
                'days' => 1,
                'period' => '24h',
            ];
        }

        if ($period === 'yesterday') {
            $yesterday = TrackerTime::yesterdayRangeUtc();

            return [
                'from' => $yesterday['from'],
                'to' => $yesterday['to'],
                'label' => TrackerTime::yesterdayPresetLabel(),
                'days' => 1,
                'period' => 'yesterday',
            ];
        }

        $days = match ($period) {
            '7d' => 7,
            '90d' => 30,
            default => 30,
        };

        $toLocal = TrackerTime::localNow()->endOfDay();
        $fromLocal = TrackerTime::localNow()->subDays($days - 1)->startOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
            'label' => "Last {$days} days",
            'days' => $days,
            'period' => $period === '7d' ? '7d' : '30d',
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
                'search', 'product_code', 'product_name', 'category', 'department', 'color', 'size', 'sort_by', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario',
            ])),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredSessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): Collection
    {
        return $this->rememberQuery(
            $this->queryCacheKey('filteredSessionIds', $from, $to, $period, $filters),
            fn () => $this->queryFilteredSessionIds($from, $to, $filters, $period),
        );
    }

    /**
     * Session IDs in User Activity list scope for the period (plus optional dashboard filters).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, string>
     */
    public function activitySessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): Collection
    {
        if ($filters === []) {
            return $this->sessionsInRange($from, $to, $period)->keys()->values();
        }

        if ($this->extractProductCatalogOptions($filters) !== []) {
            return $this->productCatalogSessionIds($from, $to, $filters, $period);
        }

        return $this->filteredSessionIds($from, $to, $filters, $period);
    }

    /**
     * Sessions that match product-catalog filters (product, category, color, size, etc.).
     *
     * @param  array<string, mixed>  $filters
     */
    public function productCatalogSessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): Collection
    {
        return $this->rememberQuery(
            $this->queryCacheKey('productCatalogSessionIds', $from, $to, $period, $filters),
            fn () => $this->queryProductCatalogSessionIds($from, $to, $filters, $period),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function queryProductCatalogSessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): Collection
    {
        $sessionFilters = $this->extractSessionFilters($filters);
        $catalogOptions = $this->extractProductCatalogOptions($filters);
        $hasOrder = $sessionFilters['has_order'] ?? null;
        unset($sessionFilters['has_order']);

        if ($catalogOptions === []) {
            return $this->filteredSessionIds(
                $from,
                $to,
                $hasOrder !== null && $hasOrder !== '' ? array_merge($sessionFilters, ['has_order' => $hasOrder]) : $sessionFilters,
                $period,
            );
        }

        $matchedSessionIds = match ($hasOrder) {
            '1' => $this->queryProductCatalogPurchaseSessionIds($from, $to, $catalogOptions),
            '0' => $this->queryProductCatalogActivitySessionIds($from, $to, $catalogOptions)
                ->diff($this->queryProductCatalogPurchaseSessionIds($from, $to, $catalogOptions))
                ->values(),
            default => $this->queryProductCatalogActivitySessionIds($from, $to, $catalogOptions),
        };

        $matchedSessionIds = $this->filterSessionIdsByProductCatalogActivity(
            $matchedSessionIds,
            $from,
            $to,
            $catalogOptions,
        );

        if ($sessionFilters === []) {
            return $matchedSessionIds;
        }

        $scopedSessionIds = $this->filteredSessionIds($from, $to, $sessionFilters, $period);

        return $matchedSessionIds
            ->intersect($scopedSessionIds)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     * @return Collection<int, string>
     */
    private function queryProductCatalogActivitySessionIds(Carbon $from, Carbon $to, array $catalogOptions): Collection
    {
        $actionTypes = array_merge(
            self::PRODUCT_VIEW_TYPES,
            ['add_to_cart', 'proceed_checkout', 'payment_success', 'category_view'],
        );

        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', $actionTypes)
            ->get();

        return $actions
            ->filter(fn (ActivityEcomUserAction $action) => $this->actionMatchesProductCatalogOptions($action, $catalogOptions))
            ->pluck('session_id')
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     * @return Collection<int, string>
     */
    private function queryProductCatalogPurchaseSessionIds(Carbon $from, Carbon $to, array $catalogOptions): Collection
    {
        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->get();

        return $actions
            ->filter(fn (ActivityEcomUserAction $action) => $this->paymentSuccessMatchesCategoryCatalog($action, $catalogOptions))
            ->pluck('session_id')
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $options
     * @return Collection<int, string>
     */
    public function filterSessionIdsByProductCatalogActivity(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $options,
    ): Collection {
        $activityFlags = $this->resolveProductCatalogActivityFlags($options);
        $hasViews = $activityFlags['views'] || ($options['has_views'] ?? '') === '1';
        $hasAdds = $activityFlags['adds'] || ($options['has_adds'] ?? '') === '1';
        $hasPurchases = $activityFlags['purchases'] || ($options['has_purchases'] ?? '') === '1';
        $eventScenario = $this->resolveProductCatalogEventScenario($options['event_scenario'] ?? null);

        if (! $hasViews && ! $hasAdds && ! $hasPurchases && $eventScenario === '') {
            return $sessionIds;
        }

        if ($sessionIds->isEmpty()) {
            return $sessionIds;
        }

        $identityOptions = $this->extractProductCatalogIdentityOptions($options);
        $metrics = $this->countProductCatalogMetricsForSessions($sessionIds, $from, $to, $identityOptions);

        return $sessionIds
            ->filter(function (string $sessionId) use ($metrics, $hasViews, $hasAdds, $hasPurchases, $eventScenario) {
                $row = $metrics[$sessionId] ?? null;

                if ($row === null) {
                    return false;
                }

                $views = (int) ($row['products_viewed'] ?? 0);
                $adds = (int) ($row['adds'] ?? 0);
                $purchases = (int) ($row['purchases'] ?? 0);

                if ($hasViews || $hasAdds || $hasPurchases) {
                    $matches = [];

                    if ($hasViews) {
                        $matches[] = $views > 0;
                    }

                    if ($hasAdds) {
                        $matches[] = $adds > 0;
                    }

                    if ($hasPurchases) {
                        $matches[] = $purchases > 0;
                    }

                    if (! in_array(true, $matches, true)) {
                        return false;
                    }
                }

                if ($eventScenario !== '') {
                    return $this->productMatchesEventScenario([
                        'views' => $views,
                        'adds' => $adds,
                        'purchases' => $purchases,
                    ], $eventScenario);
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function extractProductCatalogIdentityOptions(array $options): array
    {
        return array_filter(
            array_intersect_key($options, array_flip([
                'search', 'product_code', 'product_name', 'category', 'department', 'color', 'size',
            ])),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * Per-session product metrics for activity drill-down (matches dashboard catalog counting).
     *
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $options
     * @return array<string, array{products_viewed: int, adds: int, purchased: string}>
     */
    public function countProductCatalogMetricsForSessions(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $options = [],
    ): array {
        $metrics = [];

        foreach ($sessionIds as $sessionId) {
            $metrics[$sessionId] = [
                'products_viewed' => 0,
                'adds' => 0,
                'purchases' => 0,
                'purchased' => '—',
            ];
        }

        if ($sessionIds->isEmpty()) {
            return $metrics;
        }

        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereIn('session_id', $sessionIds->all())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', array_merge(self::PRODUCT_VIEW_TYPES, ['add_to_cart', 'proceed_checkout', 'payment_success']))
            ->get();

        foreach ($actions as $action) {
            $sessionId = $action->session_id;

            if (! isset($metrics[$sessionId])) {
                continue;
            }

            foreach ($this->productCatalogLinesFromAction($action) as $line) {
                if (! $this->productCatalogLineMatchesOptions($line, $options)) {
                    continue;
                }

                if (in_array($action->action_type, self::PRODUCT_VIEW_TYPES, true)) {
                    $metrics[$sessionId]['products_viewed']++;
                } elseif ($action->action_type === 'add_to_cart') {
                    $metrics[$sessionId]['adds']++;
                } elseif ($action->action_type === 'payment_success') {
                    $metrics[$sessionId]['purchases']++;
                    $metrics[$sessionId]['purchased'] = 'Yes';
                }
            }
        }

        return $metrics;
    }

    /**
     * Per-session category metrics for activity drill-down (scoped to catalog filters).
     *
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $options
     * @return array<string, array{top_category: string, purchases: int}>
     */
    public function countCategoryCatalogMetricsForSessions(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $options = [],
    ): array {
        $categoryFilter = trim((string) ($options['category'] ?? ''));
        $departmentFilter = trim((string) ($options['department'] ?? ''));
        $metrics = [];

        foreach ($sessionIds as $sessionId) {
            $metrics[$sessionId] = [
                'top_category' => $categoryFilter !== ''
                    ? TrackerCategoryIdentity::label($departmentFilter, $categoryFilter)
                    : '—',
                'purchases' => 0,
            ];
        }

        if ($sessionIds->isEmpty() || $categoryFilter === '') {
            return $metrics;
        }

        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereIn('session_id', $sessionIds->all())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->get();

        foreach ($actions as $action) {
            $sessionId = $action->session_id;

            if (! isset($metrics[$sessionId])) {
                continue;
            }

            if ($this->paymentSuccessMatchesCategoryCatalog($action, $options)) {
                $metrics[$sessionId]['purchases']++;
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function paymentActionMatchesCategoryCatalog(ActivityEcomUserAction $action, array $options): bool
    {
        return $this->paymentSuccessMatchesCategoryCatalog($action, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function catalogPaymentAmount(ActivityEcomUserAction $action, array $options): ?float
    {
        $totals = $this->sumCatalogPaymentLines($action, $options);

        return $totals['revenue'] > 0 ? $totals['revenue'] : null;
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $options
     * @return array{revenue: float, qty: int, purchases: int}
     */
    public function categoryCatalogCommerceTotalsForSessions(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $options = [],
    ): array {
        if ($sessionIds->isEmpty()) {
            return ['revenue' => 0.0, 'qty' => 0, 'purchases' => 0];
        }

        $revenue = 0.0;
        $qty = 0;
        $purchases = 0;

        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereIn('session_id', $sessionIds->all())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->get();

        foreach ($actions as $action) {
            $lines = $this->sumCatalogPaymentLines($action, $options);

            if ($lines['revenue'] <= 0) {
                continue;
            }

            $revenue += $lines['revenue'];
            $qty += $lines['qty'];
            $purchases++;
        }

        return [
            'revenue' => round($revenue, 2),
            'qty' => $qty,
            'purchases' => $purchases,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{revenue: float, qty: int}
     */
    public function sumCatalogPaymentLines(ActivityEcomUserAction $action, array $options): array
    {
        if ($action->action_type !== 'payment_success') {
            return ['revenue' => 0.0, 'qty' => 0];
        }

        $payload = app(TrackerPaymentCheckoutEnricher::class)->enrichPayloadForAction($action);
        $items = $payload['checkout_info']['items'] ?? [];
        $revenue = 0.0;
        $qty = 0;

        if (is_array($items) && $items !== []) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $line = [
                    'name' => (string) ($item['product_name'] ?? ''),
                    'code' => (string) ($item['product_code'] ?? ''),
                    'sku' => (string) ($item['sku'] ?? ''),
                    'category' => (string) ($item['category_name'] ?? ''),
                    'department_name' => $this->catalogDepartmentFromLine($item, $action),
                    'color' => (string) ($item['color_name'] ?? ''),
                    'size' => (string) ($item['size_name'] ?? ''),
                ];

                if (! $this->productCatalogLineMatchesOptions($line, $options)) {
                    continue;
                }

                $purchaseLine = $this->extractPurchaseLineIdentity($item);

                if ($purchaseLine !== null) {
                    $revenue += $purchaseLine['revenue'];
                    $qty += $purchaseLine['qty'];
                }
            }
        } elseif ($this->paymentSuccessMatchesCategoryCatalog($action, $options)) {
            $revenue = round((float) ($payload['amount_paid'] ?? 0), 2);
            $qty = 1;
        }

        return [
            'revenue' => $revenue > 0 ? round($revenue, 2) : 0.0,
            'qty' => $qty,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function actionMatchesCatalogOptions(ActivityEcomUserAction $action, array $options): bool
    {
        return $this->actionMatchesProductCatalogOptions($action, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function paymentSuccessMatchesCategoryCatalog(ActivityEcomUserAction $action, array $options): bool
    {
        foreach ($this->productCatalogLinesFromAction($action) as $line) {
            if ($this->productCatalogLineMatchesOptions($line, $options)) {
                return true;
            }
        }

        return $this->productCatalogLineMatchesOptions([
            'name' => (string) ($action->product_name ?? ''),
            'code' => (string) ($action->product_code ?? ''),
            'sku' => trim((string) ($action->sku ?? '')),
            'category' => (string) ($action->category_name ?? ''),
            'department_name' => $this->catalogDepartmentFromAction($action),
            'color' => (string) ($action->general_color_name ?? ''),
            'size' => '',
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function categoryPerformanceForName(
        Carbon $from,
        Carbon $to,
        string $categoryName,
        array $filters = [],
        ?string $period = null,
        ?string $departmentName = null,
    ): ?array {
        $categoryName = trim($categoryName);
        $departmentName = trim((string) $departmentName);

        if ($categoryName === '') {
            return null;
        }

        $categories = $this->buildCategoryPerformance($from, $to, null, $filters, $period);

        return collect($categories)->first(function (array $row) use ($categoryName, $departmentName) {
            if (strcasecmp((string) ($row['category_name'] ?? ''), $categoryName) !== 0) {
                return false;
            }

            if ($departmentName === '') {
                return true;
            }

            return strcasecmp(
                TrackerCategoryIdentity::normalizeDepartmentName((string) ($row['department_name'] ?? '')),
                TrackerCategoryIdentity::normalizeDepartmentName($departmentName),
            ) === 0;
        });
    }

    /**
     * Dashboard-style product metrics for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{views: int, adds: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}|null
     */
    public function productPerformanceSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $productCode = trim((string) ($filters['product_code'] ?? ''));
        $productName = trim((string) ($filters['product_name'] ?? ''));
        $sessionFilters = $this->extractSessionFilters($filters);
        $catalogOptions = $this->extractProductCatalogOptions($filters);

        if ($catalogOptions === [] && $sessionFilters === []) {
            return null;
        }

        $result = $this->buildProductCatalogPerformance(
            $from,
            $to,
            null,
            array_merge($sessionFilters, $catalogOptions),
            array_merge($catalogOptions, ['period' => $period]),
        );

        $products = collect($result['products'] ?? []);

        if ($products->isEmpty()) {
            return null;
        }

        if ($productCode !== '' || $productName !== '') {
            $product = $products->first(function (array $row) use ($productCode, $productName) {
                if ($productCode !== '' && strcasecmp((string) ($row['code'] ?? ''), $productCode) === 0) {
                    return true;
                }

                if ($productName !== '' && strcasecmp((string) ($row['name'] ?? ''), $productName) === 0) {
                    return true;
                }

                return false;
            });

            if ($product === null) {
                return null;
            }

            return $this->normalizeProductPerformanceSummaryRow($product);
        }

        return [
            'views' => (int) $products->sum('views'),
            'adds' => (int) $products->sum('adds'),
            'proceed_checkouts' => (int) $products->sum('proceed_checkouts'),
            'purchases' => (int) $products->sum('purchases'),
            'qty' => (int) $products->sum('qty'),
            'revenue' => round((float) $products->sum('revenue'), 2),
        ];
    }

    /**
     * Department → category options for activity/catalog filter drawers.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     departments: array<int, string>,
     *     categories_by_department: array<string, array<int, string>>
     * }
     */
    public function categoryFilterOptionsForRange(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): array {
        $sessionFilters = $this->extractSessionFilters($filters);
        $categories = $this->buildCategoryPerformance($from, $to, null, $sessionFilters, $period);
        $grouped = $this->groupCategoryPerformanceByDepartment($categories);

        $departments = [];
        $categoriesByDepartment = [];

        foreach ($grouped as $department) {
            $departmentName = trim((string) ($department['name'] ?? ''));

            if ($departmentName === '') {
                continue;
            }

            $departments[] = $departmentName;
            $categoriesByDepartment[$departmentName] = collect($department['categories'] ?? [])
                ->pluck('category_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique(fn (string $name) => strtolower($name))
                ->sort(fn (string $a, string $b) => strcasecmp($a, $b))
                ->values()
                ->all();
        }

        return [
            'departments' => $departments,
            'categories_by_department' => $categoriesByDepartment,
        ];
    }

    /**
     * Dashboard-style device metrics for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{views: int, adds: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}|null
     */
    public function devicePerformanceSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $deviceType = strtolower(trim((string) ($filters['device_type'] ?? '')));

        if (! in_array($deviceType, ['mobile', 'desktop', 'tablet'], true)) {
            return null;
        }

        $sessionFilters = $this->extractSessionFilters($filters);
        $breakdown = $this->buildDeviceBreakdown($from, $to, $sessionFilters, $period);
        $label = ucfirst($deviceType);

        $row = collect($breakdown['by_device'] ?? [])->first(
            fn (array $deviceRow) => strcasecmp((string) ($deviceRow['label'] ?? ''), $label) === 0,
        );

        if ($row === null) {
            return null;
        }

        return [
            'views' => (int) ($row['views'] ?? 0),
            'adds' => (int) ($row['add_to_cart'] ?? 0),
            'proceed_checkouts' => (int) ($row['proceed_checkout'] ?? 0),
            'purchases' => (int) ($row['purchases'] ?? 0),
            'qty' => (int) ($row['sold_qty'] ?? 0),
            'revenue' => round((float) ($row['revenue'] ?? 0), 2),
        ];
    }

    /**
     * Dashboard-style traffic source metrics for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{views: int, adds: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}|null
     */
    public function trafficSourceSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $source = trim((string) ($filters['utm_source'] ?? ''));

        if ($source === '') {
            return null;
        }

        $medium = trim((string) ($filters['utm_medium'] ?? ''));
        $sessionFilters = $this->extractSessionFilters($filters);
        $rows = $this->buildTrafficSources($from, $to, null, $sessionFilters, $period);

        $row = collect($rows)->first(function (array $trafficRow) use ($source, $medium) {
            if (strcasecmp((string) ($trafficRow['source'] ?? ''), $source) !== 0) {
                return false;
            }

            if ($medium === '') {
                return true;
            }

            return strcasecmp((string) ($trafficRow['medium'] ?? ''), $medium) === 0;
        });

        if ($row === null) {
            return null;
        }

        return [
            'views' => (int) ($row['views'] ?? 0),
            'adds' => (int) ($row['add_to_cart'] ?? 0),
            'proceed_checkouts' => (int) ($row['proceed_checkout'] ?? 0),
            'purchases' => (int) ($row['payment_success'] ?? 0),
            'qty' => (int) ($row['sold_qty'] ?? 0),
            'revenue' => round((float) ($row['revenue'] ?? 0), 2),
        ];
    }

    /**
     * Dashboard-style sale conversion metrics for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{qty: int, revenue: float}|null
     */
    public function saleConversionSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $sessionFilters = $this->extractSessionFilters($filters);
        $sessions = $this->filteredSessionsForRange($from, $to, $sessionFilters, $period);
        $metrics = $this->buildSaleConversionMetrics($from, $to, $sessions, [
            'from' => $from,
            'to' => $to,
            'label' => '',
            'days' => 0,
            'period' => $period,
        ], $sessionFilters);

        return [
            'qty' => (int) ($metrics['item_qty']['value'] ?? 0),
            'revenue' => round((float) ($metrics['revenue']['value'] ?? 0), 2),
        ];
    }

    /**
     * Dashboard-style abandonment totals for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{at_stake: float, items_qty: int, session_count: int}|null
     */
    public function abandonmentSummaryForFocus(
        string $focus,
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $config = match ($focus) {
            'cart_abandonment' => ['add_to_cart', 'add_to_cart', 'begin_checkout'],
            'begin_checkout_abandonment' => ['begin_checkout', 'begin_checkout', 'proceed_checkout'],
            'proceed_checkout_abandonment' => ['proceed_checkout', 'proceed_to_checkout', 'payment_success'],
            default => null,
        };

        if ($config === null) {
            return null;
        }

        $sessionFilters = $this->extractSessionFilters($filters);
        $data = $this->abandonedSessions(
            $from,
            $to,
            $config[0],
            $config[1],
            null,
            $sessionFilters,
            $config[2],
            $period,
        );

        return [
            'at_stake' => (float) ($data['total_at_stake'] ?? 0),
            'items_qty' => (int) collect($data['rows'] ?? [])->sum(fn (array $row) => (int) ($row['qty'] ?? 0)),
            'session_count' => (int) ($data['total_count'] ?? 0),
        ];
    }

    /**
     * Dashboard-style audience KPIs for activity drill-down context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{unique_visitors: int, avg_stay_seconds: int}|null
     */
    public function audienceSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $sessionFilters = $this->extractSessionFilters($filters);
        $sessions = $this->filteredSessionsForRange($from, $to, $sessionFilters, $period);
        $kpis = $this->buildKpis($from, $to, $sessions);

        return [
            'unique_visitors' => (int) ($kpis['unique_visitors'] ?? 0),
            'avg_stay_seconds' => (int) ($kpis['avg_stay_seconds'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{views: int, adds: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}
     */
    private function normalizeProductPerformanceSummaryRow(array $product): array
    {
        return [
            'views' => (int) ($product['views'] ?? 0),
            'adds' => (int) ($product['adds'] ?? 0),
            'proceed_checkouts' => (int) ($product['proceed_checkouts'] ?? 0),
            'purchases' => (int) ($product['purchases'] ?? 0),
            'qty' => (int) ($product['qty'] ?? 0),
            'revenue' => round((float) ($product['revenue'] ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function actionMatchesProductCatalogOptions(ActivityEcomUserAction $action, array $options): bool
    {
        foreach ($this->productCatalogLinesFromAction($action) as $line) {
            if ($this->productCatalogLineMatchesOptions($line, $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name: string, code: string, sku: string, category: string, color: string, size: string}>
     */
    private function productCatalogLinesFromAction(ActivityEcomUserAction $action): array
    {
        $actionDepartment = $this->catalogDepartmentFromAction($action);

        if (in_array($action->action_type, self::PRODUCT_VIEW_TYPES, true)) {
            return [[
                'name' => (string) ($action->product_name ?? ''),
                'code' => (string) ($action->product_code ?? ''),
                'sku' => trim((string) ($action->sku ?? '')),
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => $actionDepartment,
                'color' => (string) ($action->general_color_name ?? ''),
                'size' => '',
            ]];
        }

        if ($action->action_type === 'category_view') {
            return [[
                'name' => '',
                'code' => '',
                'sku' => '',
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => $actionDepartment,
                'color' => '',
                'size' => '',
            ]];
        }

        if ($action->action_type === 'add_to_cart' && is_array($action->add_to_cart)) {
            return $this->mapProductCatalogPayloadLines($action->add_to_cart, $action);
        }

        if ($action->action_type === 'proceed_checkout' && is_array($action->proceed_to_checkout)) {
            return $this->mapProductCatalogPayloadLines($action->proceed_to_checkout, $action);
        }

        if ($action->action_type === 'payment_success' && is_array($action->payment_success)) {
            $items = $action->payment_success['checkout_info']['items'] ?? [];

            if (! is_array($items) || $items === []) {
                return $this->mapProductCatalogPayloadLines($action->payment_success, $action);
            }

            return collect($items)
                ->filter(fn ($item) => is_array($item))
                ->map(fn (array $item) => [
                    'name' => trim((string) ($item['product_name'] ?? '')),
                    'code' => trim((string) ($item['product_code'] ?? '')),
                    'sku' => trim((string) ($item['sku'] ?? '')),
                    'category' => trim((string) ($item['category_name'] ?? $action->category_name ?? '')),
                    'department_name' => $this->catalogDepartmentFromLine($item, $action),
                    'color' => trim((string) ($item['color_name'] ?? $action->general_color_name ?? '')),
                    'size' => trim((string) ($item['size_name'] ?? '')),
                ])
                ->filter(fn (array $line) => $line['name'] !== '' || $line['code'] !== '' || $line['sku'] !== '')
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, code: string, sku: string, category: string, color: string, size: string}>
     */
    private function mapProductCatalogPayloadLines(array $payload, ActivityEcomUserAction $action): array
    {
        $lines = $this->cartPayloadLineItems($payload);

        if ($lines === []) {
            return [[
                'name' => (string) ($action->product_name ?? ''),
                'code' => (string) ($payload['product_code'] ?? $action->product_code ?? ''),
                'sku' => trim((string) ($payload['sku'] ?? $action->sku ?? '')),
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => $this->catalogDepartmentFromLine($payload, $action),
                'color' => (string) ($payload['color_name'] ?? $action->general_color_name ?? ''),
                'size' => (string) ($payload['size_name'] ?? ''),
            ]];
        }

        return collect($lines)
            ->map(fn (array $line) => [
                'name' => (string) ($line['name'] ?? ''),
                'code' => (string) ($line['code'] ?? ''),
                'sku' => (string) ($line['sku'] ?? ''),
                'category' => (string) ($line['category'] ?? $action->category_name ?? ''),
                'department_name' => $this->catalogDepartmentFromLine($line, $action),
                'color' => (string) ($line['color_name'] ?? $action->general_color_name ?? ''),
                'size' => (string) ($line['size_name'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, code: string, sku: string, category: string, color: string, size: string}  $line
     * @param  array<string, mixed>  $options
     */
    private function productCatalogLineMatchesOptions(array $line, array $options): bool
    {
        if (! $this->hasProductCatalogIdentityFilters($options)) {
            return true;
        }

        if (filled($options['product_code'] ?? null) || filled($options['product_name'] ?? null)) {
            if (! $this->productCatalogLineMatchesProductIdentity($line, $options)) {
                return false;
            }
        } else {
            $search = strtolower(trim((string) ($options['search'] ?? '')));

            if ($search !== '') {
                $matchesSearch = str_contains(strtolower($line['name']), $search)
                    || str_contains(strtolower($line['code']), $search)
                    || str_contains(strtolower($line['sku']), $search);

                if (! $matchesSearch) {
                    return false;
                }
            }
        }

        $categoryFilter = trim((string) ($options['category'] ?? ''));

        if ($categoryFilter !== '' && strcasecmp((string) ($line['category'] ?? ''), $categoryFilter) !== 0) {
            return false;
        }

        $departmentFilter = trim((string) ($options['department'] ?? ''));

        if ($departmentFilter !== '') {
            $lineDepartment = TrackerCategoryIdentity::normalizeDepartmentName((string) ($line['department_name'] ?? ''));

            if ($lineDepartment !== ''
                && strcasecmp($lineDepartment, TrackerCategoryIdentity::normalizeDepartmentName($departmentFilter)) !== 0) {
                return false;
            }
        }

        $colorFilter = trim((string) ($options['color'] ?? ''));

        if ($colorFilter !== '' && strcasecmp((string) ($line['color'] ?? ''), $colorFilter) !== 0) {
            return false;
        }

        $sizeFilter = trim((string) ($options['size'] ?? ''));

        if ($sizeFilter !== '' && strcasecmp((string) ($line['size'] ?? ''), $sizeFilter) !== 0) {
            return false;
        }

        if (filled($options['product_code'] ?? null) || filled($options['product_name'] ?? null)) {
            return true;
        }

        $search = strtolower(trim((string) ($options['search'] ?? '')));

        return $search !== ''
            || $categoryFilter !== ''
            || $departmentFilter !== ''
            || $colorFilter !== ''
            || $sizeFilter !== '';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function hasProductCatalogIdentityFilters(array $options): bool
    {
        foreach (['search', 'product_code', 'product_name', 'category', 'department', 'color', 'size'] as $key) {
            if (filled($options[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function catalogDepartmentFromAction(ActivityEcomUserAction $action): string
    {
        return TrackerCategoryIdentity::resolveDepartmentName([
            'department_name' => (string) ($action->department_name ?? ''),
            'page_url' => (string) ($action->page_url ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function catalogDepartmentFromLine(array $line, ActivityEcomUserAction $action): string
    {
        $department = trim((string) ($line['department_name'] ?? $line['department'] ?? ''));

        if ($department !== '') {
            return TrackerCategoryIdentity::normalizeDepartmentName($department);
        }

        return $this->catalogDepartmentFromAction($action);
    }

    /**
     * @param  array{name: string, code: string, sku: string, category: string, color: string, size: string}  $line
     * @param  array<string, mixed>  $options
     */
    private function productCatalogLineMatchesProductIdentity(array $line, array $options): bool
    {
        $targetCode = strtoupper(trim((string) ($options['product_code'] ?? '')));
        $targetName = $this->normalizeProductName((string) ($options['product_name'] ?? ''));

        $lineCode = strtoupper(trim((string) ($line['code'] ?? '')));
        $lineName = $this->normalizeProductName((string) ($line['name'] ?? ''));
        $lineSku = strtoupper(trim((string) ($line['sku'] ?? '')));

        if ($targetCode !== '') {
            if ($lineCode !== '' && strcasecmp($lineCode, $targetCode) === 0) {
                return true;
            }

            if ($lineSku !== '' && strcasecmp($lineSku, $targetCode) === 0) {
                return true;
            }
        }

        if ($targetName !== '' && $lineName !== '' && $lineName === $targetName) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function queryFilteredSessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): Collection
    {
        $query = ActivityEcomUser::query()->select('session_id');
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);

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
            'trend' => ['section' => $section, 'range' => $range, 'data' => $this->buildTrend($from, $to, $extraFilters, $range['period'] ?? null)],
            'categories' => ['section' => $section, 'range' => $range, 'data' => $this->groupCategoryPerformanceByDepartment(
                $this->buildCategoryPerformance($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null),
            )],
            'products', 'colors' => [
                'section' => 'products',
                'range' => $range,
                'data' => $this->buildProductCatalogPerformance(
                    $from,
                    $to,
                    $effectiveLimit,
                    $this->extractSessionFilters($extraFilters),
                    array_merge(
                        $this->extractProductCatalogOptions($extraFilters),
                        ['period' => $range['period'] ?? null],
                    ),
                ),
            ],
            'cart-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildCartAbandonment($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null)],
            'begin-checkout-abandonment', 'checkout-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildBeginCheckoutAbandonment($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null)],
            'proceed-checkout-abandonment' => ['section' => $section, 'range' => $range, 'data' => $this->buildProceedCheckoutAbandonment($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null)],
            'payment-success-events' => ['section' => $section, 'range' => $range, 'data' => $this->buildPaymentSuccessEvents($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null)],
            'devices' => ['section' => $section, 'range' => $range, 'data' => $this->buildDeviceBreakdown($from, $to, $extraFilters, $range['period'] ?? null)],
            'traffic-sources' => ['section' => $section, 'range' => $range, 'data' => $this->buildTrafficSources($from, $to, $effectiveLimit, $extraFilters, $range['period'] ?? null)],
            'geography' => ['section' => $section, 'range' => $range, 'data' => $this->buildGeography($from, $to, $effectiveLimit, $extraFilters)],
            'engagement' => ['section' => $section, 'range' => $range, 'data' => $this->buildEngagement($from, $to, $extraFilters)],
            default => abort(404),
        };
    }

    private function rememberQuery(string $key, callable $resolver): mixed
    {
        if (! array_key_exists($key, $this->queryCache)) {
            $this->queryCache[$key] = $resolver();
        }

        return $this->queryCache[$key];
    }

    private function queryCacheKey(string $name, Carbon $from, Carbon $to, mixed ...$parts): string
    {
        return $name.'|'.$from->getTimestamp().'|'.$to->getTimestamp().'|'.md5(serialize($parts));
    }

    private function sessionSetCacheKey(string $name, Carbon $from, Carbon $to, Collection $sessionIds): string
    {
        return $name.'|'.$from->getTimestamp().'|'.$to->getTimestamp().'|'.$sessionIds->count().'|'.$sessionIds->first().'|'.$sessionIds->last();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  Collection<int|string, mixed>  $sessionIds
     */
    private function constrainToSessionIds($query, Collection $sessionIds, string $column = 'session_id'): void
    {
        $ids = $sessionIds->values()->all();

        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (count($ids) <= self::SESSION_ID_CHUNK) {
            $query->whereIn($column, $ids);

            return;
        }

        $query->where(function ($outer) use ($ids, $column) {
            foreach (array_chunk($ids, self::SESSION_ID_CHUNK) as $chunk) {
                $outer->orWhereIn($column, $chunk);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  Collection<int|string, mixed>  $sessionIds
     */
    private function constrainActionsToRangeSessions(
        $query,
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?string $period,
        bool $hasExtraFilters,
    ): void {
        if ($sessionIds->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (! $hasExtraFilters) {
            $query->whereIn('session_id', function ($sub) use ($from, $to, $period) {
                $sub->from('activity_ecom_user')->select('session_id');
                TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
            });

            return;
        }

        $this->constrainToSessionIds($query, $sessionIds);
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function sessionIdsCreatedBetweenSubquery(Carbon $from, Carbon $to, ?Collection $allowedSessionIds = null)
    {
        $sub = ActivityEcomUser::query()
            ->select('session_id')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to));

        if ($allowedSessionIds !== null) {
            $this->constrainToSessionIds($sub, $allowedSessionIds);
        }

        return $sub;
    }

    /**
     * Funnel rows without JSON payloads, plus payment_success rows with amount/qty JSON.
     *
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return array{0: Collection<int, ActivityEcomUserAction>, 1: Collection<int, ActivityEcomUserAction>}
     */
    private function deviceTrafficActions(
        Carbon $from,
        Carbon $to,
        ?Collection $allowedSessionIds,
        ?string $period = null,
    ): array {
        return $this->rememberQuery(
            $this->queryCacheKey('deviceTrafficActions', $from, $to, $period, $allowedSessionIds?->count(), $allowedSessionIds?->first(), $allowedSessionIds?->last()),
            function () use ($from, $to, $allowedSessionIds, $period) {
                $scopedSessionIds = $allowedSessionIds;

                if ($scopedSessionIds === null) {
                    $scopedSessionIds = $this->sessionsInRange($from, $to, $period)->keys();
                }

                if ($scopedSessionIds->isEmpty()) {
                    return [collect(), collect()];
                }

                $range = TrackerTime::storageRange($from, $to);

                $funnel = DB::table('activity_ecom_user_actions')
                    ->select('session_id', 'action_type')
                    ->whereBetween('created_at', $range)
                    ->whereIn('action_type', array_merge(self::PRODUCT_VIEW_TYPES, ['add_to_cart', 'begin_checkout', 'proceed_checkout']))
                    ->whereIn('session_id', $scopedSessionIds->values()->all())
                    ->get();

                $payments = ActivityEcomUserAction::query()
                    ->select('session_id', 'payment_success')
                    ->whereBetween('created_at', $range)
                    ->where('action_type', 'payment_success');

                $this->constrainToSessionIds($payments, $scopedSessionIds);

                return [$funnel, $payments->get()];
            },
        );
    }

    /**
     * @param  Collection<int|string, mixed>  $sessionIds
     * @return array<string, true>
     */
    private function sessionIdsHavingActionType(Collection $sessionIds, string $actionType): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $found = [];

        foreach ($sessionIds->chunk(self::SESSION_ID_CHUNK) as $chunk) {
            $ids = ActivityEcomUserAction::query()
                ->select('session_id')
                ->whereIn('session_id', $chunk->values()->all())
                ->where('action_type', $actionType)
                ->distinct()
                ->pluck('session_id');

            foreach ($ids as $id) {
                $found[(string) $id] = true;
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function categoryViewColumns(): array
    {
        return [
            'id',
            'session_id',
            'action_type',
            'created_at',
            'category_name',
            'category_code',
            'department_name',
            'page_url',
            'product_name',
            'product_code',
        ];
    }

    /**
     * @return list<string>
     */
    private function conversionActionColumns(): array
    {
        return [
            'id',
            'session_id',
            'action_type',
            'created_at',
            'category_name',
            'category_code',
            'department_name',
            'page_url',
            'product_name',
            'product_code',
            'sku',
            'general_color_name',
            'add_to_cart',
            'proceed_to_checkout',
            'payment_success',
        ];
    }

    /**
     * @return list<string>
     */
    private function productCatalogActionColumns(): array
    {
        return [
            'id',
            'session_id',
            'action_type',
            'created_at',
            'product_name',
            'product_code',
            'product_color_id',
            'general_color_name',
            'sku',
            'category_name',
            'category_code',
            'department_name',
            'page_url',
            'add_to_cart',
            'proceed_to_checkout',
            'payment_success',
        ];
    }

    private function sessionsInRange(Carbon $from, Carbon $to, ?string $period = null): Collection
    {
        return $this->rememberQuery(
            $this->queryCacheKey('sessionsInRange', $from, $to, $period),
            function () use ($from, $to, $period) {
                $query = ActivityEcomUser::query()
                    ->select('session_id', 'visitor_id', 'session_duration_seconds', 'created_at');
                TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);

                return $query->get()->keyBy('session_id');
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveStatus(): array
    {
        $lastAction = ActivityEcomUserAction::query()
            ->select('created_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $lastAction?->created_at) {
            return [
                'last_event_at' => null,
                'seconds_ago' => null,
                'label' => 'No events yet',
            ];
        }

        $seconds = TrackerTime::secondsSinceStorage($lastAction->created_at);

        return [
            'last_event_at' => TrackerTime::fromStorage($lastAction->created_at)?->toIso8601String(),
            'seconds_ago' => $seconds,
            'label' => TrackerTime::formatIdleSeconds($seconds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKpis(Carbon $from, Carbon $to, Collection $sessions): array
    {
        $sessionIds = $sessions->keys();
        $funnel = $this->computeFunnelKpis($from, $to, $sessions);

        $revenue = $this->sumRevenue($from, $to, $sessionIds);
        $purchases = $funnel['payment_success_count'];
        $totalSessions = $sessionIds->count();
        $aov = $purchases > 0 ? $revenue / $purchases : 0;

        return [
            'unique_visitors' => $this->countUniqueVisitorsFromSessions($sessions),
            'sessions' => $totalSessions,
            'total_stay_seconds' => $this->totalStaySecondsFromSessions($sessions),
            'avg_stay_seconds' => $this->avgStaySecondsFromSessions($sessions),
            'conversion_rate' => $funnel['conversion_rate'],
            'revenue' => round($revenue, 2),
            'aov' => round($aov, 2),
            'cart_abandonment_rate' => $funnel['cart_abandonment_rate'],
            'begin_checkout_abandonment_rate' => $funnel['begin_checkout_abandonment_rate'],
            'proceed_checkout_abandonment_rate' => $funnel['proceed_checkout_abandonment_rate'],
            'payment_success_count' => $funnel['payment_success_count'],
            'cart_abandoned_sessions' => $funnel['cart_abandoned_count'],
            'begin_checkout_abandoned_sessions' => $funnel['begin_checkout_abandoned_count'],
            'proceed_checkout_abandoned_sessions' => $funnel['proceed_checkout_abandoned_count'],
            'cart_at_stake' => round($this->sumCartAbandonValue($from, $to, $sessionIds), 2),
            'begin_checkout_at_stake' => round($this->sumBeginCheckoutAbandonValue($from, $to, $sessionIds), 2),
            'proceed_checkout_at_stake' => round($this->sumProceedCheckoutAbandonValue($from, $to, $sessionIds), 2),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function computeFunnelKpis(Carbon $from, Carbon $to, Collection $sessions): array
    {
        return $this->rememberQuery(
            $this->sessionSetCacheKey('computeFunnelKpis', $from, $to, $sessions->keys()),
            function () use ($from, $to, $sessions) {
                $sessionIds = $sessions->keys();
                $totalSessions = $sessionIds->count();
                $typeSets = $this->sessionActionTypeSets($sessionIds, $from, $to);

                $convertedSessions = 0;
                $cartStageCount = 0;
                $beginCheckoutStageCount = 0;
                $proceedCheckoutStageCount = 0;
                $cartAbandoned = 0;
                $beginCheckoutAbandoned = 0;
                $proceedCheckoutAbandoned = 0;

                foreach ($typeSets as $types) {
                    $hasCart = isset($types['add_to_cart']);
                    $hasBegin = isset($types['begin_checkout']);
                    $hasProceed = isset($types['proceed_checkout']);
                    $hasPayment = isset($types['payment_success']);

                    if ($hasPayment) {
                        $convertedSessions++;
                    }

                    if ($hasCart) {
                        $cartStageCount++;

                        if (! $hasBegin) {
                            $cartAbandoned++;
                        }
                    }

                    if ($hasBegin) {
                        $beginCheckoutStageCount++;

                        if (! $hasProceed) {
                            $beginCheckoutAbandoned++;
                        }
                    }

                    if ($hasProceed) {
                        $proceedCheckoutStageCount++;

                        if (! $hasPayment) {
                            $proceedCheckoutAbandoned++;
                        }
                    }
                }

                $conversionRate = $totalSessions > 0 ? ($convertedSessions / $totalSessions) * 100 : 0;
                $cartAbandonRate = $cartStageCount > 0 ? ($cartAbandoned / $cartStageCount) * 100 : 0;
                $beginCheckoutAbandonRate = $beginCheckoutStageCount > 0 ? ($beginCheckoutAbandoned / $beginCheckoutStageCount) * 100 : 0;
                $proceedCheckoutAbandonRate = $proceedCheckoutStageCount > 0 ? ($proceedCheckoutAbandoned / $proceedCheckoutStageCount) * 100 : 0;
                $stageRate = static fn (int $count): float => $totalSessions > 0 ? ($count / $totalSessions) * 100 : 0.0;

                return [
                    'conversion_rate' => round($conversionRate, 2),
                    'cart_abandonment_rate' => round($cartAbandonRate, 1),
                    'begin_checkout_abandonment_rate' => round($beginCheckoutAbandonRate, 1),
                    'proceed_checkout_abandonment_rate' => round($proceedCheckoutAbandonRate, 1),
                    'payment_success_count' => $convertedSessions,
                    'cart_abandoned_count' => $cartAbandoned,
                    'begin_checkout_abandoned_count' => $beginCheckoutAbandoned,
                    'proceed_checkout_abandoned_count' => $proceedCheckoutAbandoned,
                    'total_sessions' => $totalSessions,
                    'cart_stage_count' => $cartStageCount,
                    'begin_checkout_stage_count' => $beginCheckoutStageCount,
                    'proceed_checkout_stage_count' => $proceedCheckoutStageCount,
                    'cart_stage_rate' => round($stageRate($cartStageCount), 1),
                    'begin_checkout_stage_rate' => round($stageRate($beginCheckoutStageCount), 1),
                    'proceed_checkout_stage_rate' => round($stageRate($proceedCheckoutStageCount), 1),
                    'payment_stage_rate' => round($stageRate($convertedSessions), 1),
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array{from: Carbon, to: Carbon, label: string, days: int, period?: string}  $range
     * @param  array<string, mixed>  $extraFilters
     * @return array<int, array<string, mixed>>
     */
    private function buildKpiCards(array $current, array $range, array $extraFilters = []): array
    {
        $period = $range['period'] ?? null;
        $prevRange = $this->resolvePreviousPeriodRange($range);
        $prevPeriod = $prevRange['period'] ?? null;
        $prevSessions = $this->filteredSessionsForRange($prevRange['from'], $prevRange['to'], $extraFilters, $prevPeriod);
        $comparisonLabel = $prevRange['label'];

        return [
            $this->kpiCardWithComparison(
                'Unique visitors',
                $current['unique_visitors'],
                $this->countUniqueVisitorsFromSessions($prevSessions),
                'number',
                'Distinct visitor IDs among sessions in the selected period (same session rules as User activity).',
                $comparisonLabel,
            ),
            $this->kpiCardWithComparison(
                'Sessions',
                $current['sessions'],
                $prevSessions->count(),
                'number',
                'Sessions in the selected period using the same date rules as User activity. Respects active dashboard filters.',
                $comparisonLabel,
            ),
            $this->kpiCardWithComparison(
                'Total stay time',
                $current['total_stay_seconds'],
                $this->totalStaySecondsFromSessions($prevSessions),
                'duration',
                'Sum of session Duration values for sessions in the period (same as User activity Duration column).',
                $comparisonLabel,
            ),
            $this->kpiCardWithComparison(
                'Avg stay time',
                $current['avg_stay_seconds'],
                $this->avgStaySecondsFromSessions($prevSessions),
                'duration',
                'Average Duration per session in the period (total stay time divided by session count).',
                $comparisonLabel,
            ),
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon, label: string, days: int, period?: string}  $range
     * @param  array<string, mixed>  $extraFilters
     * @return array<string, array<string, mixed>>
     */
    private function buildFunnelDropoffMetrics(
        Carbon $from,
        Carbon $to,
        Collection $currentSessions,
        array $range,
        array $extraFilters = [],
    ): array {
        $current = $this->computeFunnelKpis($from, $to, $currentSessions);
        $prevRange = $this->resolvePreviousPeriodRange($range);
        $prevSessions = $this->filteredSessionsForRange(
            $prevRange['from'],
            $prevRange['to'],
            $extraFilters,
            $prevRange['period'] ?? null,
        );
        $previous = $this->computeFunnelKpis($prevRange['from'], $prevRange['to'], $prevSessions);
        $comparisonLabel = $prevRange['label'];

        return [
            'cart_drop' => $this->funnelDropCard(
                'Cart drop',
                (float) $current['cart_abandonment_rate'],
                (int) $current['cart_abandoned_count'],
                (float) $previous['cart_abandonment_rate'],
                (int) $previous['cart_abandoned_count'],
                'Sessions that added to cart but did not begin checkout (matches Cart abandoned drill-down).',
                $comparisonLabel,
            ),
            'checkout_drop' => $this->funnelDropCard(
                'Checkout drop',
                (float) $current['begin_checkout_abandonment_rate'],
                (int) $current['begin_checkout_abandoned_count'],
                (float) $previous['begin_checkout_abandonment_rate'],
                (int) $previous['begin_checkout_abandoned_count'],
                'Sessions that began checkout but did not proceed (matches Begin checkout abandoned drill-down).',
                $comparisonLabel,
            ),
            'proceed_drop' => $this->funnelDropCard(
                'Proceed drop',
                (float) $current['proceed_checkout_abandonment_rate'],
                (int) $current['proceed_checkout_abandoned_count'],
                (float) $previous['proceed_checkout_abandonment_rate'],
                (int) $previous['proceed_checkout_abandoned_count'],
                'Sessions that proceeded to checkout but did not pay (matches Proceed checkout abandoned drill-down).',
                $comparisonLabel,
            ),
            'payments' => $this->paymentSuccessCard(
                (float) $current['payment_stage_rate'],
                (int) $current['payment_success_count'],
                (float) $previous['payment_stage_rate'],
                (int) $previous['payment_success_count'],
                $comparisonLabel,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function funnelDropCard(
        string $label,
        float $currentRate,
        int $currentCount,
        float $previousRate,
        int $previousCount,
        string $tip,
        string $comparisonLabel,
    ): array {
        $card = $this->kpiCardWithComparison($label, $currentRate, $previousRate, 'percent', $tip, $comparisonLabel);
        $card['formatted'] = $this->formatFunnelDropValue($currentRate, $currentCount);
        $card['comparison']['previous_formatted'] = $this->formatFunnelDropValue($previousRate, $previousCount);

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSuccessCard(
        float $currentRate,
        int $currentCount,
        float $previousRate,
        int $previousCount,
        string $comparisonLabel,
    ): array {
        $card = $this->kpiCardWithComparison(
            'Payments',
            $currentRate,
            $previousRate,
            'percent',
            'Share of all sessions that completed a payment (one count per session with payment_success).',
            $comparisonLabel,
        );
        $card['formatted'] = $this->formatFunnelDropValue($currentRate, $currentCount);
        $card['comparison']['previous_formatted'] = $this->formatFunnelDropValue($previousRate, $previousCount);
        $card['value_class'] = 'etd-kpi-value--success';

        return $card;
    }

    private function formatFunnelDropValue(float $rate, int $count): string
    {
        return number_format($rate, 1).'% / '.number_format($count);
    }

    /**
     * @param  array<string, mixed>  $extraFilters
     */
    private function filteredSessionsForRange(Carbon $from, Carbon $to, array $extraFilters = [], ?string $period = null): Collection
    {
        return $this->rememberQuery(
            $this->queryCacheKey('filteredSessionsForRange', $from, $to, $period, $extraFilters),
            function () use ($from, $to, $extraFilters, $period) {
                $sessions = $this->sessionsInRange($from, $to, $period);

                if ($extraFilters !== []) {
                    $sessionIds = $this->filteredSessionIds($from, $to, $extraFilters, $period);
                    $sessions = $sessions->only($sessionIds->all());
                }

                return $sessions;
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiCardWithComparison(
        string $label,
        float|int $current,
        float|int $previous,
        string $format,
        ?string $tip,
        string $comparisonLabel,
    ): array {
        return array_merge(
            $this->kpiCard($label, $current, $format, $tip),
            ['comparison' => $this->buildMetricComparison($current, $previous, $format, $comparisonLabel)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetricComparison(
        float|int $current,
        float|int $previous,
        string $format,
        string $comparisonLabel,
    ): array {
        return array_merge(
            $this->computePeriodDelta((float) $current, (float) $previous),
            [
                'previous' => $previous,
                'previous_formatted' => $this->formatKpiValue($previous, $format),
                'comparison_label' => $comparisonLabel,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiCard(string $label, float|int $value, string $format, ?string $tip = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'formatted' => $this->formatKpiValue($value, $format),
            'tip' => $tip,
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
     * @param  array{from: Carbon, to: Carbon, label: string, days: int, period?: string}  $range
     * @return array{from: Carbon, to: Carbon, label: string, days: int}
     */
    public function resolvePreviousPeriodRange(array $range): array
    {
        $period = $range['period'] ?? 'custom';

        if ($period === '24h') {
            $yesterday = TrackerTime::yesterdayRangeUtc();

            return [
                'from' => $yesterday['from'],
                'to' => $yesterday['to'],
                'label' => 'yesterday',
                'days' => 1,
                'period' => 'yesterday',
            ];
        }

        if ($period === 'yesterday') {
            $dayBefore = TrackerTime::dayBeforeYesterdayRangeUtc();
            $dayBeforeLocal = TrackerTime::toLocal($dayBefore['from']);

            return [
                'from' => $dayBefore['from'],
                'to' => $dayBefore['to'],
                'label' => $dayBeforeLocal?->format('d M Y') ?? 'day before yesterday',
                'days' => 1,
                'period' => 'yesterday',
            ];
        }

        $from = $range['from'];
        $to = $range['to'];
        $seconds = (int) $from->diffInSeconds($to);
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($seconds);

        $label = match ($period) {
            '7d' => 'previous 7 days',
            '30d', '90d' => 'previous 30 days',
            default => $this->formatPreviousPeriodLabel($prevFrom, $prevTo),
        };

        return [
            'from' => $prevFrom,
            'to' => $prevTo,
            'label' => $label,
            'days' => $range['days'] ?? ((int) $from->diffInDays($to) + 1),
            'period' => $period,
        ];
    }

    private function countUniqueVisitorsFromSessions(Collection $sessions): int
    {
        return $sessions->pluck('visitor_id')->filter()->unique()->count();
    }

    private function effectiveSessionDurationSeconds(ActivityEcomUser $session): int
    {
        return max(0, (int) ($session->session_duration_seconds ?? 0));
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function sessionActionTypeSets(Collection $sessionIds, Carbon $from, Carbon $to): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        return $this->rememberQuery(
            $this->sessionSetCacheKey('sessionActionTypeSets', $from, $to, $sessionIds),
            function () use ($sessionIds, $from, $to) {
                $query = DB::table('activity_ecom_user_actions')
                    ->select('session_id', 'action_type')
                    ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
                    ->whereIn('action_type', [
                        'add_to_cart',
                        'begin_checkout',
                        'proceed_checkout',
                        'payment_success',
                    ]);

                $this->constrainToSessionIds($query, $sessionIds);

                $typeSets = [];

                foreach ($query->get() as $row) {
                    $typeSets[$row->session_id][$row->action_type] = true;
                }

                return $typeSets;
            },
        );
    }

    private function totalStaySecondsFromSessions(Collection $sessions): int
    {
        return (int) $sessions->sum(fn (ActivityEcomUser $session) => $this->effectiveSessionDurationSeconds($session));
    }

    private function avgStaySecondsFromSessions(Collection $sessions): int
    {
        if ($sessions->isEmpty()) {
            return 0;
        }

        return (int) round($this->totalStaySecondsFromSessions($sessions) / $sessions->count());
    }

    /**
     * @return array{
     *     buckets: array<int, array{label: string, min: int, max: int, count: int, pct: float}>,
     *     total_sessions: int,
     *     median_seconds: int,
     *     median_label: string
     * }
     */
    private function buildDurationDistribution(Collection $sessions): array
    {
        $durations = $sessions->map(fn (ActivityEcomUser $session) => $this->effectiveSessionDurationSeconds($session));
        $distribution = SessionDurationBuckets::withCounts($durations);

        return [
            'buckets' => $distribution['buckets'],
            'total_sessions' => $distribution['total_sessions'],
            'median_seconds' => $distribution['median_seconds'],
            'median_label' => $this->visitorAnalytics->formatDuration($distribution['median_seconds']),
        ];
    }

    private function formatPreviousPeriodLabel(Carbon $from, Carbon $to): string
    {
        $fromLocal = TrackerTime::toLocal($from);
        $toLocal = TrackerTime::toLocal($to);

        if ($fromLocal === null || $toLocal === null) {
            return 'previous period';
        }

        if ($fromLocal->isSameDay($toLocal)) {
            return $fromLocal->format('d M Y');
        }

        return $fromLocal->format('d M').' – '.$toLocal->format('d M Y');
    }

    /**
     * @param  array{from: Carbon, to: Carbon, label: string, days: int, period?: string}  $range
     * @param  array<string, mixed>  $extraFilters
     * @return array<string, mixed>
     */
    private function buildSaleConversionMetrics(
        Carbon $from,
        Carbon $to,
        Collection $currentSessions,
        array $range,
        array $extraFilters = [],
    ): array {
        $metricSessionScope = $this->saleMetricSessionScope($extraFilters, $currentSessions);
        $itemQty = $this->sumSaleItemQty($from, $to, $metricSessionScope);
        $revenue = round($this->sumRevenue($from, $to, $metricSessionScope), 2);

        $prevRange = $this->resolvePreviousPeriodRange($range);
        $prevSessions = $this->filteredSessionsForRange(
            $prevRange['from'],
            $prevRange['to'],
            $extraFilters,
            $prevRange['period'] ?? null,
        );

        $prevMetricSessionScope = $this->saleMetricSessionScope($extraFilters, $prevSessions);
        $prevItemQty = $this->sumSaleItemQty($prevRange['from'], $prevRange['to'], $prevMetricSessionScope);
        $prevRevenue = round($this->sumRevenue($prevRange['from'], $prevRange['to'], $prevMetricSessionScope), 2);
        $comparisonLabel = $prevRange['label'];

        return [
            'item_qty' => array_merge([
                'label' => 'Items sold',
                'value' => $itemQty,
                'formatted' => number_format($itemQty),
                'tip' => 'Total product units sold from completed orders in the period (sums line-item quantities from payment_success events in the date range).',
            ], [
                'comparison' => $this->buildMetricComparison($itemQty, $prevItemQty, 'number', $comparisonLabel),
            ]),
            'revenue' => array_merge([
                'label' => 'Sale amount',
                'value' => $revenue,
                'formatted' => '£'.number_format($revenue, 2),
                'tip' => 'Total sale amount from completed orders in the period (sum of payment_success amount_paid in the date range).',
            ], [
                'comparison' => $this->buildMetricComparison($revenue, $prevRevenue, 'currency', $comparisonLabel),
            ]),
            'comparison_label' => $comparisonLabel,
        ];
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: ?string, delta_label: ?string}
     */
    private function computePeriodDelta(float $current, float $compare): array
    {
        if ($compare == 0.0) {
            if ($current > 0) {
                return ['delta_pct' => null, 'delta_direction' => null, 'delta_label' => 'new'];
            }

            return ['delta_pct' => null, 'delta_direction' => null, 'delta_label' => 'no_prior_data'];
        }

        if ($current == 0.0) {
            return ['delta_pct' => -100.0, 'delta_direction' => 'down', 'delta_label' => null];
        }

        $deltaPct = (($current - $compare) / $compare) * 100;

        return [
            'delta_pct' => round($deltaPct, 1),
            'delta_direction' => $deltaPct > 0 ? 'up' : ($deltaPct < 0 ? 'down' : 'flat'),
            'delta_label' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFunnel(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): array
    {
        $sessions = $this->sessionsInRange($from, $to, $period);

        if ($filters !== []) {
            $filteredIds = $this->filteredSessionIds($from, $to, $filters, $period);
            $sessions = $sessions->only($filteredIds->all());
        }

        $sessionIds = $sessions->keys();
        $counts = [];

        foreach (self::FUNNEL_STAGES as $stage) {
            $counts[$stage['key']] = 0;
        }

        if ($sessionIds->isNotEmpty()) {
            $typeToStage = [];
            foreach (self::FUNNEL_STAGES as $stage) {
                foreach ($stage['types'] as $type) {
                    $typeToStage[$type] = $stage['key'];
                }
            }

            $query = ActivityEcomUserAction::query()
                ->select('session_id', 'action_type')
                ->whereIn('action_type', array_keys($typeToStage))
                ->whereBetween('created_at', TrackerTime::storageRange($from, $to));

            $this->constrainActionsToRangeSessions($query, $sessionIds, $from, $to, $period, $filters !== []);

            $seen = [];
            foreach ($query->toBase()->get() as $action) {
                $stageKey = $typeToStage[$action->action_type] ?? null;

                if ($stageKey === null) {
                    continue;
                }

                $seen[$stageKey][$action->session_id] = true;
            }

            foreach ($counts as $stageKey => $_) {
                $counts[$stageKey] = count($seen[$stageKey] ?? []);
            }
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
        $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay();
        $toLocalDay = TrackerTime::toLocal($to)?->copy()->startOfDay();

        if ($fromLocal === null || $toLocalDay === null) {
            $fromLocal = $from->copy()->startOfDay();
            $toLocalDay = $to->copy()->startOfDay();
        }

        $isHourly = $fromLocal->isSameDay($toLocalDay);

        if ($isHourly) {
            $bucket = 'hour';
            $totalDays = 1;
            $periodBuckets = $this->trendHourlyPeriodsForCalendarDay($fromLocal);
        } else {
            $toLocal = TrackerTime::toLocal($to)?->copy()->endOfDay() ?? $to->copy();

            $totalDays = (int) $fromLocal->diffInDays($toLocalDay) + 1;
            $bucket = match (true) {
                $totalDays >= self::TREND_MONTHLY_THRESHOLD_DAYS => 'month',
                $totalDays >= self::TREND_WEEKLY_THRESHOLD_DAYS => 'week',
                default => 'day',
            };
            $periodBuckets = $this->trendPeriods($fromLocal, $toLocal, $bucket);
        }

        $sessions = $this->sessionsInRange($from, $to, $period);

        if ($filters !== []) {
            $filteredIds = $this->filteredSessionIds($from, $to, $filters, $period);
            $sessions = $sessions->only($filteredIds->all());
        }

        $scopedSessionIds = $sessions->keys();
        $restrictToScopedSessions = $filters !== [];
        $bucketCounts = $this->aggregateTrendBuckets(
            $periodBuckets,
            $scopedSessionIds,
        );
        $labels = $bucketCounts['labels'];
        $sessionCounts = $bucketCounts['session_counts'];
        $uniqueVisitorCounts = $bucketCounts['unique_visitors'];
        $itemsSoldCounts = $bucketCounts['items_sold'];
        $conversionRates = $bucketCounts['conversion_rates'];
        $seriesData = $bucketCounts['series'];

        $series = collect([
            [
                'key' => 'unique_visitors',
                'label' => 'Unique visitors',
                'data' => $uniqueVisitorCounts,
                'chart_type' => 'bar',
                'y_axis_id' => 'y',
            ],
            [
                'key' => 'sessions',
                'label' => 'Sessions',
                'data' => $sessionCounts,
                'chart_type' => 'bar',
                'y_axis_id' => 'y',
            ],
            ...collect(self::TREND_FUNNEL_SERIES)->map(fn (array $definition) => [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'data' => $seriesData[$definition['key']],
                'chart_type' => 'line',
                'y_axis_id' => 'y',
            ]),
            [
                'key' => 'items_sold_qty',
                'label' => 'Items sold qty',
                'data' => $itemsSoldCounts,
                'chart_type' => 'line',
                'y_axis_id' => 'y',
            ],
            [
                'key' => 'conversion_rate',
                'label' => 'Conv. rate %',
                'data' => $conversionRates,
                'chart_type' => 'line',
                'y_axis_id' => 'y1',
            ],
        ])->values()->all();

        return [
            'labels' => $labels,
            'series' => $series,
            'unique_visitors' => $uniqueVisitorCounts,
            'sessions' => $sessionCounts,
            'items_sold_qty' => $itemsSoldCounts,
            'conversion_rates' => $conversionRates,
            'bucket' => $bucket,
            'total_days' => $totalDays,
            'use_log_scale' => ! $isHourly && $totalDays > self::TREND_LOG_SCALE_DAYS,
            'range_label' => $this->trendRangeLabel($totalDays, $bucket, $period, $isHourly ? $fromLocal : null),
        ];
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon, 2: string}>
     */
    private function trendHourlyPeriodsForCalendarDay(Carbon $dayLocal): array
    {
        $periods = [];
        $dayStart = $dayLocal->copy()->startOfDay();

        for ($hour = 0; $hour < 24; $hour++) {
            $periodStart = $dayStart->copy()->addHours($hour);
            $periodEnd = $periodStart->copy()->endOfHour();
            $periods[] = [$periodStart, $periodEnd, $periodStart->format('H:i')];
        }

        return $periods;
    }

    /**
     * @param  array<int, array{0: Carbon, 1: Carbon, 2: string}>  $periodBuckets
     * @return array{
     *     labels: array<int, string>,
     *     session_counts: array<int, int>,
     *     unique_visitors: array<int, int>,
     *     items_sold: array<int, int>,
     *     conversion_rates: array<int, float>,
     *     series: array<string, array<int, int>>
     * }
     */
    private function aggregateTrendBuckets(
        array $periodBuckets,
        Collection $scopedSessionIds,
    ): array {
        $bucketCount = count($periodBuckets);
        $labels = [];
        $ranges = [];
        $sessionCounts = array_fill(0, $bucketCount, 0);
        $uniqueVisitorSets = array_fill(0, $bucketCount, []);
        $itemsSoldCounts = array_fill(0, $bucketCount, 0);
        $purchaseSessionsPerBucket = array_fill(0, $bucketCount, []);
        $seriesData = [];
        $typeToSeries = [];

        foreach (self::TREND_FUNNEL_SERIES as $series) {
            $seriesData[$series['key']] = array_fill(0, $bucketCount, 0);

            foreach ($series['types'] as $type) {
                $typeToSeries[$type] = $series['key'];
            }
        }

        foreach ($periodBuckets as $index => [$periodStart, $periodEnd, $label]) {
            $labels[] = $label;
            $ranges[$index] = TrackerTime::storageRange(
                $periodStart->copy()->utc(),
                $periodEnd->copy()->utc(),
            );
        }

        if ($scopedSessionIds->isEmpty()) {
            return $this->emptyTrendBucketResult($labels, $seriesData, $sessionCounts, $itemsSoldCounts);
        }

        [$trendFrom, $trendTo] = TrackerTime::storageRange(
            $periodBuckets[0][0]->copy()->utc(),
            $periodBuckets[$bucketCount - 1][1]->copy()->utc(),
        );

        $sessionQuery = ActivityEcomUser::query()
            ->select('session_id', 'visitor_id', 'created_at')
            ->whereIn('session_id', $scopedSessionIds->values()->all());

        foreach ($sessionQuery->toBase()->get() as $session) {
            $index = $this->trendBucketIndex($this->storedTimestampString($session), $ranges);

            if ($index === null) {
                continue;
            }

            $sessionCounts[$index]++;
            $visitorId = trim((string) ($session->visitor_id ?? ''));

            if ($visitorId !== '') {
                $uniqueVisitorSets[$index][$visitorId] = true;
            }
        }

        $funnelTypes = array_keys($typeToSeries);
        $actionQuery = ActivityEcomUserAction::query()
            ->select('session_id', 'action_type', 'created_at')
            ->whereBetween('created_at', [$trendFrom, $trendTo])
            ->whereIn('action_type', $funnelTypes);

        $this->constrainToSessionIds($actionQuery, $scopedSessionIds);

        foreach ($actionQuery->toBase()->get() as $action) {
            $seriesKey = $typeToSeries[$action->action_type] ?? null;

            if ($seriesKey === null) {
                continue;
            }

            $index = $this->trendBucketIndex($this->storedTimestampString($action), $ranges);

            if ($index === null) {
                continue;
            }

            $seriesData[$seriesKey][$index]++;

            if ($seriesKey === 'purchases') {
                $purchaseSessionsPerBucket[$index][$action->session_id] = true;
            }
        }

        $paymentQuery = ActivityEcomUserAction::query()
            ->select('session_id', 'created_at', 'payment_success')
            ->whereBetween('created_at', [$trendFrom, $trendTo])
            ->where('action_type', 'payment_success');

        $this->constrainToSessionIds($paymentQuery, $scopedSessionIds);

        foreach ($paymentQuery->get() as $action) {
            $index = $this->trendBucketIndex($this->storedTimestampString($action), $ranges);

            if ($index === null) {
                continue;
            }

            $itemsSoldCounts[$index] += $this->paymentActionItemQty($action);
        }

        $uniqueVisitorCounts = [];
        $conversionRates = [];

        foreach ($periodBuckets as $index => $_) {
            $uniqueVisitorCounts[$index] = count($uniqueVisitorSets[$index] ?? []);
            $sessionCount = $sessionCounts[$index];
            $purchaseSessionCount = count($purchaseSessionsPerBucket[$index] ?? []);
            $conversionRates[$index] = $sessionCount > 0
                ? round(($purchaseSessionCount / $sessionCount) * 100, 1)
                : 0.0;
        }

        return [
            'labels' => $labels,
            'session_counts' => $sessionCounts,
            'unique_visitors' => $uniqueVisitorCounts,
            'items_sold' => $itemsSoldCounts,
            'conversion_rates' => $conversionRates,
            'series' => $seriesData,
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<int, int>>  $seriesData
     * @param  array<int, int>  $sessionCounts
     * @param  array<int, int>  $itemsSoldCounts
     * @return array{
     *     labels: array<int, string>,
     *     session_counts: array<int, int>,
     *     unique_visitors: array<int, int>,
     *     items_sold: array<int, int>,
     *     conversion_rates: array<int, float>,
     *     series: array<string, array<int, int>>
     * }
     */
    private function emptyTrendBucketResult(
        array $labels,
        array $seriesData,
        array $sessionCounts,
        array $itemsSoldCounts,
    ): array {
        $bucketCount = count($labels);

        return [
            'labels' => $labels,
            'session_counts' => $sessionCounts,
            'unique_visitors' => array_fill(0, $bucketCount, 0),
            'items_sold' => $itemsSoldCounts,
            'conversion_rates' => array_fill(0, $bucketCount, 0.0),
            'series' => $seriesData,
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $ranges
     */
    private function trendBucketIndex(?string $utcTimestamp, array $ranges): ?int
    {
        if ($utcTimestamp === null || $utcTimestamp === '') {
            return null;
        }

        foreach ($ranges as $index => [$start, $end]) {
            if ($utcTimestamp >= $start && $utcTimestamp <= $end) {
                return $index;
            }
        }

        return null;
    }

    private function storedTimestampString(object $model): ?string
    {
        $value = method_exists($model, 'getRawOriginal')
            ? ($model->getRawOriginal('created_at') ?? $model->created_at ?? null)
            : ($model->created_at ?? null);

        return TrackerTime::formatUtc($value);
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
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryPerformance(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $sessionIds = $this->activitySessionIds($from, $to, $filters, $period);

        $conversionActions = ActivityEcomUserAction::query()
            ->select($this->conversionActionColumns())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', ['add_to_cart', 'proceed_checkout', 'payment_success'])
            ->tap(fn ($query) => $this->constrainToSessionIds($query, $sessionIds))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $conversionSessionIds = $conversionActions->pluck('session_id')->unique()->values();

        $productViews = ActivityEcomUserAction::query()
            ->select($this->categoryViewColumns())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', ['product_view', 'product_view_popup'])
            ->tap(fn ($query) => $this->constrainToSessionIds($query, $sessionIds))
            ->orderBy('created_at')
            ->orderBy('id')
            ->toBase()
            ->get();

        $categoryViewsInRange = ActivityEcomUserAction::query()
            ->select($this->categoryViewColumns())
            ->where('action_type', 'category_view')
            ->whereNotNull('category_name')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->tap(fn ($query) => $this->constrainToSessionIds($query, $sessionIds))
            ->orderBy('created_at')
            ->orderBy('id')
            ->toBase()
            ->get();

        $categoryViewsForAttribution = $categoryViewsInRange;

        if ($conversionSessionIds->isNotEmpty()) {
            $categoryViewsForAttribution = ActivityEcomUserAction::query()
                ->select($this->categoryViewColumns())
                ->where('action_type', 'category_view')
                ->whereNotNull('category_name')
                ->where(function ($query) use ($from, $to, $conversionSessionIds) {
                    $query->whereBetween('created_at', TrackerTime::storageRange($from, $to))
                        ->orWhereIn('session_id', $conversionSessionIds);
                })
                ->tap(fn ($query) => $this->constrainToSessionIds($query, $sessionIds))
                ->orderBy('created_at')
                ->orderBy('id')
                ->toBase()
                ->get();
        }

        $sessionActionsBySession = $this->categoryAttributionSessionActions($conversionSessionIds, $sessionIds);

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];
        /** @var array<string, int> $viewCounts */
        $viewCounts = [];

        foreach ($categoryViewsInRange as $action) {
            $meta = $this->categoryPerformanceMeta($action);
            $key = $meta['key'];
            $rows[$key] ??= $this->emptyCategoryPerformanceRow($meta);
            $viewCounts[$key] = ($viewCounts[$key] ?? 0) + 1;
        }

        foreach ($productViews as $action) {
            $meta = $this->categoryPerformanceMeta($action);

            if (! $this->categoryPerformanceMetaHasIdentity($meta)) {
                continue;
            }

            $key = $meta['key'];
            $rows[$key] ??= $this->emptyCategoryPerformanceRow($meta);
            $viewCounts[$key] = ($viewCounts[$key] ?? 0) + 1;
        }

        foreach ($viewCounts as $key => $count) {
            $rows[$key]['views'] = $count;
        }

        foreach ($conversionActions as $action) {
            $this->bootstrapCategoryRowsFromAction($action, $rows);
        }

        if ($rows === []) {
            return [];
        }

        $relatedSessionIds = $categoryViewsForAttribution->pluck('session_id')
            ->merge($productViews->pluck('session_id'))
            ->merge($conversionActions->pluck('session_id'))
            ->unique()
            ->values();

        $sessionVisitors = ActivityEcomUser::query()
            ->whereIn('session_id', $relatedSessionIds)
            ->pluck('visitor_id', 'session_id')
            ->all();

        $categoryTimelineByScope = $this->buildCategoryViewTimeline($categoryViewsForAttribution, $sessionVisitors);

        foreach ($conversionActions as $action) {
            if ($action->action_type === 'add_to_cart') {
                $this->attributeCategoryAddToCart(
                    $action,
                    $rows,
                    $categoryTimelineByScope,
                    $sessionVisitors,
                    $sessionActionsBySession,
                );

                continue;
            }

            if ($action->action_type === 'proceed_checkout') {
                $this->attributeCategoryProceedCheckout(
                    $action,
                    $rows,
                    $categoryTimelineByScope,
                    $sessionVisitors,
                    $sessionActionsBySession,
                );

                continue;
            }

            $this->attributeCategoryPaymentSuccess(
                $action,
                $rows,
                $categoryTimelineByScope,
                $sessionVisitors,
                $sessionActionsBySession,
            );
        }

        return collect($rows)
            ->map(function (array $row) {
                $row['label'] = TrackerCategoryIdentity::label(
                    (string) ($row['department_name'] ?? ''),
                    (string) ($row['category_name'] ?? ''),
                );
                $row['name'] = $row['label'];
                $views = (int) $row['views'];
                $row['conversion_rate'] = $views > 0
                    ? round(((int) $row['purchases'] / $views) * 100, 1)
                    : 0.0;
                $row['sale_amount'] = round((float) $row['sale_amount'], 2);

                return $row;
            })
            ->sort(function (array $left, array $right) {
                foreach (['sale_items', 'adds', 'views', 'sale_amount'] as $field) {
                    $leftValue = (float) ($left[$field] ?? 0);
                    $rightValue = (float) ($right[$field] ?? 0);

                    if ($leftValue !== $rightValue) {
                        return $rightValue <=> $leftValue;
                    }
                }

                return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            })
            ->when($limit !== null, fn ($collection) => $collection->take($limit))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    public function groupCategoryPerformanceByDepartment(array $categories): array
    {
        /** @var array<string, array<string, mixed>> $departments */
        $departments = [];

        foreach ($categories as $row) {
            $normalized = TrackerCategoryIdentity::normalizeDepartmentName((string) ($row['department_name'] ?? ''));
            $target = in_array($normalized, TrackerCategoryIdentity::DEPARTMENTS, true) ? $normalized : 'Other';
            $categoryName = TrackerCategoryIdentity::displayName((string) ($row['category_name'] ?? ''));

            if ($categoryName === '' || strcasecmp($categoryName, $target) === 0) {
                continue;
            }

            if (! isset($departments[$target])) {
                $departments[$target] = $this->emptyCategoryDepartmentRow($target);
            }

            $departments[$target]['categories'][] = [
                'department_name' => $target,
                'category_name' => $categoryName,
                'category_code' => (string) ($row['category_code'] ?? ''),
                'views' => (int) ($row['views'] ?? 0),
                'adds' => (int) ($row['adds'] ?? 0),
                'proceed_checkouts' => (int) ($row['proceed_checkouts'] ?? 0),
                'sale_items' => (int) ($row['sale_items'] ?? 0),
                'sale_amount' => round((float) ($row['sale_amount'] ?? 0), 2),
            ];
        }

        $result = collect($departments)
            ->map(function (array $department) {
                $department['categories'] = $this->sortCategoryPerformanceRows(collect($department['categories'] ?? []))->values()->all();
                $department['category_count'] = count($department['categories']);
                $department['views'] = (int) collect($department['categories'])->sum('views');
                $department['adds'] = (int) collect($department['categories'])->sum('adds');
                $department['proceed_checkouts'] = (int) collect($department['categories'])->sum('proceed_checkouts');
                $department['sale_items'] = (int) collect($department['categories'])->sum('sale_items');
                $department['sale_amount'] = round((float) collect($department['categories'])->sum('sale_amount'), 2);
                $department['purchases'] = (int) collect($department['categories'])->sum('purchases');

                return $department;
            })
            ->filter(fn (array $department) => $department['category_count'] > 0)
            ->sort(function (array $left, array $right) {
                foreach (['sale_amount', 'sale_items', 'adds', 'views'] as $field) {
                    $leftValue = (float) ($left[$field] ?? 0);
                    $rightValue = (float) ($right[$field] ?? 0);

                    if ($leftValue !== $rightValue) {
                        return $rightValue <=> $leftValue;
                    }
                }

                return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            })
            ->values()
            ->all();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCategoryDepartmentRow(string $name): array
    {
        return [
            'name' => $name,
            'key' => strtolower($name),
            'views' => 0,
            'adds' => 0,
            'proceed_checkouts' => 0,
            'sale_items' => 0,
            'sale_amount' => 0.0,
            'purchases' => 0,
            'categories' => [],
            'category_count' => 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCategoryPerformanceRows(Collection $rows): Collection
    {
        return $rows->sort(function (array $left, array $right) {
            foreach (['sale_items', 'adds', 'views', 'sale_amount'] as $field) {
                $leftValue = (float) ($left[$field] ?? 0);
                $rightValue = (float) ($right[$field] ?? 0);

                if ($leftValue !== $rightValue) {
                    return $rightValue <=> $leftValue;
                }
            }

            return strcmp((string) ($left['category_name'] ?? ''), (string) ($right['category_name'] ?? ''));
        });
    }

    /**
     * @param  Collection<int, object>  $categoryViews
     * @param  array<string, string|null>  $sessionVisitors
     * @return array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>
     */
    private function buildCategoryViewTimeline(Collection $categoryViews, array $sessionVisitors): array
    {
        $timelineByScope = [];

        foreach ($categoryViews as $view) {
            $at = TrackerTime::toUtc($view->created_at);

            if ($at === null) {
                continue;
            }

            $scope = $this->categoryAttributionScope(
                $sessionVisitors[$view->session_id] ?? null,
                $view->session_id,
            );

            $meta = $this->categoryPerformanceMeta($view);

            $timelineByScope[$scope][] = [
                'at' => $at,
                'key' => $meta['key'],
                'meta' => $meta,
            ];
        }

        foreach ($timelineByScope as &$entries) {
            usort($entries, fn (array $left, array $right) => $left['at']->getTimestamp() <=> $right['at']->getTimestamp());
        }
        unset($entries);

        return $timelineByScope;
    }

    private function categoryAttributionScope(?string $visitorId, string $sessionId): string
    {
        return filled($visitorId) ? 'visitor:'.$visitorId : 'session:'.$sessionId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function attributeCategoryAddToCart(
        ActivityEcomUserAction $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
    ): void {
        if ($this->attributeCartQtyToCategories($action, $rows, $categoryTimelineByScope, $sessionVisitors, $sessionActionsBySession)) {
            return;
        }

        $categoryKey = $this->resolveLastCategoryKeyBeforeEvent(
            $action,
            $categoryTimelineByScope,
            $sessionVisitors,
        );

        if ($categoryKey === null || ! isset($rows[$categoryKey])) {
            return;
        }

        $rows[$categoryKey]['adds'] += $this->resolveCartEventQty($action);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function attributeCategoryProceedCheckout(
        ActivityEcomUserAction $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
    ): void {
        $payload = $action->proceed_to_checkout ?? [];

        if ($this->attributeLineItemQtyToCategories(
            $action,
            is_array($payload) ? $payload : [],
            'proceed_checkouts',
            $rows,
            $categoryTimelineByScope,
            $sessionVisitors,
            $sessionActionsBySession,
        )) {
            return;
        }

        $categoryKey = $this->resolveLastCategoryKeyBeforeEvent(
            $action,
            $categoryTimelineByScope,
            $sessionVisitors,
        );

        if ($categoryKey === null || ! isset($rows[$categoryKey])) {
            return;
        }

        $rows[$categoryKey]['proceed_checkouts'] += $this->resolvePayloadEventQty(is_array($payload) ? $payload : []);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @param  Collection<string, Collection<int, ActivityEcomUserAction>>  $sessionActionsBySession
     */
    private function attributeCategoryPaymentSuccess(
        ActivityEcomUserAction $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
    ): void {
        $payload = $action->payment_success ?? [];
        $items = is_array($payload['checkout_info']['items'] ?? null)
            ? $payload['checkout_info']['items']
            : [];
        $sessionActions = $sessionActionsBySession->get($action->session_id, collect());
        $matchedKeys = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = $this->enrichCategoryLineItem(
                $item,
                $action,
                $sessionActions,
                $categoryTimelineByScope,
                $sessionVisitors,
            );
            $categoryKey = $this->resolveCategoryRowForLine($line, $rows);

            if ($categoryKey === null) {
                continue;
            }

            $purchaseLine = $this->extractPurchaseLineIdentity($line);

            if ($purchaseLine === null) {
                continue;
            }

            $rows[$categoryKey]['sale_items'] += $purchaseLine['qty'];
            $rows[$categoryKey]['sale_amount'] += $purchaseLine['revenue'];
            $matchedKeys[$categoryKey] = true;
        }

        if ($matchedKeys !== []) {
            foreach (array_keys($matchedKeys) as $key) {
                $rows[$key]['purchases']++;
            }

            return;
        }

        $categoryKey = $this->resolveLastCategoryKeyBeforeEvent(
            $action,
            $categoryTimelineByScope,
            $sessionVisitors,
        );

        if ($categoryKey === null || ! isset($rows[$categoryKey])) {
            return;
        }

        $rows[$categoryKey]['purchases']++;
        $rows[$categoryKey]['sale_amount'] += $this->paymentAmountPaid($payload);

        if ($items === []) {
            $rows[$categoryKey]['sale_items'] += 1;

            return;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = $this->enrichCategoryLineItem(
                $item,
                $action,
                $sessionActions,
                $categoryTimelineByScope,
                $sessionVisitors,
            );
            $purchaseLine = $this->extractPurchaseLineIdentity($line);

            if ($purchaseLine === null) {
                continue;
            }

            $rows[$categoryKey]['sale_items'] += $purchaseLine['qty'];
            $rows[$categoryKey]['sale_amount'] += $purchaseLine['revenue'];
        }
    }

    /**
     * @param  array<string, list<array{at: Carbon, key: string}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function resolveLastCategoryKeyBeforeEvent(
        ActivityEcomUserAction $event,
        array $categoryTimelineByScope,
        array $sessionVisitors,
    ): ?string {
        $eventAt = TrackerTime::toUtc($event->created_at);

        if ($eventAt === null) {
            return null;
        }

        $scope = $this->categoryAttributionScope(
            $sessionVisitors[$event->session_id] ?? null,
            $event->session_id,
        );
        $timeline = $categoryTimelineByScope[$scope] ?? [];
        $lastKey = null;

        foreach ($timeline as $entry) {
            if ($entry['at']->gt($eventAt)) {
                break;
            }

            $lastKey = $entry['key'];
        }

        return $lastKey;
    }

    private function categoryPerformanceMeta(object $action): array
    {
        return TrackerCategoryIdentity::meta(
            TrackerCategoryIdentity::resolveDepartmentName([
                'department_name' => (string) ($action->department_name ?? ''),
                'page_url' => (string) ($action->page_url ?? ''),
            ]),
            (string) ($action->category_code ?? ''),
            (string) ($action->category_name ?? ''),
            (string) ($action->category_id ?? ''),
        );
    }

    /**
     * @param  array{department_name?: string, category_name?: string, category_code?: string, category_id?: string}  $meta
     */
    private function categoryPerformanceMetaHasIdentity(array $meta): bool
    {
        return trim((string) ($meta['category_name'] ?? '')) !== ''
            || trim((string) ($meta['category_code'] ?? '')) !== ''
            || trim((string) ($meta['category_id'] ?? '')) !== '';
    }

    /**
     * @param  array{key: string, department_name: string, category_name: string, category_code: string, category_id: string, label: string}  $meta
     * @return array<string, mixed>
     */
    private function emptyCategoryPerformanceRow(array $meta): array
    {
        return [
            'key' => $meta['key'],
            'department_name' => $meta['department_name'],
            'category_name' => $meta['category_name'],
            'category_code' => $meta['category_code'],
            'category_id' => $meta['category_id'],
            'label' => $meta['label'],
            'name' => $meta['label'],
            'views' => 0,
            'adds' => 0,
            'proceed_checkouts' => 0,
            'purchases' => 0,
            'sale_items' => 0,
            'sale_amount' => 0.0,
            'conversion_rate' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    private function bootstrapCategoryRowsFromAction(ActivityEcomUserAction $action, array &$rows): void
    {
        foreach ($this->categoryLineItemsFromAction($action) as $line) {
            $this->resolveCategoryRowForLine($line, $rows);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryLineItemsFromAction(ActivityEcomUserAction $action): array
    {
        if ($action->action_type === 'add_to_cart' && is_array($action->add_to_cart)) {
            $items = $action->add_to_cart['items'] ?? $action->add_to_cart['cart_items'] ?? [];

            return is_array($items)
                ? array_values(array_filter($items, 'is_array'))
                : [];
        }

        if ($action->action_type === 'payment_success' && is_array($action->payment_success)) {
            $items = $action->payment_success['checkout_info']['items'] ?? [];

            return is_array($items)
                ? array_values(array_filter($items, 'is_array'))
                : [];
        }

        if ($action->action_type === 'proceed_checkout' && is_array($action->proceed_to_checkout)) {
            $items = $action->proceed_to_checkout['cart_items'] ?? $action->proceed_to_checkout['items'] ?? [];

            return is_array($items)
                ? array_values(array_filter($items, 'is_array'))
                : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function resolveCategoryRowForLine(array $line, array &$rows): ?string
    {
        $existingKey = $this->findMatchingCategoryRowKey($line, $rows);

        if ($existingKey !== null) {
            return $existingKey;
        }

        $meta = TrackerCategoryIdentity::metaFromLine($line);

        if ($meta === null) {
            return null;
        }

        $rows[$meta['key']] ??= $this->emptyCategoryPerformanceRow($meta);
        $this->mergeCategoryRowMeta($rows[$meta['key']], $meta);

        return $meta['key'];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function findMatchingCategoryRowKey(array $line, array $rows): ?string
    {
        foreach ($rows as $key => $row) {
            if (! TrackerCategoryIdentity::lineMatchesRow($line, $row)) {
                continue;
            }

            $meta = TrackerCategoryIdentity::metaFromLine($line);

            if ($meta !== null) {
                $this->mergeCategoryRowMeta($rows[$key], $meta);
            }

            return $key;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{department_name?: string, category_name?: string, category_code?: string, category_id?: string, label?: string}  $meta
     */
    private function mergeCategoryRowMeta(array &$row, array $meta): void
    {
        foreach (['department_name', 'category_name', 'category_code', 'category_id'] as $field) {
            if (($row[$field] ?? '') === '' && ($meta[$field] ?? '') !== '') {
                $row[$field] = $meta[$field];
            }
        }

        $row['label'] = TrackerCategoryIdentity::label(
            (string) ($row['department_name'] ?? ''),
            (string) ($row['category_name'] ?? ''),
        );
        $row['name'] = $row['label'];
    }

    /**
     * @param  Collection<int, string>  $conversionSessionIds
     * @param  Collection<int, string>|null  $sessionIds
     * @return Collection<string, Collection<int, ActivityEcomUserAction>>
     */
    private function categoryAttributionSessionActions(Collection $conversionSessionIds, ?Collection $sessionIds): Collection
    {
        if ($conversionSessionIds->isEmpty()) {
            return collect();
        }

        return ActivityEcomUserAction::query()
            ->select($this->conversionActionColumns())
            ->whereIn('session_id', $conversionSessionIds)
            ->whereIn('action_type', [
                'category_view',
                'product_view',
                'product_view_popup',
                'add_to_cart',
                'proceed_checkout',
                'payment_success',
            ])
            ->when($sessionIds !== null, fn ($query) => $this->constrainToSessionIds($query, $sessionIds))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('session_id');
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @return array<string, mixed>
     */
    private function enrichCategoryLineItem(
        array $line,
        ActivityEcomUserAction $contextAction,
        Collection $sessionActions,
        array $categoryTimelineByScope,
        array $sessionVisitors,
    ): array {
        if (TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $line = $this->mergeCategoryFieldsOntoLine(
            $line,
            $this->sessionProductCategoryMeta($line, $sessionActions),
        );

        if (TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $line = $this->mergeCategoryFieldsOntoLine(
            $line,
            $this->categoryMetaFromTimelineBeforeEvent($contextAction, $categoryTimelineByScope, $sessionVisitors),
        );

        if (TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $departmentName = $this->departmentNameFromSessionProductActions($line, $sessionActions);

        if ($departmentName !== '') {
            $line['department_name'] = $departmentName;
        }

        return TrackerCategoryIdentity::ensureLineCategoryIdentity($line);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function mergeCategoryFieldsOntoLine(array $line, array $meta): array
    {
        foreach (['department_name', 'category_name', 'category_code', 'category_id'] as $field) {
            if (($line[$field] ?? '') === '' && ($meta[$field] ?? '') !== '') {
                $line[$field] = $meta[$field];
            }
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @return array<string, mixed>
     */
    private function sessionProductCategoryMeta(array $line, Collection $sessionActions): array
    {
        $productId = trim((string) ($line['product_id'] ?? ''));
        $productCode = trim((string) ($line['product_code'] ?? ''));

        foreach ($sessionActions as $action) {
            foreach ($this->categoryLineItemsFromAction($action) as $candidate) {
                if (! $this->purchaseLineMatchesProduct($candidate, $productId, $productCode)) {
                    continue;
                }

                $meta = TrackerCategoryIdentity::metaFromLine($candidate);

                if ($meta !== null) {
                    return $meta;
                }
            }
        }

        return [];
    }

    private function purchaseLineMatchesProduct(array $line, string $productId, string $productCode): bool
    {
        $lineProductId = trim((string) ($line['product_id'] ?? ''));
        $lineProductCode = trim((string) ($line['product_code'] ?? ''));

        if ($productId !== '' && $lineProductId !== '' && $productId === $lineProductId) {
            return true;
        }

        return $productCode !== '' && $lineProductCode !== '' && strcasecmp($productCode, $lineProductCode) === 0;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     */
    private function departmentNameFromSessionProductActions(array $line, Collection $sessionActions): string
    {
        $productCode = trim((string) ($line['product_code'] ?? ''));
        $productName = trim((string) ($line['product_name'] ?? ''));

        foreach ($sessionActions as $action) {
            if (! in_array($action->action_type, ['product_view', 'product_view_popup', 'add_to_cart'], true)) {
                continue;
            }

            $actionMatches = ($productCode !== '' && strcasecmp((string) ($action->product_code ?? ''), $productCode) === 0)
                || ($productName !== '' && strcasecmp(trim((string) ($action->product_name ?? '')), $productName) === 0);

            if (! $actionMatches) {
                continue;
            }

            $departmentName = TrackerCategoryIdentity::resolveDepartmentName([
                'department_name' => (string) ($action->department_name ?? ''),
                'page_url' => (string) ($action->page_url ?? ''),
            ]);

            if ($departmentName !== '') {
                return $departmentName;
            }
        }

        foreach ($sessionActions as $action) {
            $departmentName = TrackerCategoryIdentity::resolveDepartmentName([
                'department_name' => (string) ($action->department_name ?? ''),
                'page_url' => (string) ($action->page_url ?? ''),
            ]);

            if ($departmentName !== '') {
                return $departmentName;
            }
        }

        return '';
    }

    /**
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @return array<string, mixed>
     */
    private function categoryMetaFromTimelineBeforeEvent(
        ActivityEcomUserAction $event,
        array $categoryTimelineByScope,
        array $sessionVisitors,
    ): array {
        $eventAt = TrackerTime::toUtc($event->created_at);

        if ($eventAt === null) {
            return [];
        }

        $scope = $this->categoryAttributionScope(
            $sessionVisitors[$event->session_id] ?? null,
            $event->session_id,
        );
        $timeline = $categoryTimelineByScope[$scope] ?? [];
        $meta = [];

        foreach ($timeline as $entry) {
            if ($entry['at']->gt($eventAt)) {
                break;
            }

            $meta = $entry['meta'];
        }

        return $meta;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @param  Collection<string, Collection<int, ActivityEcomUserAction>>  $sessionActionsBySession
     */
    private function attributeCartQtyToCategories(
        ActivityEcomUserAction $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
    ): bool {
        $cart = $action->add_to_cart ?? [];

        return $this->attributeLineItemQtyToCategories(
            $action,
            is_array($cart) ? $cart : [],
            'adds',
            $rows,
            $categoryTimelineByScope,
            $sessionVisitors,
            $sessionActionsBySession,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function attributeLineItemQtyToCategories(
        ActivityEcomUserAction $action,
        array $payload,
        string $counterField,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
    ): bool {
        $items = $payload['items'] ?? $payload['cart_items'] ?? [];
        $matched = false;

        if (! is_array($items) || $items === []) {
            return false;
        }

        $sessionActions = $sessionActionsBySession->get($action->session_id, collect());

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = $this->enrichCategoryLineItem(
                $item,
                $action,
                $sessionActions,
                $categoryTimelineByScope,
                $sessionVisitors,
            );
            $qty = (int) max(1, (float) ($item['qty'] ?? 1));
            $categoryKey = $this->resolveCategoryRowForLine($line, $rows);

            if ($categoryKey === null) {
                continue;
            }

            $rows[$categoryKey][$counterField] += $qty;
            $matched = true;
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $row
     */
    private function lineMatchesCategoryRow(array $item, array $row): bool
    {
        return TrackerCategoryIdentity::lineMatchesRow($item, $row);
    }

    private function resolveCartEventQty(ActivityEcomUserAction $action): int
    {
        $cart = $action->add_to_cart ?? [];

        return $this->resolvePayloadEventQty(is_array($cart) ? $cart : []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayloadEventQty(array $payload): int
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? [];

        if (is_array($items) && $items !== []) {
            $qty = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $qty += (int) max(1, (float) ($item['qty'] ?? 1));
            }

            if ($qty > 0) {
                return $qty;
            }
        }

        return (int) max(1, (float) ($payload['qty'] ?? 1));
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
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
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
        $key = $this->resolveCatalogProductKey($products, $identity);

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
                'sku' => trim((string) ($item['sku'] ?? '')),
                'name' => trim((string) ($item['product_name'] ?? '')),
                'product_id' => trim((string) ($item['product_id'] ?? '')),
                'color_name' => trim((string) ($item['color_name'] ?? '')),
                'size_name' => trim((string) ($item['size_name'] ?? '')),
                'category' => trim((string) ($item['category_name'] ?? '')),
                'category_code' => trim((string) ($item['category_code'] ?? '')),
                'category_id' => trim((string) ($item['category_id'] ?? '')),
                'department_id' => trim((string) ($item['department_id'] ?? '')),
                'department_name' => trim((string) ($item['department_name'] ?? '')),
            ])
            ->filter(fn (array $line) => $line['code'] !== '' || $line['sku'] !== '' || $line['name'] !== '' || $line['product_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{code: string, name: string, product_id: string, qty: int, revenue: float}|null
     */
    private function extractPurchaseLineIdentity(array $item): ?array
    {
        $code = trim((string) ($item['product_code'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? ''));
        $name = trim((string) ($item['product_name'] ?? ''));
        $productId = trim((string) ($item['product_id'] ?? ''));

        if ($code === '' && $sku === '' && $name === '' && $productId === '') {
            return null;
        }

        return [
            'code' => $code,
            'sku' => $sku,
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
        $normalizedCode = strtoupper(trim($code));

        if ($normalizedCode !== '') {
            return 'code:'.$normalizedCode;
        }

        $normalizedId = trim($productId);

        if ($normalizedId !== '') {
            return 'id:'.$normalizedId;
        }

        $normalizedName = $this->normalizeProductName($name);

        if ($normalizedName !== '') {
            return 'name:'.$normalizedName;
        }

        return 'unknown:'.md5($name.$code.$productId);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $catalog
     * @param  array{name?: string, code?: string, product_id?: string, sku?: string}  $identity
     * @param  array{color?: string, size?: string, sku?: string}  $variant
     */
    private function resolveCatalogProductKey(Collection $catalog, array $identity, array $variant = []): string
    {
        $code = strtoupper(trim((string) ($identity['code'] ?? '')));
        $sku = strtoupper(trim((string) ($variant['sku'] ?? $identity['sku'] ?? '')));
        $name = $this->normalizeProductName((string) ($identity['name'] ?? ''));
        $productId = trim((string) ($identity['product_id'] ?? ''));

        if ($code !== '' && $catalog->has('code:'.$code)) {
            return 'code:'.$code;
        }

        if ($productId !== '' && $catalog->has('id:'.$productId)) {
            return 'id:'.$productId;
        }

        if ($name !== '' && $catalog->has('name:'.$name)) {
            return 'name:'.$name;
        }

        foreach ($catalog as $key => $product) {
            if ($code !== '' && strtoupper(trim((string) ($product['code'] ?? ''))) === $code) {
                return (string) $key;
            }

            if ($code !== '' && $this->catalogProductVariantSkuMatches($product, $code)) {
                return (string) $key;
            }

            if ($sku !== '' && $this->catalogProductVariantSkuMatches($product, $sku)) {
                return (string) $key;
            }

            if ($productId !== '' && trim((string) ($product['product_id'] ?? '')) === $productId) {
                return (string) $key;
            }

            if ($name !== '' && $this->normalizeProductName((string) ($product['name'] ?? '')) === $name) {
                return (string) $key;
            }
        }

        return $this->productIdentityKey(
            (string) ($identity['name'] ?? ''),
            (string) ($identity['code'] ?? ''),
            (string) ($identity['product_id'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function catalogProductVariantSkuMatches(array $product, string $needle): bool
    {
        $needle = strtoupper(trim($needle));

        if ($needle === '') {
            return false;
        }

        $variants = $product['variants'] ?? collect();

        if (! $variants instanceof Collection) {
            return false;
        }

        foreach ($variants as $variant) {
            if (strtoupper(trim((string) ($variant['sku'] ?? ''))) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $variants
     * @param  array{color?: string, size?: string, sku?: string}  $variant
     */
    private function resolveCatalogVariantKey(Collection $variants, array $variant, string $productCode = ''): string
    {
        $color = trim((string) ($variant['color'] ?? ''));
        $size = trim((string) ($variant['size'] ?? ''));
        $sku = trim((string) ($variant['sku'] ?? ''));
        $productCode = strtoupper(trim($productCode));
        $exactKey = $this->catalogVariantKey($color, $size, $sku);

        if ($variants->has($exactKey)) {
            return $exactKey;
        }

        foreach ([
            $this->catalogVariantKey($color, $size, ''),
            $this->catalogVariantKey($color, '', $sku),
            $this->catalogVariantKey($color, '', ''),
        ] as $candidate) {
            if ($variants->has($candidate)) {
                return $candidate;
            }
        }

        if ($color !== '') {
            foreach ($variants as $key => $row) {
                if (strcasecmp((string) ($row['color'] ?? ''), $color) !== 0) {
                    continue;
                }

                $rowSize = trim((string) ($row['size'] ?? ''));
                $rowSku = strtoupper(trim((string) ($row['sku'] ?? '')));

                if ($size !== '' && $rowSize !== '' && strcasecmp($rowSize, $size) !== 0) {
                    continue;
                }

                if ($sku !== '' && $rowSku !== '' && $rowSku !== strtoupper($sku)) {
                    continue;
                }

                if ($productCode !== '' && $rowSku !== '' && $rowSku !== $productCode) {
                    continue;
                }

                return (string) $key;
            }
        }

        if ($productCode !== '') {
            foreach ($variants as $key => $row) {
                if (strtoupper(trim((string) ($row['sku'] ?? ''))) !== $productCode) {
                    continue;
                }

                if ($color !== '' && strcasecmp((string) ($row['color'] ?? ''), $color) !== 0) {
                    continue;
                }

                if ($size !== '' && trim((string) ($row['size'] ?? '')) !== '' && strcasecmp((string) ($row['size'] ?? ''), $size) !== 0) {
                    continue;
                }

                return (string) $key;
            }
        }

        return $exactKey;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function shouldPreferIncomingProductCode(array $product, string $existingCode, string $incomingCode): bool
    {
        if ($existingCode === '') {
            return true;
        }

        if (strcasecmp($existingCode, $incomingCode) === 0) {
            return false;
        }

        if ($this->catalogProductVariantSkuMatches($product, $incomingCode)
            && ! $this->catalogProductVariantSkuMatches($product, $existingCode)) {
            return false;
        }

        return strlen($incomingCode) > strlen($existingCode);
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
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->when($sessionIds !== null, fn ($q) => $q->whereIn('session_id', $sessionIds));

        $actionQuery()
            ->whereIn('action_type', self::PRODUCT_VIEW_TYPES)
            ->whereNotNull('general_color_name')
            ->get()
            ->each(function (ActivityEcomUserAction $action) use ($variants) {
                $variantSku = trim((string) ($action->sku ?: ''));

                if ($variantSku === '') {
                    return;
                }

                $this->incrementColorVariant(
                    $variants,
                    $variantSku,
                    (string) $action->general_color_name,
                    $action->product_name,
                    'viewed',
                    1,
                    (string) ($action->product_code ?? ''),
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
                        $identity['product_code'] ?? '',
                    );
                }

                if ($items === [] && $action->general_color_name) {
                    $variantSku = trim((string) ($action->sku ?: ''));

                    if ($variantSku === '') {
                        return;
                    }

                    $this->incrementColorVariant(
                        $variants,
                        $variantSku,
                        (string) $action->general_color_name,
                        $action->product_name,
                        'purchased',
                        1,
                        (string) ($action->product_code ?? ''),
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
                        'sku' => $row['variant_sku'] ?: $row['product_code'],
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
                    'top_performance' => ['label' => 'Performance', 'hint' => 'Sale items, then adds, views, and sale amount'],
                    'top_revenue' => ['label' => 'Revenue', 'hint' => 'Highest purchase revenue first'],
                    'top_views' => ['label' => 'Views', 'hint' => 'Most product_view events first'],
                    'top_adds' => ['label' => 'Cart adds', 'hint' => 'Most add_to_cart events first'],
                    'top_purchases' => ['label' => 'Purchases', 'hint' => 'Most purchase orders first'],
                    'top_qty' => ['label' => 'Sale items', 'hint' => 'Highest quantity sold first'],
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
                    'code_asc' => ['label' => 'Product code A–Z', 'hint' => 'Sort by parent product code ascending'],
                    'code_desc' => ['label' => 'Product code Z–A', 'hint' => 'Sort by parent product code descending'],
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
                    'qty_asc' => ['label' => 'Sale items · lowest first', 'hint' => 'Lowest quantity sold first'],
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
        return 'top_performance';
    }

    public function resolveProductCatalogSort(?string $sortBy): string
    {
        $legacy = [
            'revenue_desc' => 'top_revenue',
            'views_desc' => 'top_views',
            'adds_desc' => 'top_adds',
            'purchases_desc' => 'top_purchases',
            'qty_desc' => 'top_qty',
            'performance_desc' => 'top_performance',
        ];

        $sortBy = $legacy[$sortBy ?? ''] ?? $sortBy;
        $keys = array_keys($this->productCatalogSortOptions());

        return in_array($sortBy ?? '', $keys, true) ? (string) $sortBy : 'top_performance';
    }

    private function normalizeProductCatalogSortKey(string $sortBy): string
    {
        return match ($sortBy) {
            'top_performance' => 'performance_desc',
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
        $period = $options['period'] ?? null;
        $sessionIds = $this->activitySessionIds($from, $to, $filters, $period);
        /** @var Collection<string, array{key: string, name: string, code: string, category: string, variants: Collection<string, array<string, mixed>>}> $catalog */
        $catalog = collect();

        $viewRows = DB::table('activity_ecom_user_actions')
            ->select('product_name', 'product_code', 'general_color_name', 'sku', 'category_name')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->tap(fn ($q) => $this->constrainToSessionIds($q, $sessionIds))
            ->whereIn('action_type', self::PRODUCT_VIEW_TYPES)
            ->get();

        foreach ($viewRows as $action) {
            $this->accumulateCatalogEvent($catalog, [
                'name' => (string) ($action->product_name ?? ''),
                'code' => (string) ($action->product_code ?? ''),
                'product_id' => '',
            ], [
                'color' => (string) ($action->general_color_name ?? ''),
                'size' => '',
                'sku' => trim((string) ($action->sku ?: '')),
                'category' => (string) ($action->category_name ?? ''),
            ], views: 1);
        }

        $actions = ActivityEcomUserAction::query()
            ->select($this->productCatalogActionColumns())
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->tap(fn ($q) => $this->constrainToSessionIds($q, $sessionIds))
            ->whereIn('action_type', ['add_to_cart', 'proceed_checkout', 'payment_success'])
            ->get()
            ->sortBy(fn (ActivityEcomUserAction $action) => match ($action->action_type) {
                'add_to_cart' => 1,
                'proceed_checkout' => 2,
                'payment_success' => 3,
                default => 4,
            })
            ->values();

        foreach ($actions as $action) {
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
                        'sku' => trim((string) ($cart['sku'] ?? $action->sku ?? '')),
                        'category' => $defaultCategory,
                    ], adds: 1);

                    continue;
                }

                foreach ($lines as $line) {
                    $this->accumulateCatalogEvent($catalog, $line, [
                        'color' => (string) ($line['color_name'] ?? $cart['color_name'] ?? $action->general_color_name ?? ''),
                        'size' => (string) ($line['size_name'] ?? $cart['size_name'] ?? ''),
                        'sku' => trim((string) ($line['sku'] ?? '')),
                        'category' => (string) ($line['category'] ?? $defaultCategory),
                    ], adds: 1);
                }

                continue;
            }

            if ($action->action_type === 'proceed_checkout') {
                $checkout = $action->proceed_to_checkout ?? [];
                $lines = $this->cartPayloadLineItems(is_array($checkout) ? $checkout : []);
                $defaultCategory = (string) ($action->category_name ?? '');

                if ($lines === []) {
                    $this->accumulateCatalogEvent($catalog, [
                        'name' => (string) ($action->product_name ?? ''),
                        'code' => (string) ($action->product_code ?? ''),
                        'product_id' => '',
                    ], [
                        'color' => (string) ($action->general_color_name ?? ''),
                        'size' => '',
                        'sku' => trim((string) ($action->sku ?? '')),
                        'category' => $defaultCategory,
                    ], proceed_checkouts: 1);

                    continue;
                }

                foreach ($lines as $line) {
                    $this->accumulateCatalogEvent($catalog, $line, [
                        'color' => (string) ($line['color_name'] ?? $action->general_color_name ?? ''),
                        'size' => (string) ($line['size_name'] ?? ''),
                        'sku' => trim((string) ($line['sku'] ?? '')),
                        'category' => (string) ($line['category'] ?? $defaultCategory),
                    ], proceed_checkouts: 1);
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
                    'sku' => trim((string) ($action->sku ?? '')),
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
                    'sku' => trim((string) ($line['sku'] ?? '')),
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
        int $proceed_checkouts = 0,
        int $purchases = 0,
        int $qty = 0,
        float $revenue = 0.0,
    ): void {
        $productKey = $this->resolveCatalogProductKey($catalog, $identity, $variant);

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
        } elseif ($incomingCode !== '' && $this->shouldPreferIncomingProductCode($product, $code, $incomingCode)) {
            $code = $incomingCode;
        }

        $category = $product['category'] ?: trim((string) ($variant['category'] ?? ''));

        $variants = $product['variants'];
        $variantKey = $this->resolveCatalogVariantKey($variants, $variant, $incomingCode);

        $variantRow = $variants->get($variantKey, [
            'color' => trim((string) ($variant['color'] ?? '')),
            'size' => trim((string) ($variant['size'] ?? '')),
            'sku' => trim((string) ($variant['sku'] ?? '')),
            'category' => $category,
            'views' => 0,
            'adds' => 0,
            'proceed_checkouts' => 0,
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

        if ($variantRow['size'] === '' && trim((string) ($variant['size'] ?? '')) !== '') {
            $variantRow['size'] = trim((string) $variant['size']);
        }

        $variantRow['views'] += $views;
        $variantRow['adds'] += $adds;
        $variantRow['proceed_checkouts'] += $proceed_checkouts;
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
                'product_code' => $product['code'],
                'category' => $category,
                'views' => (int) $variants->sum('views'),
                'adds' => (int) $variants->sum('adds'),
                'proceed_checkouts' => (int) $variants->sum('proceed_checkouts'),
                'purchases' => (int) $variants->sum('purchases'),
                'qty' => (int) $variants->sum('qty'),
                'revenue' => round((float) $variants->sum('revenue'), 2),
                'variant_count' => $variants->count(),
                'variants' => $variants->values()->all(),
            ];
        })->values();

        if ($search !== '') {
            $products = $products->filter(function (array $product) use ($search) {
                if (str_contains(strtolower($product['name']), $search)
                    || str_contains(strtolower($product['code']), $search)) {
                    return true;
                }

                foreach ($product['variants'] as $variant) {
                    if (str_contains(strtolower((string) ($variant['sku'] ?? '')), $search)) {
                        return true;
                    }
                }

                return false;
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

        if ($sortBy === 'performance_desc') {
            return $this->sortProductCatalogByPerformancePriority($products);
        }

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
    private function sortProductCatalogByPerformancePriority(Collection $products): Collection
    {
        return $products->sort(function (array $left, array $right) {
            foreach (['qty', 'adds', 'views', 'revenue'] as $field) {
                $leftValue = (float) ($left[$field] ?? 0);
                $rightValue = (float) ($right[$field] ?? 0);

                if ($leftValue !== $rightValue) {
                    return $rightValue <=> $leftValue;
                }
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });
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

        $sku = trim((string) ($item['sku'] ?? ''));
        $productCode = trim((string) ($item['product_code'] ?? ''));
        $productId = trim((string) ($item['product_id'] ?? ''));
        $productName = trim((string) ($item['product_name'] ?? ''));

        if ($sku === '' && $productCode === '' && $productId === '' && $productName === '') {
            return null;
        }

        return [
            'sku' => $sku !== '' ? $sku : $productId,
            'product_code' => $productCode,
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

            if ($sku !== '' && strcasecmp($variant['variant_sku'] ?: $variant['product_code'], $sku) === 0) {
                return true;
            }

            return $productId !== '' && strcasecmp($variant['variant_sku'] ?: $variant['product_code'], $productId) === 0;
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
        string $variantSku,
        string $colorName,
        ?string $productName,
        string $metric,
        int $quantity = 1,
        string $parentProductCode = '',
    ): void {
        $this->incrementColorVariantByKey(
            $variants,
            $this->colorVariantKey($variantSku, $colorName),
            $variantSku,
            $colorName,
            $productName,
            $metric,
            $quantity,
            $parentProductCode,
        );
    }

    /**
     * @param  Collection<string, array{product_name: string, color_name: string, product_code: string, variant_sku: string, viewed: int, purchased: int}>  $variants
     */
    private function incrementColorVariantByKey(
        Collection $variants,
        string $key,
        string $variantSku,
        string $colorName,
        ?string $productName,
        string $metric,
        int $quantity = 1,
        string $parentProductCode = '',
    ): void {
        $variantSku = trim($variantSku);
        $colorName = trim($colorName);
        $parentProductCode = trim($parentProductCode);

        if ($variantSku === '' || $colorName === '') {
            return;
        }

        $existing = $variants->get($key, [
            'product_name' => $productName ?: 'Unknown product',
            'color_name' => $colorName,
            'product_code' => $parentProductCode,
            'variant_sku' => $variantSku,
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

        if ($parentProductCode !== '' && ($existing['product_code'] === '' || strlen($parentProductCode) >= strlen($existing['product_code']))) {
            $existing['product_code'] = $parentProductCode;
        }

        if ($existing['variant_sku'] === '' || strlen($variantSku) >= strlen($existing['variant_sku'])) {
            $existing['variant_sku'] = $variantSku;
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
    private function buildCartAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', $limit, $filters, 'begin_checkout', $period);

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBeginCheckoutAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'begin_checkout', 'begin_checkout', $limit, $filters, 'proceed_checkout', $period);

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProceedCheckoutAbandonment(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $abandonment = $this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', $limit, $filters, 'payment_success', $period);

        return [
            'session_count' => $abandonment['total_count'],
            'at_stake' => $abandonment['total_at_stake'],
            'rows' => $abandonment['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentSuccessEvents(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $allowedSessionIds = $this->activitySessionIds($from, $to, $filters, $period);

        if ($allowedSessionIds->isEmpty()) {
            return [
                'session_count' => 0,
                'at_stake' => 0.0,
                'rows' => [],
            ];
        }

        $actionsQuery = ActivityEcomUserAction::query()
            ->select('event_id', 'session_id', 'created_at', 'payment_success')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->orderByDesc('created_at');

        $this->constrainToSessionIds($actionsQuery, $allowedSessionIds);

        $rows = $this->uniquePaymentSuccessActions($actionsQuery->get())
            ->map(function (ActivityEcomUserAction $action) {
                $payload = $action->payment_success ?? [];

                return $this->formatRecoverableSessionRow(
                    (string) $action->session_id,
                    $this->paymentAmountPaid(is_array($payload) ? $payload : []),
                    $action->created_at,
                    $this->paymentActionItemQty($action),
                );
            })
            ->sortByDesc(fn (array $row) => $row['_sort_at']?->timestamp ?? 0)
            ->values();

        $result = $this->finalizeRecoverableSessionRows($rows, $limit);

        return [
            'session_count' => $result['total_count'],
            'at_stake' => $result['total_at_stake'],
            'rows' => $result['rows'],
        ];
    }

    /**
     * @return array{session_id: string, session_label: string, value: float, occurred_ago: string, activity_url: string, _sort_at: ?Carbon}
     */
    private function formatRecoverableSessionRow(string $sessionId, float $value, mixed $occurredAt, int $qty = 1): array
    {
        return [
            'session_id' => $sessionId,
            'session_label' => substr($sessionId, 0, 8).'…',
            'qty' => max(0, $qty),
            'value' => round($value, 2),
            'occurred_ago' => TrackerTime::diffForHumansFromStorage($occurredAt) ?? '—',
            'activity_url' => EcomTrackerViewData::activityShowUrl($sessionId),
            '_sort_at' => TrackerTime::fromStorage($occurredAt),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array{total_count: int, total_at_stake: float, rows: array<int, array<string, mixed>>}
     */
    private function finalizeRecoverableSessionRows(Collection $rows, ?int $limit): array
    {
        $totalAtStake = round($rows->sum('value'), 2);
        $limited = $limit !== null ? $rows->take($limit) : $rows;
        $displayRows = $limited
            ->map(function (array $row) {
                unset($row['_sort_at']);

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
     * @return array{total_count: int, total_at_stake: float, rows: array<int, array<string, mixed>>}
     */
    private function abandonedSessions(Carbon $from, Carbon $to, string $stage, string $payloadKey, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], string $excludeActionType = 'payment_success', ?string $period = null): array
    {
        $full = $this->rememberQuery(
            $this->queryCacheKey('abandonedSessions', $from, $to, $stage, $payloadKey, $filters, $excludeActionType, $period),
            fn () => $this->queryAbandonedSessions($from, $to, $stage, $payloadKey, $filters, $excludeActionType, $period),
        );

        return $this->finalizeRecoverableSessionRows(
            collect($full['rows']),
            $limit,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_count: int, total_at_stake: float, rows: array<int, array<string, mixed>>}
     */
    private function queryAbandonedSessions(
        Carbon $from,
        Carbon $to,
        string $stage,
        string $payloadKey,
        array $filters,
        string $excludeActionType,
        ?string $period = null,
    ): array {
        $allowedSessionIds = $this->activitySessionIds($from, $to, $filters, $period);

        $candidatesQuery = ActivityEcomUserAction::query()
            ->select('session_id', 'created_at', $payloadKey)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', $stage)
            ->orderByDesc('created_at');

        if ($allowedSessionIds->isEmpty()) {
            return ['total_count' => 0, 'total_at_stake' => 0.0, 'rows' => []];
        }

        $this->constrainToSessionIds($candidatesQuery, $allowedSessionIds);

        $candidates = $candidatesQuery->get()->groupBy('session_id');
        $excludedSessionIds = $this->sessionIdsHavingActionType($candidates->keys(), $excludeActionType);
        $rows = collect();

        foreach ($candidates as $sessionId => $stageActions) {
            if (isset($excludedSessionIds[$sessionId])) {
                continue;
            }

            $latest = $stageActions->first();
            $payload = is_array($latest->{$payloadKey} ?? null) ? $latest->{$payloadKey} : [];

            $rows->push($this->formatRecoverableSessionRow(
                (string) $sessionId,
                (float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0),
                $latest->created_at,
                $this->resolvePayloadEventQty($payload),
            ));
        }

        return $this->finalizeRecoverableSessionRows(
            $rows->sortByDesc(fn (array $row) => $row['_sort_at']?->timestamp ?? 0)->values(),
            null,
        );
    }

    /**
     * @return array{by_device: array<int, array<string, mixed>>, by_browser: array<int, array<string, mixed>>}
     */
    private function buildDeviceBreakdown(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null;

        $sessionsQuery = ActivityEcomUser::query()
            ->select('session_id', 'device_type', 'browser');

        if ($sessionIds !== null) {
            if ($sessionIds->isEmpty()) {
                return ['by_device' => [], 'by_browser' => []];
            }

            $this->constrainToSessionIds($sessionsQuery, $sessionIds);
        } else {
            TrackerTime::applyEcomActivitySessionScope($sessionsQuery, $from, $to, $period);
        }

        $sessions = $sessionsQuery->get();

        if ($sessions->isEmpty()) {
            return ['by_device' => [], 'by_browser' => []];
        }

        $deviceBuckets = [];
        $browserBuckets = [];
        $sessionDeviceMap = [];
        $sessionBrowserMap = [];

        foreach ($sessions as $session) {
            $deviceLabel = $this->normalizeDeviceBrowserLabel($session->device_type, 'Unknown device');
            $browserLabel = $this->normalizeDeviceBrowserLabel($session->browser, 'Unknown browser');

            $sessionDeviceMap[$session->session_id] = $deviceLabel;
            $sessionBrowserMap[$session->session_id] = $browserLabel;

            $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'sessions');
            $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'sessions');
        }

        $beginCheckoutSeen = [];
        $proceedCheckoutSeen = [];
        $addToCartSeen = [];
        $devicePurchaseSeen = [];
        $browserPurchaseSeen = [];

        [$funnelActions, $paymentActions] = $this->deviceTrafficActions($from, $to, $sessionIds, $period);

        foreach ($funnelActions as $action) {
            $sessionId = $action->session_id;
            $deviceLabel = $sessionDeviceMap[$sessionId] ?? null;
            $browserLabel = $sessionBrowserMap[$sessionId] ?? null;

            if (in_array($action->action_type, self::PRODUCT_VIEW_TYPES, true)) {
                if ($deviceLabel) {
                    $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'views');
                }

                if ($browserLabel) {
                    $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'views');
                }
            }

            if ($action->action_type === 'add_to_cart' && ! isset($addToCartSeen[$sessionId])) {
                $addToCartSeen[$sessionId] = true;

                if ($deviceLabel) {
                    $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'add_to_cart');
                }

                if ($browserLabel) {
                    $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'add_to_cart');
                }
            }

            if ($action->action_type === 'begin_checkout' && ! isset($beginCheckoutSeen[$sessionId])) {
                $beginCheckoutSeen[$sessionId] = true;

                if ($deviceLabel) {
                    $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'begin_checkout');
                }

                if ($browserLabel) {
                    $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'begin_checkout');
                }
            }

            if ($action->action_type === 'proceed_checkout' && ! isset($proceedCheckoutSeen[$sessionId])) {
                $proceedCheckoutSeen[$sessionId] = true;

                if ($deviceLabel) {
                    $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'proceed_checkout');
                }

                if ($browserLabel) {
                    $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'proceed_checkout');
                }
            }
        }

        foreach ($paymentActions as $action) {
            $sessionId = $action->session_id;
            $deviceLabel = $sessionDeviceMap[$sessionId] ?? null;
            $browserLabel = $sessionBrowserMap[$sessionId] ?? null;
            $soldQty = $this->paymentActionItemQty($action);
            $revenue = $this->paymentAmountPaid($action->payment_success ?? []);

            if ($deviceLabel) {
                if (! isset($devicePurchaseSeen[$sessionId])) {
                    $devicePurchaseSeen[$sessionId] = true;
                    $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'purchases');
                }

                $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'sold_qty', $soldQty);
                $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'revenue', $revenue);
            }

            if ($browserLabel) {
                if (! isset($browserPurchaseSeen[$sessionId])) {
                    $browserPurchaseSeen[$sessionId] = true;
                    $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'purchases');
                }

                $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'sold_qty', $soldQty);
                $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'revenue', $revenue);
            }
        }

        return [
            'by_device' => $this->finalizeDeviceBrowserRows($deviceBuckets),
            'by_browser' => $this->finalizeDeviceBrowserRows($browserBuckets, self::DEVICE_BROWSER_DISPLAY_LIMIT),
        ];
    }

    private function normalizeDeviceBrowserLabel(?string $value, string $fallback): string
    {
        $label = trim((string) $value);

        if ($label === '') {
            return $fallback;
        }

        return ucfirst($label);
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $buckets
     */
    private function incrementDeviceBrowserBucket(array &$buckets, string $label, string $field, int|float $amount = 1): void
    {
        if (! isset($buckets[$label])) {
            $buckets[$label] = [
                'label' => $label,
                'sessions' => 0,
                'views' => 0,
                'add_to_cart' => 0,
                'begin_checkout' => 0,
                'proceed_checkout' => 0,
                'purchases' => 0,
                'sold_qty' => 0,
                'revenue' => 0.0,
            ];
        }

        if ($field === 'revenue') {
            $buckets[$label][$field] = round((float) $buckets[$label][$field] + (float) $amount, 2);

            return;
        }

        $buckets[$label][$field] = (int) $buckets[$label][$field] + (int) $amount;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function sortAcquisitionRows(Collection $rows): Collection
    {
        return $rows
            ->sort(function (array $a, array $b): int {
                foreach (['sold_qty', 'add_to_cart', 'views', 'revenue'] as $field) {
                    $left = $field === 'revenue' ? (float) ($a[$field] ?? 0) : (int) ($a[$field] ?? 0);
                    $right = $field === 'revenue' ? (float) ($b[$field] ?? 0) : (int) ($b[$field] ?? 0);

                    if ($left !== $right) {
                        return $right <=> $left;
                    }
                }

                return 0;
            })
            ->values();
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $buckets
     * @return array<int, array<string, mixed>>
     */
    private function finalizeDeviceBrowserRows(array $buckets, ?int $limit = null): array
    {
        $rows = $this->sortAcquisitionRows(collect($buckets)->values());

        if ($limit !== null && $rows->count() > $limit) {
            $top = $rows->take($limit);
            $rest = $rows->slice($limit);

            $top->push([
                'label' => 'Other',
                'sessions' => (int) $rest->sum('sessions'),
                'views' => (int) $rest->sum('views'),
                'add_to_cart' => (int) $rest->sum('add_to_cart'),
                'begin_checkout' => (int) $rest->sum('begin_checkout'),
                'proceed_checkout' => (int) $rest->sum('proceed_checkout'),
                'purchases' => (int) $rest->sum('purchases'),
                'sold_qty' => (int) $rest->sum('sold_qty'),
                'revenue' => round((float) $rest->sum('revenue'), 2),
            ]);

            $rows = $top;
        }

        $totalSessions = max(1, (int) $rows->sum('sessions'));

        return $rows
            ->map(function (array $row) use ($totalSessions) {
                $sessions = (int) $row['sessions'];
                $purchases = (int) $row['purchases'];

                return [
                    'label' => (string) $row['label'],
                    'sessions' => $sessions,
                    'share' => round(($sessions / $totalSessions) * 100, 1),
                    'views' => (int) $row['views'],
                    'add_to_cart' => (int) $row['add_to_cart'],
                    'begin_checkout' => (int) $row['begin_checkout'],
                    'proceed_checkout' => (int) $row['proceed_checkout'],
                    'purchases' => $purchases,
                    'sold_qty' => (int) $row['sold_qty'],
                    'revenue' => round((float) $row['revenue'], 2),
                    'conversion_rate' => $sessions > 0
                        ? round(($purchases / $sessions) * 100, 1)
                        : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTrafficSources(Carbon $from, Carbon $to, ?int $limit = self::TABLE_DISPLAY_LIMIT, array $filters = [], ?string $period = null): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null;

        $sessionsQuery = ActivityEcomUser::query()
            ->select('id', 'session_id', 'utm_source', 'utm_medium', 'utm_campaign', 'landing_page');

        if ($sessionIds !== null) {
            if ($sessionIds->isEmpty()) {
                return [];
            }

            $this->constrainToSessionIds($sessionsQuery, $sessionIds);
        } else {
            TrackerTime::applyEcomActivitySessionScope($sessionsQuery, $from, $to, $period);
        }

        $sessions = $sessionsQuery->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIdList = $sessions->pluck('session_id');
        $needsUrlAttribution = $sessions
            ->filter(function (ActivityEcomUser $session) {
                if (filled($session->utm_source) && filled($session->utm_medium)) {
                    return false;
                }

                $fromLanding = SessionTrafficAttribution::parseFromUrl($session->landing_page);

                return ! filled($fromLanding['utm_source'] ?? null)
                    || ! filled($fromLanding['utm_medium'] ?? null);
            })
            ->pluck('session_id');
        $actionUrlsBySession = $this->trafficActionUrlsBySession($needsUrlAttribution, $from, $to, $needsUrlAttribution);
        $referersBySession = $this->trafficFirstReferersBySession($needsUrlAttribution, $from, $to, $needsUrlAttribution);

        $buckets = [];
        $sessionBucketMap = [];

        foreach ($sessions as $session) {
            $hasStoredUtm = filled($session->utm_source) && filled($session->utm_medium);
            $bucket = SessionTrafficAttribution::resolvedTrafficBucket(
                $session,
                $hasStoredUtm ? [''] : $actionUrlsBySession->get($session->session_id, ['']),
                $hasStoredUtm ? '' : $referersBySession->get($session->session_id, ''),
            );
            $source = $bucket['source'];
            $medium = $bucket['medium'];
            $key = $source."\0".$medium;

            $sessionBucketMap[$session->session_id] = $key;
            $this->incrementTrafficSourceBucket($buckets, $key, $source, $medium, 'sessions');
        }

        $addToCartSeen = [];
        $beginCheckoutSeen = [];
        $proceedCheckoutSeen = [];
        $paymentSuccessSeen = [];

        [$funnelActions, $paymentActions] = $this->deviceTrafficActions($from, $to, $sessionIds, $period);

        foreach ($funnelActions as $action) {
            $bucketKey = $sessionBucketMap[$action->session_id] ?? null;

            if ($bucketKey === null) {
                continue;
            }

            $sessionId = $action->session_id;

            if (in_array($action->action_type, self::PRODUCT_VIEW_TYPES, true)) {
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'views');
            }

            if ($action->action_type === 'add_to_cart' && ! isset($addToCartSeen[$sessionId])) {
                $addToCartSeen[$sessionId] = true;
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'add_to_cart');
            }

            if ($action->action_type === 'begin_checkout' && ! isset($beginCheckoutSeen[$sessionId])) {
                $beginCheckoutSeen[$sessionId] = true;
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'begin_checkout');
            }

            if ($action->action_type === 'proceed_checkout' && ! isset($proceedCheckoutSeen[$sessionId])) {
                $proceedCheckoutSeen[$sessionId] = true;
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'proceed_checkout');
            }
        }

        foreach ($paymentActions as $action) {
            $bucketKey = $sessionBucketMap[$action->session_id] ?? null;

            if ($bucketKey === null) {
                continue;
            }

            $sessionId = $action->session_id;

            if (! isset($paymentSuccessSeen[$sessionId])) {
                $paymentSuccessSeen[$sessionId] = true;
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'payment_success');
            }

            $this->incrementTrafficSourceBucket(
                $buckets,
                $bucketKey,
                field: 'sold_qty',
                amount: $this->paymentActionItemQty($action),
            );

            $this->incrementTrafficSourceBucket(
                $buckets,
                $bucketKey,
                field: 'revenue',
                amount: $this->paymentAmountPaid($action->payment_success ?? []),
            );
        }

        return $this->finalizeTrafficSourceRows($buckets, $limit);
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return Collection<string, list<string>>
     */
    private function trafficActionUrlsBySession(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?Collection $allowedSessionIds = null,
    ): Collection {
        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return ActivityEcomUserAction::query()
            ->select('session_id', 'page_url')
            ->whereIn('session_id', $this->sessionIdsCreatedBetweenSubquery($from, $to, $allowedSessionIds))
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('session_id')
            ->map(fn (Collection $rows) => $rows
                ->pluck('page_url')
                ->map(fn ($url) => (string) $url)
                ->values()
                ->all());
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return Collection<string, string>
     */
    private function trafficFirstReferersBySession(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?Collection $allowedSessionIds = null,
    ): Collection {
        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return ActivityEcomUserAction::query()
            ->select('session_id', 'referer')
            ->whereIn('session_id', $this->sessionIdsCreatedBetweenSubquery($from, $to, $allowedSessionIds))
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->unique('session_id')
            ->mapWithKeys(fn (ActivityEcomUserAction $row) => [$row->session_id => (string) $row->referer]);
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $buckets
     */
    private function incrementTrafficSourceBucket(
        array &$buckets,
        string $key,
        ?string $source = null,
        ?string $medium = null,
        string $field = 'sessions',
        int|float $amount = 1,
    ): void {
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'source' => (string) $source,
                'medium' => (string) $medium,
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

        if ($field === 'revenue') {
            $buckets[$key][$field] = round((float) $buckets[$key][$field] + (float) $amount, 2);

            return;
        }

        $buckets[$key][$field] = (int) $buckets[$key][$field] + (int) $amount;
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $buckets
     * @return array<int, array<string, mixed>>
     */
    private function finalizeTrafficSourceRows(array $buckets, ?int $limit = null): array
    {
        $rows = $this->sortAcquisitionRows(collect($buckets)->values());

        if ($limit !== null && $rows->count() > $limit) {
            $top = $rows->take($limit);
            $rest = $rows->slice($limit);

            $top->push([
                'source' => 'Other',
                'medium' => '—',
                'sessions' => (int) $rest->sum('sessions'),
                'views' => (int) $rest->sum('views'),
                'add_to_cart' => (int) $rest->sum('add_to_cart'),
                'begin_checkout' => (int) $rest->sum('begin_checkout'),
                'proceed_checkout' => (int) $rest->sum('proceed_checkout'),
                'payment_success' => (int) $rest->sum('payment_success'),
                'sold_qty' => (int) $rest->sum('sold_qty'),
                'revenue' => round((float) $rest->sum('revenue'), 2),
            ]);

            $rows = $top;
        }

        return $rows
            ->map(function (array $row) {
                $sessions = (int) $row['sessions'];
                $paymentSuccess = (int) $row['payment_success'];

                return [
                    'source' => (string) $row['source'],
                    'medium' => (string) $row['medium'],
                    'sessions' => $sessions,
                    'views' => (int) $row['views'],
                    'add_to_cart' => (int) $row['add_to_cart'],
                    'begin_checkout' => (int) $row['begin_checkout'],
                    'proceed_checkout' => (int) $row['proceed_checkout'],
                    'payment_success' => $paymentSuccess,
                    'sold_qty' => (int) $row['sold_qty'],
                    'conversion_rate' => $sessions > 0
                        ? round(($paymentSuccess / $sessions) * 100, 1)
                        : 0.0,
                    'revenue' => round((float) $row['revenue'], 2),
                ];
            })
            ->values()
            ->all();
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
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->groupBy('city', 'country')
            ->orderByDesc('sessions');

        if ($sessionIds !== null) {
            $this->constrainToSessionIds($locationsQuery, $sessionIds);
        }

        if ($limit !== null) {
            $locationsQuery->limit($limit);
        }

        $locations = $locationsQuery->get();

        if ($locations->isEmpty()) {
            return [];
        }

        $payments = $this->qualifyingPaymentActions($from, $to, $sessionIds);
        $revenueBySession = [];

        foreach ($payments as $action) {
            $revenueBySession[$action->session_id] = ($revenueBySession[$action->session_id] ?? 0)
                + $this->paymentAmountPaid($action->payment_success ?? []);
        }

        $sessionsQuery = ActivityEcomUser::query()
            ->select('session_id', 'city', 'country')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to));

        if ($sessionIds !== null) {
            $this->constrainToSessionIds($sessionsQuery, $sessionIds);
        }

        $revenueByLocation = [];

        foreach ($sessionsQuery->get() as $session) {
            $city = $session->city !== null && $session->city !== '' ? $session->city : 'Unknown';
            $country = $session->country !== null && $session->country !== '' ? $session->country : 'Unknown';
            $key = $city."\0".$country;
            $revenueByLocation[$key] = ($revenueByLocation[$key] ?? 0) + ($revenueBySession[$session->session_id] ?? 0);
        }

        return $locations->map(function ($row) use ($revenueByLocation) {
            $key = $row->city."\0".$row->country;

            return [
                'location' => $row->city.', '.$row->country,
                'sessions' => (int) $row->sessions,
                'revenue' => round((float) ($revenueByLocation[$key] ?? 0), 2),
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
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->distinct();

        if ($sessionIds !== null) {
            $buyerQuery->whereIn('session_id', $sessionIds);
        }

        $buyerSessions = $buyerQuery->pluck('session_id');

        $labels = ['Category page', 'Product page'];
        $buyers = [
            $this->averageDwell($from, $to, $buyerSessions, ['category_view']),
            $this->averageDwell($from, $to, $buyerSessions, self::PRODUCT_VIEW_TYPES),
        ];
        $nonBuyers = [
            $this->averageDwell($from, $to, null, ['category_view'], $buyerSessions),
            $this->averageDwell($from, $to, null, self::PRODUCT_VIEW_TYPES, $buyerSessions),
        ];

        $maxSeconds = max(1, ...$buyers, ...$nonBuyers);

        $rows = collect($labels)->map(function (string $label, int $index) use ($buyers, $nonBuyers, $maxSeconds) {
            $buyerSeconds = (int) ($buyers[$index] ?? 0);
            $nonBuyerSeconds = (int) ($nonBuyers[$index] ?? 0);
            $deltaSeconds = $buyerSeconds - $nonBuyerSeconds;

            return [
                'page_type' => $label,
                'buyers_seconds' => $buyerSeconds,
                'non_buyers_seconds' => $nonBuyerSeconds,
                'buyers_formatted' => format_duration($buyerSeconds),
                'non_buyers_formatted' => format_duration($nonBuyerSeconds),
                'delta_seconds' => $deltaSeconds,
                'delta_formatted' => $this->formatDwellDelta($deltaSeconds),
                'buyers_bar_percent' => round(($buyerSeconds / $maxSeconds) * 100, 1),
                'non_buyers_bar_percent' => round(($nonBuyerSeconds / $maxSeconds) * 100, 1),
            ];
        })->values()->all();

        return [
            'labels' => $labels,
            'buyers' => $buyers,
            'non_buyers' => $nonBuyers,
            'rows' => $rows,
        ];
    }

    private function formatDwellDelta(int $deltaSeconds): string
    {
        if ($deltaSeconds === 0) {
            return '—';
        }

        $prefix = $deltaSeconds > 0 ? '+' : '−';

        return $prefix.format_duration(abs($deltaSeconds));
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
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', $actionTypes)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time');

        if ($includeSessions) {
            if ($includeSessions->isEmpty()) {
                return 0;
            }

            $this->constrainToSessionIds($query, $includeSessions);
        }

        if ($excludeSessions && $excludeSessions->isNotEmpty()) {
            $query->whereNotIn('session_id', $excludeSessions->values()->all());
        }

        $driver = $query->getConnection()->getDriverName();
        $avgExpression = $driver === 'sqlite'
            ? 'AVG((julianday(end_time) - julianday(start_time)) * 86400.0)'
            : 'AVG(TIMESTAMPDIFF(SECOND, start_time, end_time))';

        $avg = $query->clone()->value(DB::raw($avgExpression));

        if ($avg === null) {
            return 0;
        }

        return (int) round((float) $avg);
    }

    private function sumRevenue(Carbon $from, Carbon $to, ?Collection $sessionIds): float
    {
        return $this->sumRevenueForSessions($from, $to, $sessionIds);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentSaleAmount(array $payload): float
    {
        $checkoutInfo = $payload['checkout_info'] ?? null;

        if (is_array($checkoutInfo)) {
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
        }

        return (float) ($payload['amount_paid'] ?? 0);
    }

    /**
     * When dashboard session filters are active, sale metrics stay limited to those sessions.
     * Otherwise all payment_success events in the date range are counted (matches product tables).
     */
    private function saleMetricSessionScope(array $extraFilters, Collection $sessions): ?Collection
    {
        return $sessions->keys();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ActivityEcomUserAction>
     */
    private function qualifyingPaymentActions(Carbon $from, Carbon $to, ?Collection $sessionIds): Collection
    {
        $query = ActivityEcomUserAction::query()
            ->select('session_id', 'payment_success')
            ->where('action_type', 'payment_success')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to));

        if ($sessionIds !== null) {
            if ($sessionIds->isEmpty()) {
                return collect();
            }

            $this->constrainToSessionIds($query, $sessionIds);
        }

        return $this->rememberQuery(
            $this->sessionSetCacheKey('qualifyingPaymentActions', $from, $to, $sessionIds ?? collect(['*'])),
            fn () => $query->get(),
        );
    }

    private function sumRevenueForSessions(Carbon $from, Carbon $to, ?Collection $sessionIds): float
    {
        return $this->qualifyingPaymentActions($from, $to, $sessionIds)
            ->sum(fn (ActivityEcomUserAction $action) => $this->paymentAmountPaid($action->payment_success ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentAmountPaid(array $payload): float
    {
        return round((float) ($payload['amount_paid'] ?? 0), 2);
    }

    private function countPurchases(Carbon $from, Carbon $to, ?Collection $sessionIds): int
    {
        $query = ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to));

        if ($sessionIds !== null) {
            if ($sessionIds->isEmpty()) {
                return 0;
            }

            $query->whereIn('session_id', $sessionIds);
        }

        return (int) $query->distinct('session_id')->count('session_id');
    }

    private function sumSaleItemQty(Carbon $from, Carbon $to, ?Collection $sessionIds): int
    {
        return (int) $this->qualifyingPaymentActions($from, $to, $sessionIds)
            ->sum(fn (ActivityEcomUserAction $action) => $this->paymentActionItemQty($action));
    }

    private function paymentActionItemQty(ActivityEcomUserAction $action): int
    {
        $checkoutInfo = $action->payment_success['checkout_info'] ?? [];
        $items = is_array($checkoutInfo) ? ($checkoutInfo['items'] ?? []) : [];

        if (! is_array($items) || $items === []) {
            return 1;
        }

        return (int) collect($items)
            ->filter(fn ($item) => is_array($item))
            ->sum(fn (array $item) => $this->resolvePurchaseLineQty($item));
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
}
