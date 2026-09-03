<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Support\CommerceFunnelQuery;
use App\Support\CommerceHasOrderFilter;
use App\Support\CommerceLineItemQuery;
use App\Support\CommerceReadSupport;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivityKeywordSearch;
use App\Support\EcomTrackerViewData;
use App\Support\SessionDurationBuckets;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerCategoryIdentity;
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
        $isUnfiltered = $extraFilters === [];

        if ($isUnfiltered) {
            $currentSessions = collect();
            $scopedSessionIds = null;
            $currentKpis = $this->buildKpisFromSessionAggregates($range['from'], $range['to'], $period);
        } else {
            $currentSessions = $this->sessionsInRange($range['from'], $range['to'], $period);
            $currentIds = $this->filteredSessionIds($range['from'], $range['to'], $extraFilters, $period);
            $currentSessions = $currentSessions->only($currentIds->all());
            $scopedSessionIds = $currentSessions->keys()->values();
            $currentKpis = $this->buildKpis($range['from'], $range['to'], $currentSessions, false, $period);
        }
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
                'category_views' => (int) collect($categories)->sum('category_views'),
                'product_views' => (int) collect($categories)->sum('product_views'),
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
            'devices' => $this->buildDeviceBreakdown($range['from'], $range['to'], $extraFilters, $period, $scopedSessionIds),
            'traffic_sources' => $this->buildTrafficSources($range['from'], $range['to'], filters: $extraFilters, period: $period, scopedSessionIds: $scopedSessionIds),
            'geography' => $this->buildGeography($range['from'], $range['to'], filters: $extraFilters, scopedSessionIds: $scopedSessionIds, period: $period),
            'engagement' => $this->buildEngagement($range['from'], $range['to'], $extraFilters, $period),
            'has_session_filters' => $extraFilters !== [],
            'visitor_quality' => app(BotTrafficAnalyticsService::class)->summaryOnly($filters),
            'duration_distribution' => $isUnfiltered
                ? $this->buildDurationDistributionFromQuery($range['from'], $range['to'], $period)
                : $this->buildDurationDistribution($currentSessions),
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
    public function activitySessionIds(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): ?Collection
    {
        $keyword = trim((string) ($filters['keyword_search'] ?? ''));
        unset($filters['keyword_search']);

        if ($keyword !== '') {
            return $this->keywordActivitySessionIds(
                $from,
                $to,
                $keyword,
                $this->extractSessionFilters($filters),
                $period,
            );
        }

        if ($filters === []) {
            return null;
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
            '0' => $this->queryProductCatalogActivitySessionIds($from, $to, $catalogOptions, $period)
                ->diff($this->queryProductCatalogPurchaseSessionIds($from, $to, $catalogOptions))
                ->values(),
            default => $this->queryProductCatalogActivitySessionIds($from, $to, $catalogOptions, $period),
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
    private function queryProductCatalogActivitySessionIds(
        Carbon $from,
        Carbon $to,
        array $catalogOptions,
        ?string $period = null,
    ): Collection {
        return CommerceLineItemQuery::sessionIds($from, $to, $catalogOptions, [], $period);
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     * @return Collection<int, string>
     */
    private function queryProductCatalogPurchaseSessionIds(Carbon $from, Carbon $to, array $catalogOptions): Collection
    {
        return CommerceLineItemQuery::sessionIds(
            $from,
            $to,
            $catalogOptions,
            ['payment_success'],
        );
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

        $lines = $this->commerceLineItemsForSessions(
            $sessionIds,
            $from,
            $to,
            ['add_to_cart', 'proceed_checkout', 'payment_success'],
        );

        foreach ($lines as $row) {
            $sessionId = (string) $row->session_id;

            if (! isset($metrics[$sessionId])) {
                continue;
            }

            $line = $this->catalogLineFromCommerceRow($row);

            if (! $this->productCatalogLineMatchesOptions($line, $options)) {
                continue;
            }

            if ($row->funnel_stage === 'add_to_cart') {
                $metrics[$sessionId]['adds']++;
            } elseif ($row->funnel_stage === 'payment_success') {
                $metrics[$sessionId]['purchases']++;
                $metrics[$sessionId]['purchased'] = 'Yes';
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
                    : ($departmentFilter !== '' ? $departmentFilter : '—'),
                'purchases' => 0,
            ];
        }

        if ($sessionIds->isEmpty() || ($categoryFilter === '' && $departmentFilter === '')) {
            return $metrics;
        }

        if ($categoryFilter === '') {
            return $metrics;
        }

        $seenEvents = [];
        $lines = $this->commerceLineItemsForSessions($sessionIds, $from, $to, ['payment_success']);

        foreach ($lines as $row) {
            $sessionId = (string) $row->session_id;

            if (! isset($metrics[$sessionId])) {
                continue;
            }

            if (! $this->productCatalogLineMatchesOptions($this->catalogLineFromCommerceRow($row), $options)) {
                continue;
            }

            $eventId = (string) ($row->event_id ?? '');
            $eventKey = $sessionId.'|'.$eventId;

            if ($eventId !== '' && isset($seenEvents[$eventKey])) {
                continue;
            }

            $seenEvents[$eventKey] = true;
            $metrics[$sessionId]['purchases']++;
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
     * True only when the payment includes line items that match the catalog filter.
     *
     * @param  array<string, mixed>  $options
     */
    public function catalogPaymentHasMatchingLines(ActivityEcomUserAction $action, array $options): bool
    {
        return $this->sumCatalogPaymentLines($action, $options)['revenue'] > 0;
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
        $seenEvents = [];
        $lines = $this->commerceLineItemsForSessions($sessionIds, $from, $to, ['payment_success']);

        foreach ($lines as $row) {
            $line = $this->catalogLineFromCommerceRow($row);

            if (! $this->productCatalogLineMatchesOptions($line, $options)) {
                continue;
            }

            $purchaseLine = $this->extractPurchaseLineIdentity($this->catalogLineToPurchaseItem($line));

            if ($purchaseLine === null) {
                continue;
            }

            $revenue += $purchaseLine['revenue'];
            $qty += $purchaseLine['qty'];

            $eventId = (string) ($row->event_id ?? '');

            if ($eventId !== '' && ! isset($seenEvents[$eventId])) {
                $seenEvents[$eventId] = true;
                $purchases++;
            }
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
    public function sumCatalogPaymentLines(ActivityEcomUserAction $action, array $options, ?Collection $linesByEvent = null): array
    {
        if ($action->action_type !== 'payment_success') {
            return ['revenue' => 0.0, 'qty' => 0];
        }

        $lines = CommerceReadSupport::catalogLinesForAction($action, $linesByEvent);
        $revenue = 0.0;
        $qty = 0;

        foreach ($lines as $line) {
            if (! $this->productCatalogLineMatchesOptions($line, $options)) {
                continue;
            }

            $purchaseLine = $this->extractPurchaseLineIdentity($this->catalogLineToPurchaseItem($line));

            if ($purchaseLine !== null) {
                $revenue += $purchaseLine['revenue'];
                $qty += $purchaseLine['qty'];
            }
        }

        if ($revenue <= 0 && $this->paymentSuccessMatchesCategoryCatalog($action, $options, $linesByEvent)) {
            $revenue = (float) (CommerceReadSupport::amountForAction($action) ?? 0);
            $qty = CommerceReadSupport::itemQtyForAction($action);
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
    private function paymentSuccessMatchesCategoryCatalog(object $action, array $options, ?Collection $linesByEvent = null): bool
    {
        foreach ($this->productCatalogLinesFromAction($action, $linesByEvent) as $line) {
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

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '' && EcomActivityFocus::looksLikeProductCodeSearch($search)) {
            $product = $products->first(function (array $row) use ($search) {
                if (strcasecmp((string) ($row['code'] ?? ''), $search) === 0) {
                    return true;
                }

                foreach ($row['variants'] ?? [] as $variant) {
                    if (strcasecmp((string) ($variant['sku'] ?? ''), $search) === 0) {
                        return true;
                    }
                }

                return false;
            });

            return $product === null
                ? null
                : $this->normalizeProductPerformanceSummaryRow($product);
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
     * Built from category/product/add-to-cart actions in the same session window
     * as the activity list (no JSON, no keyword search).
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     departments: list<string>,
     *     categories_by_department: array<string, list<string>>
     * }
     */
    public function categoryFilterOptionsForRange(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): array {
        return $this->rememberQuery(
            $this->queryCacheKey('categoryFilterOptionsForRange', $from, $to, $period, $filters),
            fn () => TrackerCategoryIdentity::filterOptionsFromCategoryPerformance(
                $this->buildCategoryPerformance($from, $to, null, $filters, $period),
            ),
        );
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
            'begin_checkouts' => (int) ($row['begin_checkout'] ?? 0),
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
            'begin_checkouts' => (int) ($row['begin_checkout'] ?? 0),
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
        $keyword = trim((string) ($filters['keyword_search'] ?? ''));
        $catalogOptions = $this->extractProductCatalogOptions($filters);

        if ($keyword !== '' || $catalogOptions !== []) {
            $sessionIds = $this->activitySessionIds($from, $to, $filters, $period);
            $sessions = $this->sessionsInRange($from, $to, $period)->only($sessionIds->all());
        } else {
            $sessions = $this->filteredSessionsForRange($from, $to, $this->extractSessionFilters($filters), $period);
        }

        $kpis = $this->buildKpis($from, $to, $sessions);

        return [
            'unique_visitors' => (int) ($kpis['unique_visitors'] ?? 0),
            'avg_stay_seconds' => (int) ($kpis['avg_stay_seconds'] ?? 0),
        ];
    }

    /**
     * Funnel totals for activity filter / keyword drill-down summaries.
     *
     * @param  array<string, mixed>  $filters
     * @return array{views: int, adds: int, begin_checkouts: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}|null
     */
    public function activityFunnelSummaryForFilters(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
    ): ?array {
        $sessionFilters = $this->extractSessionFilters($filters);
        $catalogOptions = $this->extractProductCatalogOptions($filters);
        $sessionIds = $this->activitySessionIds($from, $to, $filters, $period);

        if ($catalogOptions !== []) {
            $catalogSessionIds = $this->productCatalogSessionIds(
                $from,
                $to,
                array_merge($sessionFilters, $catalogOptions),
                $period,
            );
            $sessionIds = $sessionIds === null
                ? $catalogSessionIds
                : $sessionIds->intersect($catalogSessionIds)->values();
        }

        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return null;
        }

        $productRow = $catalogOptions !== []
            ? $this->productPerformanceSummaryForFilters(
                $from,
                $to,
                array_merge($sessionFilters, $catalogOptions),
                $period,
            )
            : null;

        if ($productRow !== null) {
            return $productRow;
        }

        if ($catalogOptions !== []) {
            return null;
        }

        $beginCheckouts = $this->countDistinctActionSessions(
            $sessionIds,
            $from,
            $to,
            'begin_checkout',
            $period,
        );

        return [
            'views' => $this->countDistinctActionSessions($sessionIds, $from, $to, self::PRODUCT_VIEW_TYPES, $period),
            'adds' => $this->countDistinctActionSessions($sessionIds, $from, $to, 'add_to_cart', $period),
            'begin_checkouts' => $beginCheckouts,
            'proceed_checkouts' => $this->countDistinctActionSessions($sessionIds, $from, $to, 'proceed_checkout', $period),
            'purchases' => $this->countDistinctActionSessions($sessionIds, $from, $to, 'payment_success', $period),
            'qty' => $this->paymentQtyForSessions($sessionIds, $from, $to, $period),
            'revenue' => $this->paymentRevenueForSessions($sessionIds, $from, $to, $period),
        ];
    }

    /**
     * @param  Collection<int, string>|null  $sessionIds
     * @param  string|array<int, string>  $actionTypes
     */
    private function countDistinctActionSessions(
        ?Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        string|array $actionTypes,
        ?string $period = null,
    ): int {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return 0;
        }

        $types = is_array($actionTypes) ? $actionTypes : [$actionTypes];
        $flags = [];
        foreach ($types as $type) {
            $flag = CommerceFunnelQuery::stageFlag((string) $type);
            if ($flag !== null) {
                $flags[] = $flag;
            }
        }

        if ($flags === []) {
            return 0;
        }

        $query = DB::table('activity_ecom_user')->where(function ($inner) use ($flags) {
            foreach ($flags as $index => $flag) {
                if ($index === 0) {
                    $inner->where($flag, true);
                } else {
                    $inner->orWhere($flag, true);
                }
            }
        });

        if ($sessionIds === null) {
            TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);

            return (int) $query->count();
        }

        $this->constrainToSessionIds($query, $sessionIds);

        return (int) $query->count();
    }

    /**
     * @param  Collection<int, string>|null  $sessionIds
     */
    private function paymentQtyForSessions(?Collection $sessionIds, Carbon $from, Carbon $to, ?string $period = null): int
    {
        return $this->paymentTotalsForSessions($sessionIds, $from, $to, $period)['qty'];
    }

    /**
     * @param  Collection<int, string>|null  $sessionIds
     */
    private function paymentRevenueForSessions(?Collection $sessionIds, Carbon $from, Carbon $to, ?string $period = null): float
    {
        return $this->paymentTotalsForSessions($sessionIds, $from, $to, $period)['revenue'];
    }

    /**
     * @param  Collection<int, string>|null  $sessionIds
     * @return array{qty: int, revenue: float}
     */
    private function paymentTotalsForSessions(?Collection $sessionIds, Carbon $from, Carbon $to, ?string $period = null): array
    {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return ['qty' => 0, 'revenue' => 0.0];
        }

        $query = DB::table('activity_ecom_orders')
            ->selectRaw('COALESCE(SUM(item_qty), 0) as qty, COALESCE(SUM(amount_paid), 0) as revenue')
            ->whereBetween('ordered_at', TrackerTime::storageRange($from, $to));

        if ($sessionIds === null) {
            $this->applyOptionalSessionScope($query, null, $from, $to, $period);
        } else {
            $this->constrainToSessionIds($query, $sessionIds);
        }

        $row = $query->first();

        return [
            'qty' => (int) ($row->qty ?? 0),
            'revenue' => round((float) ($row->revenue ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{views: int, adds: int, begin_checkouts: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}
     */
    private function normalizeProductPerformanceSummaryRow(array $product): array
    {
        return [
            'views' => (int) ($product['views'] ?? 0),
            'adds' => (int) ($product['adds'] ?? 0),
            'begin_checkouts' => (int) ($product['begin_checkouts'] ?? $product['begin_checkout'] ?? 0),
            'proceed_checkouts' => (int) ($product['proceed_checkouts'] ?? 0),
            'purchases' => (int) ($product['purchases'] ?? 0),
            'qty' => (int) ($product['qty'] ?? 0),
            'revenue' => round((float) ($product['revenue'] ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function actionMatchesProductCatalogOptions(object $action, array $options, ?Collection $linesByEvent = null): bool
    {
        foreach ($this->productCatalogLinesFromAction($action, $linesByEvent) as $line) {
            if ($this->productCatalogLineMatchesOptions($line, $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name: string, code: string, sku: string, category: string, color: string, size: string}>
     */
    private function productCatalogLinesFromAction(object $action, ?Collection $linesByEvent = null): array
    {
        return CommerceReadSupport::catalogLinesForAction($action, $linesByEvent);
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     * @param  list<string>  $funnelStages
     * @return Collection<int, object>
     */
    private function commerceLineItemsForSessions(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $funnelStages = [],
    ): Collection {
        if ($sessionIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('activity_ecom_commerce_line_items')
            ->whereBetween('staged_at', TrackerTime::storageRange($from, $to));

        if ($funnelStages !== []) {
            $query->whereIn('funnel_stage', $funnelStages);
        }

        $this->constrainToSessionIds($query, $sessionIds);

        return $query->get();
    }

    /**
     * @return array{name: string, code: string, sku: string, category: string, department_name: string, color: string, size: string, qty: float, unit_price: ?float, line_total: ?float}
     */
    private function catalogLineFromCommerceRow(object $line): array
    {
        return [
            'name' => (string) ($line->product_name ?? ''),
            'code' => (string) ($line->product_code ?? ''),
            'sku' => trim((string) ($line->sku ?? '')),
            'category' => (string) ($line->category_name ?? ''),
            'department_name' => (string) ($line->department_name ?? ''),
            'color' => (string) ($line->color_name ?? ''),
            'size' => (string) ($line->size_name ?? ''),
            'qty' => (float) ($line->qty ?? 0),
            'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
            'line_total' => $line->line_total !== null ? (float) $line->line_total : null,
        ];
    }

    /**
     * @param  array{name: string, code: string, sku: string, category: string, department_name: string, color: string, size: string, qty: float, unit_price: ?float, line_total: ?float}  $line
     * @return array<string, mixed>
     */
    private function catalogLineToPurchaseItem(array $line): array
    {
        return [
            'product_code' => $line['code'],
            'product_name' => $line['name'],
            'sku' => $line['sku'],
            'category_name' => $line['category'],
            'department_name' => $line['department_name'],
            'color_name' => $line['color'],
            'size_name' => $line['size'],
            'qty' => $line['qty'],
            'line_total' => $line['line_total'],
            'unit_price' => $line['unit_price'],
        ];
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
                if ($this->searchUsesExactProductIdentityMatch($options)) {
                    $searchUpper = strtoupper(trim((string) ($options['search'] ?? '')));
                    $matchesSearch = strcasecmp(strtoupper((string) ($line['code'] ?? '')), $searchUpper) === 0
                        || strcasecmp(strtoupper((string) ($line['sku'] ?? '')), $searchUpper) === 0;
                } else {
                    $matchesSearch = str_contains(strtolower($line['name']), $search)
                        || str_contains(strtolower($line['code']), $search)
                        || str_contains(strtolower($line['sku']), $search);
                }

                if (! $matchesSearch) {
                    return false;
                }
            }
        }

        $categoryFilter = trim((string) ($options['category'] ?? ''));

        if ($categoryFilter !== '' && ! TrackerCategoryIdentity::categoryMatchesFilter(
            (string) ($line['category'] ?? ''),
            $categoryFilter,
        )) {
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

    /**
     * @param  array<string, mixed>  $options
     */
    private function searchUsesExactProductIdentityMatch(array $options): bool
    {
        $search = trim((string) ($options['search'] ?? ''));

        if ($search === '' || filled($options['product_code'] ?? null) || filled($options['product_name'] ?? null)) {
            return false;
        }

        return EcomActivityFocus::looksLikeProductCodeSearch($search);
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
        $this->applyActivitySessionFilters($query, $filters, $from, $to);

        return $query->pluck('session_id');
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return Collection<int, string>
     */
    private function keywordActivitySessionIds(
        Carbon $from,
        Carbon $to,
        string $keyword,
        array $sessionFilters = [],
        ?string $period = null,
    ): Collection {
        return $this->rememberQuery(
            $this->queryCacheKey('keywordActivitySessionIds', $from, $to, $period, array_merge($sessionFilters, ['keyword_search' => $keyword])),
            function () use ($from, $to, $keyword, $sessionFilters, $period) {
                $query = ActivityEcomUser::query()->select('session_id');
                TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);
                $this->applyActivitySessionFilters($query, $sessionFilters, $from, $to);
                EcomActivityKeywordSearch::apply($query, $keyword, $this, $from, $to, $period);

                return $query->pluck('session_id')->values();
            },
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ActivityEcomUser>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyActivitySessionFilters($query, array $filters, ?Carbon $from = null, ?Carbon $to = null): void
    {
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
            $hasOrder = (bool) $filters['has_order'];

            if ($from instanceof Carbon && $to instanceof Carbon) {
                CommerceHasOrderFilter::apply($query, $hasOrder, $from, $to);
            } else {
                CommerceHasOrderFilter::apply($query, $hasOrder);
            }
        }
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
            'geography' => ['section' => $section, 'range' => $range, 'data' => $this->buildGeography($from, $to, $effectiveLimit, $extraFilters, period: $range['period'] ?? null)],
            'engagement' => ['section' => $section, 'range' => $range, 'data' => $this->buildEngagement($from, $to, $extraFilters, $range['period'] ?? null)],
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

    private function applyOptionalSessionScope(
        $query,
        ?Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?string $period = null,
        string $column = 'session_id',
    ): void {
        if ($sessionIds !== null) {
            $this->constrainToSessionIds($query, $sessionIds, $column);

            return;
        }

        $query->whereIn($column, function ($sub) use ($from, $to, $period) {
            $sub->from('activity_ecom_user')->select('session_id');
            TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
        });
    }

    private function periodWindowKey(?string $period): string
    {
        return $period === '24h' ? '24h' : 'calendar';
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, string>  $sessionIds
     * @return Collection<int, object>
     */
    private function restrictRowsToSessionIds(Collection $rows, Collection $sessionIds): Collection
    {
        $allowed = array_fill_keys($sessionIds->map(fn ($id) => (string) $id)->all(), true);

        return $rows
            ->filter(fn (object $row) => isset($allowed[(string) ($row->session_id ?? '')]))
            ->values();
    }

    /**
     * One session-table scan for KPI, funnel, and drop-off cards.
     *
     * @return array{
     *     sessions: int,
     *     unique_visitors: int,
     *     total_stay_seconds: int,
     *     avg_stay_seconds: int,
     *     add_to_cart: int,
     *     begin_checkout: int,
     *     proceed_checkout: int,
     *     payment_success: int,
     *     cart_abandoned: int,
     *     begin_checkout_abandoned: int,
     *     proceed_checkout_abandoned: int
     * }
     */
    private function periodSessionAggregates(Carbon $from, Carbon $to, ?string $period = null): array
    {
        return $this->rememberQuery(
            $this->queryCacheKey('periodSessionAggregates', $from, $to, $period),
            function () use ($from, $to, $period) {
                $query = DB::table('activity_ecom_user');
                TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);

                $row = $query->selectRaw(
                    'COUNT(*) as sessions,
                     COUNT(DISTINCT visitor_id) as unique_visitors,
                     COALESCE(SUM(session_duration_seconds), 0) as total_stay_seconds,
                     COALESCE(SUM(has_add_to_cart), 0) as add_to_cart,
                     COALESCE(SUM(has_begin_checkout), 0) as begin_checkout,
                     COALESCE(SUM(has_proceed_checkout), 0) as proceed_checkout,
                     COALESCE(SUM(has_payment_success), 0) as payment_success,
                     COALESCE(SUM(CASE WHEN has_add_to_cart = 1 AND has_begin_checkout = 0 AND has_proceed_checkout = 0 AND has_payment_success = 0 THEN 1 ELSE 0 END), 0) as cart_abandoned,
                     COALESCE(SUM(CASE WHEN has_begin_checkout = 1 AND has_proceed_checkout = 0 AND has_payment_success = 0 THEN 1 ELSE 0 END), 0) as begin_checkout_abandoned,
                     COALESCE(SUM(CASE WHEN has_proceed_checkout = 1 AND has_payment_success = 0 THEN 1 ELSE 0 END), 0) as proceed_checkout_abandoned',
                )->first();

                $sessions = (int) ($row->sessions ?? 0);
                $totalStay = (int) ($row->total_stay_seconds ?? 0);

                return [
                    'sessions' => $sessions,
                    'unique_visitors' => (int) ($row->unique_visitors ?? 0),
                    'total_stay_seconds' => $totalStay,
                    'avg_stay_seconds' => $sessions > 0 ? (int) round($totalStay / $sessions) : 0,
                    'add_to_cart' => (int) ($row->add_to_cart ?? 0),
                    'begin_checkout' => (int) ($row->begin_checkout ?? 0),
                    'proceed_checkout' => (int) ($row->proceed_checkout ?? 0),
                    'payment_success' => (int) ($row->payment_success ?? 0),
                    'cart_abandoned' => (int) ($row->cart_abandoned ?? 0),
                    'begin_checkout_abandoned' => (int) ($row->begin_checkout_abandoned ?? 0),
                    'proceed_checkout_abandoned' => (int) ($row->proceed_checkout_abandoned ?? 0),
                ];
            },
        );
    }

    /**
     * Session rows needed by device, traffic, geo, duration, trend, and abandonment.
     *
     * @param  Collection<int, string>|null  $sessionIds
     * @return Collection<string, object>
     */
    private function periodSessionReadRows(
        Carbon $from,
        Carbon $to,
        ?Collection $sessionIds = null,
        ?string $period = null,
    ): Collection {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return collect();
        }

        $all = $this->rememberQuery(
            $this->queryCacheKey('periodSessionReadRows', $from, $to, $this->periodWindowKey($period)),
            function () use ($from, $to, $period) {
                $query = DB::table('activity_ecom_user')->select(
                    'id',
                    'session_id',
                    'visitor_id',
                    'session_duration_seconds',
                    'created_at',
                    'device_type',
                    'browser',
                    'has_add_to_cart',
                    'has_begin_checkout',
                    'has_proceed_checkout',
                    'has_payment_success',
                    'utm_source',
                    'utm_medium',
                    'utm_campaign',
                    'landing_page',
                    'city',
                    'country',
                );
                TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);

                return $query->get()->keyBy('session_id');
            },
        );

        if ($sessionIds === null) {
            return $all;
        }

        return $all->only($sessionIds->map(fn ($id) => (string) $id)->all());
    }

    /**
     * Catalog and abandonment line items for the period (not SELECT *).
     *
     * @param  Collection<int, string>|null  $sessionIds
     * @return Collection<int, object>
     */
    private function periodLineItems(
        Carbon $from,
        Carbon $to,
        ?Collection $sessionIds = null,
        ?string $period = null,
    ): Collection {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return collect();
        }

        $all = $this->rememberQuery(
            $this->queryCacheKey('periodLineItems', $from, $to, $this->periodWindowKey($period)),
            function () use ($from, $to, $period) {
                $query = DB::table('activity_ecom_commerce_line_items as li')
                    ->select(
                        'li.id',
                        'li.session_id',
                        'li.event_id',
                        'li.funnel_stage',
                        'li.staged_at',
                        'li.qty',
                        'li.line_total',
                        'li.product_name',
                        'li.product_code',
                        'li.sku',
                        'li.color_name',
                        'li.size_name',
                        'li.department_name',
                        'li.category_name',
                        'li.category_code',
                    )
                    ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
                    ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to));
                TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 's');

                return $query->get();
            },
        );

        if ($sessionIds === null) {
            return $all;
        }

        return $this->restrictRowsToSessionIds($all, $sessionIds);
    }

    /**
     * Paid orders in the ordered_at window. Unfiltered reads are not limited to
     * sessions that also started in the window (matches sale KPI semantics).
     *
     * @param  Collection<int, string>|null  $sessionIds
     * @return Collection<int, object>
     */
    private function periodOrders(
        Carbon $from,
        Carbon $to,
        ?Collection $sessionIds = null,
        ?string $period = null,
    ): Collection {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return collect();
        }

        $all = $this->rememberQuery(
            $this->queryCacheKey('periodOrders', $from, $to, $this->periodWindowKey($period)),
            function () use ($from, $to) {
                return DB::table('activity_ecom_orders')
                    ->select(
                        'session_id',
                        'event_id',
                        'order_id',
                        'amount_paid',
                        'item_qty',
                        'ordered_at',
                        DB::raw("'payment_success' as action_type"),
                    )
                    ->whereBetween('ordered_at', TrackerTime::storageRange($from, $to))
                    ->get();
            },
        );

        if ($sessionIds === null) {
            return $all;
        }

        return $this->restrictRowsToSessionIds($all, $sessionIds);
    }

    /**
     * @return array{revenue: float, item_qty: int, purchases: int}
     */
    private function periodOrderAggregates(
        Carbon $from,
        Carbon $to,
        ?Collection $sessionIds = null,
        ?string $period = null,
    ): array {
        $orders = $this->periodOrders($from, $to, $sessionIds, $period);

        return [
            'revenue' => (float) $orders->sum(fn (object $order) => (float) ($order->amount_paid ?? 0)),
            'item_qty' => (int) $orders->sum(fn (object $order) => (int) ($order->item_qty ?? 0)),
            'purchases' => $orders->count(),
        ];
    }

    private function sessionsInRange(Carbon $from, Carbon $to, ?string $period = null): Collection
    {
        return $this->periodSessionReadRows($from, $to, null, $period);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveStatus(): array
    {
        $lastActiveAt = DB::table('activity_ecom_user')->max('last_active_at')
            ?: DB::table('activity_ecom_user')->max('updated_at');

        if (! $lastActiveAt) {
            return [
                'last_event_at' => null,
                'seconds_ago' => null,
                'label' => 'No events yet',
            ];
        }

        $seconds = TrackerTime::secondsSinceStorage($lastActiveAt);

        return [
            'last_event_at' => TrackerTime::fromStorage($lastActiveAt)?->toIso8601String(),
            'seconds_ago' => $seconds,
            'label' => TrackerTime::formatIdleSeconds($seconds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKpis(Carbon $from, Carbon $to, Collection $sessions, bool $useNormalizedOrders = false, ?string $period = null): array
    {
        $sessionIds = $sessions->keys();
        $funnel = $this->computeFunnelKpis($from, $to, $sessions, $period);

        $commerceScope = $useNormalizedOrders ? null : $sessionIds;
        $revenue = $this->sumRevenueForSessions($from, $to, $commerceScope);
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
     * @return array<string, mixed>
     */
    private function buildKpisFromSessionAggregates(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $stats = $this->sessionAggregateStats($from, $to, $period);
        $funnel = $this->computeFunnelKpisFromAggregates($from, $to, $period);
        $revenue = $this->sumRevenueForSessions($from, $to, null);
        $purchases = $funnel['payment_success_count'];
        $aov = $purchases > 0 ? $revenue / $purchases : 0;

        return [
            'unique_visitors' => $stats['unique_visitors'],
            'sessions' => $stats['sessions'],
            'total_stay_seconds' => $stats['total_stay_seconds'],
            'avg_stay_seconds' => $stats['avg_stay_seconds'],
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
            'cart_at_stake' => round($this->abandonedSessions($from, $to, 'add_to_cart', 'add_to_cart', excludeActionType: 'begin_checkout', period: $period)['total_at_stake'], 2),
            'begin_checkout_at_stake' => round($this->abandonedSessions($from, $to, 'begin_checkout', 'begin_checkout', excludeActionType: 'proceed_checkout', period: $period)['total_at_stake'], 2),
            'proceed_checkout_at_stake' => round($this->abandonedSessions($from, $to, 'proceed_checkout', 'proceed_to_checkout', excludeActionType: 'payment_success', period: $period)['total_at_stake'], 2),
        ];
    }

    /**
     * @return array{unique_visitors: int, sessions: int, total_stay_seconds: int, avg_stay_seconds: int}
     */
    private function sessionAggregateStats(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $row = $this->periodSessionAggregates($from, $to, $period);

        return [
            'unique_visitors' => $row['unique_visitors'],
            'sessions' => $row['sessions'],
            'total_stay_seconds' => $row['total_stay_seconds'],
            'avg_stay_seconds' => $row['avg_stay_seconds'],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function computeFunnelKpisFromAggregates(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $row = $this->periodSessionAggregates($from, $to, $period);
        $totalSessions = $row['sessions'];
        $convertedSessions = $row['payment_success'];
        $cartStageCount = $row['add_to_cart'];
        $beginCheckoutStageCount = $row['begin_checkout'];
        $proceedCheckoutStageCount = $row['proceed_checkout'];
        $cartAbandoned = $row['cart_abandoned'];
        $beginCheckoutAbandoned = $row['begin_checkout_abandoned'];
        $proceedCheckoutAbandoned = $row['proceed_checkout_abandoned'];

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
    }

    /**
     * @return array{
     *     buckets: array<int, array{label: string, min: int, max: int, count: int, pct: float}>,
     *     total_sessions: int,
     *     median_seconds: int,
     *     median_label: string
     * }
     */
    private function buildDurationDistributionFromQuery(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $durations = $this->periodSessionReadRows($from, $to, null, $period)
            ->map(fn (object $session) => max(0, (int) ($session->session_duration_seconds ?? 0)));
        $distribution = SessionDurationBuckets::withCounts($durations);

        return [
            'buckets' => $distribution['buckets'],
            'total_sessions' => $distribution['total_sessions'],
            'median_seconds' => $distribution['median_seconds'],
            'median_label' => $this->visitorAnalytics->formatDuration($distribution['median_seconds']),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function computeFunnelKpis(Carbon $from, Carbon $to, Collection $sessions, ?string $period = null): array
    {
        return $this->rememberQuery(
            $this->sessionSetCacheKey('computeFunnelKpis', $from, $to, $sessions->keys()),
            function () use ($from, $to, $sessions, $period) {
                $sessionIds = $sessions->keys();
                $totalSessions = $sessionIds->count();
                $typeSets = $this->sessionActionTypeSets($sessionIds, $from, $to, $period);

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
        $comparisonLabel = $prevRange['label'];

        if ($extraFilters === []) {
            $prevStats = $this->sessionAggregateStats($prevRange['from'], $prevRange['to'], $prevPeriod);

            return [
                $this->kpiCardWithComparison(
                    'Unique visitors',
                    $current['unique_visitors'],
                    $prevStats['unique_visitors'],
                    'number',
                    'Distinct visitor IDs among sessions in the selected period (same session rules as User activity).',
                    $comparisonLabel,
                ),
                $this->kpiCardWithComparison(
                    'Sessions',
                    $current['sessions'],
                    $prevStats['sessions'],
                    'number',
                    'Sessions in the selected period using the same date rules as User activity. Respects active dashboard filters.',
                    $comparisonLabel,
                ),
                $this->kpiCardWithComparison(
                    'Total stay time',
                    $current['total_stay_seconds'],
                    $prevStats['total_stay_seconds'],
                    'duration',
                    'Sum of session Duration values for sessions in the period (same as User activity Duration column).',
                    $comparisonLabel,
                ),
                $this->kpiCardWithComparison(
                    'Avg stay time',
                    $current['avg_stay_seconds'],
                    $prevStats['avg_stay_seconds'],
                    'duration',
                    'Average Duration per session in the period (total stay time divided by session count).',
                    $comparisonLabel,
                ),
            ];
        }

        $prevSessions = $this->filteredSessionsForRange($prevRange['from'], $prevRange['to'], $extraFilters, $prevPeriod);

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
        $period = $range['period'] ?? null;
        $current = $extraFilters === []
            ? $this->computeFunnelKpisFromAggregates($from, $to, $period)
            : $this->computeFunnelKpis($from, $to, $currentSessions, $period);
        $prevRange = $this->resolvePreviousPeriodRange($range);
        $prevPeriod = $prevRange['period'] ?? null;
        $previous = $extraFilters === []
            ? $this->computeFunnelKpisFromAggregates($prevRange['from'], $prevRange['to'], $prevPeriod)
            : $this->computeFunnelKpis(
                $prevRange['from'],
                $prevRange['to'],
                $this->filteredSessionsForRange($prevRange['from'], $prevRange['to'], $extraFilters, $prevPeriod),
                $prevPeriod,
            );
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

    private function effectiveSessionDurationSeconds(object $session): int
    {
        return max(0, (int) ($session->session_duration_seconds ?? 0));
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function sessionActionTypeSets(Collection $sessionIds, Carbon $from, Carbon $to, ?string $period = null): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $typeSets = [];

        foreach ($this->periodSessionReadRows($from, $to, $sessionIds, $period) as $row) {
            $types = [];
            if ($row->has_add_to_cart) {
                $types['add_to_cart'] = true;
            }
            if ($row->has_begin_checkout) {
                $types['begin_checkout'] = true;
            }
            if ($row->has_proceed_checkout) {
                $types['proceed_checkout'] = true;
            }
            if ($row->has_payment_success) {
                $types['payment_success'] = true;
            }
            $typeSets[$row->session_id] = $types;
        }

        return $typeSets;
    }

    private function totalStaySecondsFromSessions(Collection $sessions): int
    {
        return (int) $sessions->sum(fn (object $session) => $this->effectiveSessionDurationSeconds($session));
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
        $durations = $sessions->map(fn (object $session) => $this->effectiveSessionDurationSeconds($session));
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
        $prevSessions = $extraFilters === []
            ? collect()
            : $this->filteredSessionsForRange(
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
        if ($filters === []) {
            return $this->buildFunnelFromAggregates($from, $to, $period);
        }

        $sessions = $this->sessionsInRange($from, $to, $period);
        $filteredIds = $this->filteredSessionIds($from, $to, $filters, $period);
        $sessions = $sessions->only($filteredIds->all());

        $counts = [];

        foreach (self::FUNNEL_STAGES as $stage) {
            $counts[$stage['key']] = 0;
        }

        foreach ($sessions as $session) {
            if ($session->has_add_to_cart ?? false) {
                $counts['add_to_cart']++;
            }
            if ($session->has_begin_checkout ?? false) {
                $counts['begin_checkout']++;
            }
            if ($session->has_proceed_checkout ?? false) {
                $counts['proceed_checkout']++;
            }
            if ($session->has_payment_success ?? false) {
                $counts['payment_success']++;
            }
        }

        $top = max(1, max($counts) ?: 0);
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
     * @return array<int, array<string, mixed>>
     */
    private function buildFunnelFromAggregates(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $row = $this->periodSessionAggregates($from, $to, $period);

        $counts = [
            'category_view' => 0,
            'product_view' => 0,
            'add_to_cart' => $row['add_to_cart'],
            'begin_checkout' => $row['begin_checkout'],
            'proceed_checkout' => $row['proceed_checkout'],
            'payment_success' => $row['payment_success'],
        ];

        $top = max(1, max($counts) ?: 0);
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

        $sessions = $filters === [] ? null : $this->sessionsInRange($from, $to, $period);

        if ($sessions !== null && $filters !== []) {
            $filteredIds = $this->filteredSessionIds($from, $to, $filters, $period);
            $sessions = $sessions->only($filteredIds->all());
        }

        $scopedSessionIds = $sessions?->keys();
        $preloadedSessions = $filters === []
            ? $this->periodSessionReadRows($from, $to, null, $period)
            : $sessions;
        $restrictToScopedSessions = $filters !== [];
        $bucketCounts = $this->aggregateTrendBuckets(
            $periodBuckets,
            $scopedSessionIds,
            $preloadedSessions,
            $from,
            $to,
            $period,
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
        ?Collection $scopedSessionIds,
        ?Collection $preloadedSessions = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $period = null,
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

        if ($scopedSessionIds !== null && $scopedSessionIds->isEmpty()) {
            return $this->emptyTrendBucketResult($labels, $seriesData, $sessionCounts, $itemsSoldCounts);
        }

        if ($from === null || $to === null) {
            return $this->emptyTrendBucketResult($labels, $seriesData, $sessionCounts, $itemsSoldCounts);
        }

        if ($preloadedSessions !== null) {
            $sessionRows = $preloadedSessions->values();
        } else {
            $sessionRows = $this->periodSessionReadRows($from, $to, $scopedSessionIds, $period)->values();
        }

        foreach ($sessionRows as $session) {
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

        foreach ($this->periodLineItems($from, $to, $scopedSessionIds, $period) as $line) {
            $seriesKey = $typeToSeries[(string) ($line->funnel_stage ?? '')] ?? null;

            if ($seriesKey === null) {
                continue;
            }

            $index = $this->trendBucketIndex(TrackerTime::formatUtc($line->staged_at ?? $line->created_at ?? null), $ranges);

            if ($index === null) {
                continue;
            }

            $seriesData[$seriesKey][$index]++;
        }

        foreach ($this->periodOrders($from, $to, $scopedSessionIds, $period) as $order) {
            $index = $this->trendBucketIndex(TrackerTime::formatUtc($order->ordered_at ?? $order->created_at ?? null), $ranges);

            if ($index === null) {
                continue;
            }

            $seriesData['purchases'][$index]++;
            $purchaseSessionsPerBucket[$index][$order->session_id] = true;
            $itemsSoldCounts[$index] += max(0, (int) ($order->item_qty ?? 0));
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

        $lines = $this->periodLineItems($from, $to, $sessionIds, $period);

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($lines as $line) {
            $mapped = [
                'department_name' => (string) ($line->department_name ?? ''),
                'category_name' => (string) ($line->category_name ?? ''),
                'category_code' => (string) ($line->category_code ?? ''),
                'category' => (string) ($line->category_name ?? ''),
            ];
            $meta = TrackerCategoryIdentity::metaFromLine($mapped);

            if ($meta === null) {
                continue;
            }

            $key = $meta['key'];
            $rows[$key] ??= $this->emptyCategoryPerformanceRow($meta);

            $stage = (string) $line->funnel_stage;

            if ($stage === 'category_view') {
                $rows[$key]['category_views']++;
            } elseif (in_array($stage, ['product_view', 'product_view_popup'], true)) {
                $rows[$key]['product_views']++;
            } elseif ($stage === 'add_to_cart') {
                $rows[$key]['adds']++;
            } elseif ($stage === 'proceed_checkout') {
                $rows[$key]['proceed_checkouts']++;
            } elseif ($stage === 'payment_success') {
                $rows[$key]['purchases']++;
                $rows[$key]['sale_items'] += (int) round((float) ($line->qty ?? 0));
                $rows[$key]['sale_amount'] += (float) ($line->line_total ?? 0);
            }

            $rows[$key]['views'] = (int) $rows[$key]['category_views'] + (int) $rows[$key]['product_views'];
        }

        if ($rows === []) {
            return [];
        }

        return collect($rows)
            ->filter(fn (array $row) => TrackerCategoryIdentity::categoryRowHasActivity($row))
            ->map(function (array $row) {
                $row['label'] = TrackerCategoryIdentity::label(
                    (string) ($row['department_name'] ?? ''),
                    (string) ($row['category_name'] ?? ''),
                );
                $row['name'] = $row['label'];
                $views = (int) $row['views'];
                $base = $views > 0 ? $views : (int) $row['adds'];
                $row['conversion_rate'] = $base > 0
                    ? round(((int) $row['purchases'] / $base) * 100, 1)
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
            if (! TrackerCategoryIdentity::categoryRowHasActivity($row)) {
                continue;
            }

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
                'category_views' => (int) ($row['category_views'] ?? 0),
                'product_views' => (int) ($row['product_views'] ?? 0),
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
                $department['category_views'] = (int) collect($department['categories'])->sum('category_views');
                $department['product_views'] = (int) collect($department['categories'])->sum('product_views');
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
            'category_views' => 0,
            'product_views' => 0,
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
        object $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
        ?Collection $linesByEvent = null,
    ): void {
        if ($this->attributeCatalogLineQtyToCategories(
            $action,
            'adds',
            $rows,
            $categoryTimelineByScope,
            $sessionVisitors,
            $sessionActionsBySession,
            $linesByEvent,
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

        $rows[$categoryKey]['adds'] += CommerceReadSupport::itemQtyForAction($action);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function attributeCategoryProceedCheckout(
        object $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
        ?Collection $linesByEvent = null,
    ): void {
        if ($this->attributeCatalogLineQtyToCategories(
            $action,
            'proceed_checkouts',
            $rows,
            $categoryTimelineByScope,
            $sessionVisitors,
            $sessionActionsBySession,
            $linesByEvent,
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

        $rows[$categoryKey]['proceed_checkouts'] += CommerceReadSupport::itemQtyForAction($action);
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @param  Collection<string, Collection<int, object>>  $sessionActionsBySession
     */
    private function attributeCategoryPaymentSuccess(
        object $action,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
        ?Collection $linesByEvent = null,
    ): void {
        $catalogLines = CommerceReadSupport::catalogLinesForAction($action, $linesByEvent);
        $sessionActions = $sessionActionsBySession->get($action->session_id, collect());
        $matchedKeys = [];

        foreach ($catalogLines as $catalogLine) {
            $item = $this->catalogLineToPurchaseItem($catalogLine);
            $line = $this->enrichCategoryLineItem(
                $item,
                $action,
                $sessionActions,
                $categoryTimelineByScope,
                $sessionVisitors,
                $linesByEvent,
            );
            $categoryKey = $this->resolveCategoryRowForLine($line, $rows);

            if ($categoryKey === null) {
                continue;
            }

            $purchaseLine = $this->extractPurchaseLineIdentity($item);

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
        $rows[$categoryKey]['sale_amount'] += (float) (CommerceReadSupport::amountForAction($action) ?? 0);
        $rows[$categoryKey]['sale_items'] += CommerceReadSupport::itemQtyForAction($action);
    }

    /**
     * @param  array<string, list<array{at: Carbon, key: string}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     */
    private function resolveLastCategoryKeyBeforeEvent(
        object $event,
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
            'category_views' => 0,
            'product_views' => 0,
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
    private function bootstrapCategoryRowsFromAction(object $action, array &$rows, ?Collection $linesByEvent = null): void
    {
        foreach ($this->categoryLineItemsFromAction($action, $linesByEvent) as $line) {
            $this->resolveCategoryRowForLine($line, $rows);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryLineItemsFromAction(object $action, ?Collection $linesByEvent = null): array
    {
        return collect(CommerceReadSupport::catalogLinesForAction($action, $linesByEvent))
            ->map(fn (array $line) => $this->catalogLineToPurchaseItem($line))
            ->filter(fn (array $line) => ($line['product_code'] ?? '') !== ''
                || ($line['sku'] ?? '') !== ''
                || ($line['product_name'] ?? '') !== '')
            ->values()
            ->all();
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
     * @param  array<string, mixed>  $line
     * @param  Collection<int, object>  $sessionActions
     * @param  array<string, list<array{at: Carbon, key: string, meta: array<string, mixed>}>>  $categoryTimelineByScope
     * @param  array<string, string|null>  $sessionVisitors
     * @return array<string, mixed>
     */
    private function enrichCategoryLineItem(
        array $line,
        object $contextAction,
        Collection $sessionActions,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        ?Collection $linesByEvent = null,
    ): array {
        if (TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $line = $this->mergeCategoryFieldsOntoLine(
            $line,
            $this->sessionProductCategoryMeta($line, $sessionActions, $linesByEvent),
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
     * @param  Collection<int, object>  $sessionActions
     * @return array<string, mixed>
     */
    private function sessionProductCategoryMeta(array $line, Collection $sessionActions, ?Collection $linesByEvent = null): array
    {
        $productId = trim((string) ($line['product_id'] ?? ''));
        $productCode = trim((string) ($line['product_code'] ?? ''));

        foreach ($sessionActions as $action) {
            foreach ($this->categoryLineItemsFromAction($action, $linesByEvent) as $candidate) {
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
     * @param  Collection<int, object>  $sessionActions
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
        object $event,
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
     * @param  Collection<string, Collection<int, object>>  $sessionActionsBySession
     */
    private function attributeCatalogLineQtyToCategories(
        object $action,
        string $counterField,
        array &$rows,
        array $categoryTimelineByScope,
        array $sessionVisitors,
        Collection $sessionActionsBySession,
        ?Collection $linesByEvent = null,
    ): bool {
        $catalogLines = CommerceReadSupport::catalogLinesForAction($action, $linesByEvent);
        $matched = false;

        if ($catalogLines === []) {
            return false;
        }

        $sessionActions = $sessionActionsBySession->get($action->session_id, collect());

        foreach ($catalogLines as $catalogLine) {
            $item = $this->catalogLineToPurchaseItem($catalogLine);
            $line = $this->enrichCategoryLineItem(
                $item,
                $action,
                $sessionActions,
                $categoryTimelineByScope,
                $sessionVisitors,
                $linesByEvent,
            );
            $qty = (int) max(1, (float) ($catalogLine['qty'] ?? 1));
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

    private function resolveCartEventQty(object $action): int
    {
        return CommerceReadSupport::itemQtyForAction($action);
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
    private function extractPurchaseLineIdentity(array $item, string $priceMode = 'unit'): ?array
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
            'revenue' => $this->resolvePurchaseLineRevenue($item, $priceMode),
        ];
    }

    private function resolvePurchaseLineQty(array $item): int
    {
        $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);

        return (int) max(1, $qty);
    }

    private function resolvePurchaseLineRevenue(array $item, string $priceMode = 'unit'): float
    {
        foreach (['line_total', 'total', 'row_total', 'subtotal'] as $field) {
            $lineTotal = (float) ($item[$field] ?? 0);

            if ($lineTotal > 0) {
                return round($lineTotal, 2);
            }
        }

        $qty = $this->resolvePurchaseLineQty($item);
        $unitPrice = (float) ($item['unit_price'] ?? $item['discount_price'] ?? 0);

        if ($unitPrice > 0) {
            return round(max(0, $qty) * max(0, $unitPrice), 2);
        }

        $price = (float) ($item['price'] ?? 0);

        if ($price <= 0) {
            return 0.0;
        }

        if ($priceMode === 'line' || $qty <= 1) {
            return round($price, 2);
        }

        return round(max(0, $qty) * max(0, $price), 2);
    }

    /**
     * Payment checkout lines may store either unit price or line total in `price`.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function resolvePaymentLinePriceMode(array $items, float $checkoutSubtotal): string
    {
        if ($items === []) {
            return 'unit';
        }

        $unitSum = 0.0;
        $lineSum = 0.0;

        foreach ($items as $item) {
            $unitSum += $this->resolvePurchaseLineRevenueWithMode($item, 'unit');
            $lineSum += $this->resolvePurchaseLineRevenueWithMode($item, 'line');
        }

        if ($checkoutSubtotal > 0) {
            return abs($lineSum - $checkoutSubtotal) < abs($unitSum - $checkoutSubtotal) ? 'line' : 'unit';
        }

        return 'unit';
    }

    private function resolvePurchaseLineRevenueWithMode(array $item, string $priceMode): float
    {
        foreach (['line_total', 'total', 'row_total', 'subtotal'] as $field) {
            $lineTotal = (float) ($item[$field] ?? 0);

            if ($lineTotal > 0) {
                return round($lineTotal, 2);
            }
        }

        $qty = $this->resolvePurchaseLineQty($item);
        $unitPrice = (float) ($item['unit_price'] ?? $item['discount_price'] ?? 0);

        if ($unitPrice > 0) {
            return round(max(0, $qty) * max(0, $unitPrice), 2);
        }

        $price = (float) ($item['price'] ?? 0);

        if ($price <= 0) {
            return 0.0;
        }

        if ($priceMode === 'line') {
            return round($price, 2);
        }

        if ($qty <= 1) {
            return round($price, 2);
        }

        return round(max(0, $qty) * max(0, $price), 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $actions
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function uniquePaymentSuccessActions(Collection $actions): Collection
    {
        return $actions
            ->unique(function (object $action) {
                $orderId = CommerceReadSupport::orderIdForAction($action);

                return filled($orderId) ? $orderId : $action->event_id;
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

        foreach ($this->periodLineItems($from, $to, $sessionIds, $period) as $line) {
            $identity = [
                'name' => (string) ($line->product_name ?? ''),
                'code' => (string) ($line->product_code ?? ''),
                'product_id' => '',
            ];
            $variant = [
                'color' => (string) ($line->color_name ?? ''),
                'size' => (string) ($line->size_name ?? ''),
                'sku' => trim((string) ($line->sku ?? '')),
                'category' => (string) ($line->category_name ?? ''),
            ];

            if ($identity['code'] === '' && $variant['sku'] === '' && $identity['name'] === '') {
                continue;
            }

            match ((string) $line->funnel_stage) {
                'add_to_cart' => $this->accumulateCatalogEvent($catalog, $identity, $variant, adds: 1),
                'begin_checkout' => $this->accumulateCatalogEvent($catalog, $identity, $variant, begin_checkouts: 1),
                'proceed_checkout' => $this->accumulateCatalogEvent($catalog, $identity, $variant, proceed_checkouts: 1),
                'payment_success' => $this->accumulateCatalogEvent(
                    $catalog,
                    $identity,
                    $variant,
                    purchases: 1,
                    qty: (int) round((float) ($line->qty ?? 0)),
                    revenue: (float) ($line->line_total ?? 0),
                ),
                default => null,
            };
        }

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
        int $begin_checkouts = 0,
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
            'begin_checkouts' => 0,
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
        $variantRow['begin_checkouts'] += $begin_checkouts;
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
                'begin_checkouts' => (int) $variants->sum('begin_checkouts'),
                'begin_checkout' => (int) $variants->sum('begin_checkouts'),
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

        $paymentRows = CommerceFunnelQuery::paymentRowsFromLoadedData(
            $this->periodOrders($from, $to, $allowedSessionIds, $period),
            $this->periodSessionReadRows($from, $to, $allowedSessionIds, $period),
        );

        $rows = collect($paymentRows)
            ->map(fn (array $row) => $this->formatRecoverableSessionRow(
                $row['session_id'],
                (float) $row['value'],
                $row['occurred_at'],
                (int) $row['qty'],
            ))
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

        $abandonedRows = CommerceFunnelQuery::abandonedRowsFromLoadedData(
            $this->periodSessionReadRows($from, $to, $allowedSessionIds, $period),
            $this->periodLineItems($from, $to, $allowedSessionIds, $period),
            $stage,
            $excludeActionType,
        );

        $rows = collect($abandonedRows)->map(fn (array $row) => $this->formatRecoverableSessionRow(
            $row['session_id'],
            (float) $row['value'],
            $row['occurred_at'],
            (int) $row['qty'],
        ));

        return $this->finalizeRecoverableSessionRows(
            $rows->sortByDesc(fn (array $row) => $row['_sort_at']?->timestamp ?? 0)->values(),
            null,
        );
    }

    /**
     * @return array{by_device: array<int, array<string, mixed>>, by_browser: array<int, array<string, mixed>>}
     */
    private function buildDeviceBreakdown(
        Carbon $from,
        Carbon $to,
        array $filters = [],
        ?string $period = null,
        ?Collection $scopedSessionIds = null,
    ): array {
        $sessionIds = $scopedSessionIds ?? ($filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null);

        $sessions = $this->periodSessionReadRows($from, $to, $sessionIds, $period);

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

            if ($session->has_add_to_cart) {
                $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'add_to_cart');
                $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'add_to_cart');
            }
            if ($session->has_begin_checkout) {
                $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'begin_checkout');
                $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'begin_checkout');
            }
            if ($session->has_proceed_checkout) {
                $this->incrementDeviceBrowserBucket($deviceBuckets, $deviceLabel, 'proceed_checkout');
                $this->incrementDeviceBrowserBucket($browserBuckets, $browserLabel, 'proceed_checkout');
            }
        }

        $ordersQuery = $this->periodOrders($from, $to, $sessionIds, $period);

        $devicePurchaseSeen = [];
        $browserPurchaseSeen = [];

        foreach ($ordersQuery as $order) {
            $sessionId = $order->session_id;
            $deviceLabel = $sessionDeviceMap[$sessionId] ?? null;
            $browserLabel = $sessionBrowserMap[$sessionId] ?? null;
            $soldQty = max(0, (int) ($order->item_qty ?? 0));
            $revenue = (float) ($order->amount_paid ?? 0);

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
    private function buildTrafficSources(
        Carbon $from,
        Carbon $to,
        ?int $limit = self::TABLE_DISPLAY_LIMIT,
        array $filters = [],
        ?string $period = null,
        ?Collection $scopedSessionIds = null,
    ): array {
        $sessionIds = $scopedSessionIds ?? ($filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null);

        $sessions = $this->periodSessionReadRows($from, $to, $sessionIds, $period);

        if ($sessions->isEmpty()) {
            return [];
        }

        $buckets = [];
        $sessionBucketMap = [];

        foreach ($sessions as $session) {
            $bucket = SessionTrafficAttribution::resolvedTrafficBucket(
                $session,
                [''],
                '',
            );
            $source = $bucket['source'];
            $medium = $bucket['medium'];
            $key = $source."\0".$medium;

            $sessionBucketMap[$session->session_id] = $key;
            $this->incrementTrafficSourceBucket($buckets, $key, $source, $medium, 'sessions');

            if ($session->has_add_to_cart) {
                $this->incrementTrafficSourceBucket($buckets, $key, field: 'add_to_cart');
            }
            if ($session->has_begin_checkout) {
                $this->incrementTrafficSourceBucket($buckets, $key, field: 'begin_checkout');
            }
            if ($session->has_proceed_checkout) {
                $this->incrementTrafficSourceBucket($buckets, $key, field: 'proceed_checkout');
            }
        }

        $paymentSuccessSeen = [];

        foreach ($this->periodOrders($from, $to, $sessionIds, $period) as $order) {
            $bucketKey = $sessionBucketMap[$order->session_id] ?? null;

            if ($bucketKey === null) {
                continue;
            }

            if (! isset($paymentSuccessSeen[$order->session_id])) {
                $paymentSuccessSeen[$order->session_id] = true;
                $this->incrementTrafficSourceBucket($buckets, $bucketKey, field: 'payment_success');
            }

            $this->incrementTrafficSourceBucket(
                $buckets,
                $bucketKey,
                field: 'sold_qty',
                amount: max(0, (int) ($order->item_qty ?? 0)),
            );
            $this->incrementTrafficSourceBucket(
                $buckets,
                $bucketKey,
                field: 'revenue',
                amount: (float) ($order->amount_paid ?? 0),
            );
        }

        return $this->finalizeTrafficSourceRows($buckets, $limit);
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
    private function buildGeography(
        Carbon $from,
        Carbon $to,
        ?int $limit = self::TABLE_DISPLAY_LIMIT,
        array $filters = [],
        ?Collection $scopedSessionIds = null,
        ?string $period = null,
    ): array {
        $sessionIds = $scopedSessionIds ?? ($filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null);
        $sessions = $this->periodSessionReadRows($from, $to, $sessionIds, $period);

        if ($sessions->isEmpty()) {
            return [];
        }

        $buckets = [];

        foreach ($sessions as $session) {
            $city = filled($session->city ?? null) ? (string) $session->city : 'Unknown';
            $country = filled($session->country ?? null) ? (string) $session->country : 'Unknown';
            $key = $city."\0".$country;
            $buckets[$key] ??= [
                'city' => $city,
                'country' => $country,
                'sessions' => 0,
                'revenue' => 0.0,
            ];
            $buckets[$key]['sessions']++;
        }

        foreach ($this->periodOrders($from, $to, $sessionIds, $period) as $order) {
            $session = $sessions->get($order->session_id);
            if ($session === null) {
                continue;
            }

            $city = filled($session->city ?? null) ? (string) $session->city : 'Unknown';
            $country = filled($session->country ?? null) ? (string) $session->country : 'Unknown';
            $key = $city."\0".$country;
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['revenue'] += (float) ($order->amount_paid ?? 0);
        }

        $rows = collect($buckets)
            ->sortByDesc('sessions')
            ->values();

        if ($limit !== null) {
            $rows = $rows->take($limit);
        }

        return $rows
            ->map(fn (array $row) => [
                'location' => $row['city'].', '.$row['country'],
                'sessions' => (int) $row['sessions'],
                'revenue' => round((float) $row['revenue'], 2),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEngagement(Carbon $from, Carbon $to, array $filters = [], ?string $period = null): array
    {
        $sessionIds = $filters !== [] ? $this->filteredSessionIds($from, $to, $filters, $period) : null;
        $sessions = $this->periodSessionReadRows($from, $to, $sessionIds, $period);
        $buyerSet = [];

        foreach ($this->periodOrders($from, $to, $sessionIds, $period) as $order) {
            $buyerSet[(string) $order->session_id] = true;
        }

        $buyerTotal = 0;
        $buyerCount = 0;
        $nonBuyerTotal = 0;
        $nonBuyerCount = 0;

        foreach ($sessions as $session) {
            if ($session->session_duration_seconds === null) {
                continue;
            }

            $seconds = (int) $session->session_duration_seconds;
            if (isset($buyerSet[(string) $session->session_id])) {
                $buyerTotal += $seconds;
                $buyerCount++;
            } else {
                $nonBuyerTotal += $seconds;
                $nonBuyerCount++;
            }
        }

        $avgSeconds = $buyerCount > 0 ? (int) round($buyerTotal / $buyerCount) : 0;
        $nonBuyerSeconds = $nonBuyerCount > 0 ? (int) round($nonBuyerTotal / $nonBuyerCount) : 0;
        $labels = ['Category page', 'Product page'];
        $buyers = [$avgSeconds, $avgSeconds];
        $nonBuyers = [$nonBuyerSeconds, $nonBuyerSeconds];

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
     * @param  Collection<int, string>|null  $scopedSessionIds
     * @param  Collection<int, string>|null  $includeSessions
     * @param  Collection<int, string>|null  $excludeSessions
     */
    private function averageSessionDuration(
        Carbon $from,
        Carbon $to,
        ?Collection $scopedSessionIds,
        ?Collection $includeSessions,
        ?Collection $excludeSessions = null,
    ): int {
        $query = DB::table('activity_ecom_user')->whereNotNull('session_duration_seconds');
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, null);

        if ($scopedSessionIds !== null) {
            if ($scopedSessionIds->isEmpty()) {
                return 0;
            }
            $this->constrainToSessionIds($query, $scopedSessionIds);
        }

        if ($includeSessions !== null) {
            if ($includeSessions->isEmpty()) {
                return 0;
            }
            $this->constrainToSessionIds($query, $includeSessions);
        }

        if ($excludeSessions && $excludeSessions->isNotEmpty()) {
            $query->whereNotIn('session_id', $excludeSessions->values()->all());
        }

        $avg = $query->avg('session_duration_seconds');

        return $avg === null ? 0 : (int) round((float) $avg);
    }

    private function sumRevenue(Carbon $from, Carbon $to, ?Collection $sessionIds): float
    {
        return $this->sumRevenueForSessions($from, $to, $sessionIds);
    }

    private function paymentSaleAmount(object $action): float
    {
        return (float) (CommerceReadSupport::amountForAction($action) ?? 0);
    }

    /**
     * When dashboard session filters are active, sale metrics stay limited to those sessions.
     * Otherwise all payment_success events in the date range are counted (matches product tables).
     */
    private function saleMetricSessionScope(array $extraFilters, Collection $sessions): ?Collection
    {
        return $extraFilters === [] ? null : $sessions->keys();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function qualifyingPaymentActions(Carbon $from, Carbon $to, ?Collection $sessionIds): Collection
    {
        return $this->periodOrders($from, $to, $sessionIds);
    }

    private function sumRevenueForSessions(Carbon $from, Carbon $to, ?Collection $sessionIds): float
    {
        return $this->periodOrderAggregates($from, $to, $sessionIds)['revenue'];
    }

    private function countPurchases(Carbon $from, Carbon $to, ?Collection $sessionIds): int
    {
        if ($sessionIds === null) {
            return $this->periodOrderAggregates($from, $to)['purchases'];
        }

        if ($sessionIds->isEmpty()) {
            return 0;
        }

        return $this->periodOrders($from, $to, $sessionIds)
            ->pluck('session_id')
            ->unique()
            ->count();
    }

    private function sumSaleItemQty(Carbon $from, Carbon $to, ?Collection $sessionIds): int
    {
        return $this->periodOrderAggregates($from, $to, $sessionIds)['item_qty'];
    }

    private function paymentActionItemQty(object $action): int
    {
        return CommerceReadSupport::itemQtyForAction($action);
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
