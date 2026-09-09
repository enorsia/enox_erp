<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use App\Services\EcomActivityFunnelSessions;
use App\Services\EcomTrackerDashboardService;
use App\Services\VisitorAnalyticsService;
use App\Support\CommerceFunnelQuery;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EcomActivityFocus
{
  /** @var list<string> */
    public const SHARED_SESSION_FILTER_KEYS = [
        'device_type',
        'logged_in',
        'has_order',
        'utm_source',
        'utm_medium',
    ];

    /** @var list<string> */
    public const DASHBOARD_AUDIENCE_FILTER_KEYS = [
        'visitor_type',
    ];

    /** @var list<string> */
    public const SIDEBAR_FUNNEL_FILTER_KEYS = [
        'cart_abandonment',
        'begin_checkout_abandonment',
        'proceed_checkout_abandonment',
        'payment_success',
    ];

    /** @var array<string, string> */
    private const SECTION_MAP = [
        'cart-abandonment' => 'cart_abandonment',
        'begin-checkout-abandonment' => 'begin_checkout_abandonment',
        'checkout-abandonment' => 'begin_checkout_abandonment',
        'proceed-checkout-abandonment' => 'proceed_checkout_abandonment',
        'payment-success-events' => 'payment_success',
        'products' => 'products',
        'colors' => 'products',
        'categories' => 'categories',
        'devices' => 'devices',
        'traffic-sources' => 'traffic',
        'geography' => 'traffic',
        'engagement' => 'audience',
        'funnel' => 'audience',
    ];

    /** @var array<string, array<string, mixed>> */
    private const DEFINITIONS = [
        'audience' => [
            'label' => 'Audience & engagement',
            'empty' => 'No sessions in this period.',
            'columns' => ['device'],
            'sort' => 'latest',
        ],
        'conversion' => [
            'label' => 'Sale & conversion',
            'empty' => 'No converting sessions in this period.',
            'columns' => [],
            'sort' => 'funnel_stage',
            'implicit' => ['has_order' => '1'],
            'payment_success' => true,
        ],
        'cart_abandonment' => [
            'label' => 'Cart abandoned',
            'empty' => 'No abandoned carts in this period — nice.',
            'columns' => [],
            'sort' => 'funnel_stage',
            'funnel' => ['stage' => 'add_to_cart', 'payload' => 'add_to_cart', 'exclude' => 'begin_checkout'],
        ],
        'begin_checkout_abandonment' => [
            'label' => 'Begin checkout abandoned',
            'empty' => 'No begin checkout abandonment in this period.',
            'columns' => [],
            'sort' => 'funnel_stage',
            'funnel' => ['stage' => 'begin_checkout', 'payload' => 'begin_checkout', 'exclude' => 'proceed_checkout'],
        ],
        'proceed_checkout_abandonment' => [
            'label' => 'Proceed checkout abandoned',
            'empty' => 'No proceed checkout abandonment in this period.',
            'columns' => [],
            'sort' => 'funnel_stage',
            'funnel' => ['stage' => 'proceed_checkout', 'payload' => 'proceed_to_checkout', 'exclude' => 'payment_success'],
        ],
        'payment_success' => [
            'label' => 'Payment success',
            'empty' => 'No payment success events in this period.',
            'columns' => [],
            'sort' => 'funnel_stage',
            'payment_success' => true,
        ],
        'products' => [
            'label' => 'Product performance',
            'empty' => 'No product activity in this period.',
            'columns' => ['products_viewed', 'adds', 'purchased'],
            'sort' => 'latest',
            'action_types' => ['product_view', 'product_view_popup', 'add_to_cart', 'payment_success'],
        ],
        'categories' => [
            'label' => 'Category performance',
            'empty' => 'No category activity in this period.',
            'columns' => ['top_category', 'purchases'],
            'sort' => 'latest',
            'action_types' => ['category_view', 'payment_success'],
        ],
        'devices' => [
            'label' => 'Device & browser',
            'empty' => 'No sessions in this period.',
            'columns' => ['device_detail'],
            'sort' => 'latest',
        ],
        'traffic' => [
            'label' => 'Traffic sources',
            'empty' => 'No sessions in this period.',
            'columns' => ['traffic_source', 'traffic_medium'],
            'sort' => 'latest',
        ],
        'session_quality' => [
            'label' => 'Session quality',
            'empty' => 'No sessions in this period.',
            'columns' => ['classification_reason'],
            'sort' => 'latest',
        ],
        'duration' => [
            'label' => 'Session duration',
            'empty' => 'No sessions in this duration range.',
            'columns' => [],
            'sort' => 'latest',
        ],
    ];

    public static function fromSection(string $section): ?string
    {
        return self::SECTION_MAP[$section] ?? (isset(self::DEFINITIONS[$section]) ? $section : null);
    }

    public static function isValid(?string $focus): bool
    {
        return filled($focus) && isset(self::DEFINITIONS[$focus]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(?string $focus): ?array
    {
        if (! self::isValid($focus)) {
            return null;
        }

        return self::DEFINITIONS[$focus];
    }

    public static function label(?string $focus): ?string
    {
        return self::definition($focus)['label'] ?? null;
    }

    public static function emptyMessage(?string $focus): string
    {
        return self::definition($focus)['empty'] ?? 'No visitor sessions found.';
    }

    public static function sortMode(?string $focus): string
    {
        return self::definition($focus)['sort'] ?? 'latest';
    }

    /**
     * @return array<int, string>
     */
    public static function baseColumns(): array
    {
        return ['session', 'user', 'trust', 'duration', 'last_active', 'view'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tableColumns(?string $focus, ?Request $request = null): array
    {
        $defs = [
            'actions_count' => ['key' => 'actions_count', 'label' => 'Actions', 'class' => 'etd-num'],
            'device' => ['key' => 'device', 'label' => 'Device'],
            'order_qty' => ['key' => 'order_qty', 'label' => 'Orders', 'class' => 'etd-num'],
            'order_value' => ['key' => 'order_value', 'label' => 'Order value', 'class' => 'etd-num'],
            'cart_qty' => ['key' => 'cart_qty', 'label' => 'Cart qty', 'class' => 'etd-num'],
            'cart_value' => ['key' => 'cart_value', 'label' => 'Cart value', 'class' => 'etd-num'],
            'checkout_qty' => ['key' => 'checkout_qty', 'label' => 'Qty', 'class' => 'etd-num'],
            'checkout_value' => ['key' => 'checkout_value', 'label' => 'Value', 'class' => 'etd-num'],
            'abandoned_at' => ['key' => 'abandoned_at', 'label' => 'Abandoned'],
            'products_viewed' => ['key' => 'products_viewed', 'label' => 'Products viewed', 'class' => 'etd-num'],
            'adds' => ['key' => 'adds', 'label' => 'Adds', 'class' => 'etd-num'],
            'purchased' => ['key' => 'purchased', 'label' => 'Bought'],
            'top_category' => ['key' => 'top_category', 'label' => 'Top category'],
            'purchases' => ['key' => 'purchases', 'label' => 'Purchases', 'class' => 'etd-num', 'tip' => 'Completed orders with items in this category'],
            'device_detail' => ['key' => 'device_detail', 'label' => 'Device & browser'],
            'traffic_source' => ['key' => 'traffic_source', 'label' => 'Source'],
            'traffic_medium' => ['key' => 'traffic_medium', 'label' => 'Medium'],
            'classification_reason' => ['key' => 'classification_reason', 'label' => 'Classification'],
        ];

        $focusKeys = self::definition($focus)['columns'] ?? [];

        $columns = array_values(array_filter(array_map(
            fn (string $key) => $defs[$key] ?? null,
            $focusKeys,
        )));

        if ($request?->filled('category')) {
            $columns = array_values(array_filter(
                $columns,
                fn (array $column) => ($column['key'] ?? '') !== 'top_category',
            ));
        }

        return $columns;
    }

    /**
     * Focus key used to load per-session funnel metrics (orders, abandonment, etc.).
     */
    public static function resolveFunnelMetricsFocus(Request $request): ?string
    {
        $focus = $request->input('focus');

        if (self::isValid($focus)) {
            $definition = self::definition($focus);

            if (! empty($definition['payment_success']) || ! empty($definition['funnel'])) {
                return $focus;
            }
        }

        $drawerFunnels = self::drawerFunnelFilterValues($request);

        return count($drawerFunnels) === 1 ? $drawerFunnels[0] : null;
    }

    /**
     * Whether per-session payment totals should be loaded for the activity table/export.
     */
    public static function shouldAttachPaymentMetrics(?string $focus, Request $request, array $funnelMetrics = []): bool
    {
        if ($funnelMetrics !== []) {
            return false;
        }

        if (in_array($focus, ['conversion', 'payment_success'], true)) {
            return true;
        }

        if ($request->filled('has_order') && $request->has_order === '1') {
            return true;
        }

        return in_array('payment_success', self::drawerFunnelFilterValues($request), true);
    }

    /**
     * Extra export columns driven by focus and active drawer filters.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportContextColumns(?string $focus, ?Request $request = null): array
    {
        $request ??= request();
        $columns = self::tableColumns($focus, $request);
        $existingKeys = collect($columns)->pluck('key')->all();

        foreach (self::exportFunnelColumnKeys($focus, $request) as $key) {
            $columns = self::appendExportColumn($columns, $existingKeys, $key);
        }

        if (
            TrackerMultiSelectFilter::requestFilled($request, 'device_type')
            && ! in_array('device', $existingKeys, true)
            && ! in_array('device_detail', $existingKeys, true)
        ) {
            $columns = self::appendExportColumn($columns, $existingKeys, 'device');
        }

        if (
            (TrackerMultiSelectFilter::requestFilled($request, 'utm_source')
                || TrackerMultiSelectFilter::requestFilled($request, 'utm_medium'))
            && ! in_array('traffic_source', $existingKeys, true)
        ) {
            $columns = self::appendExportColumn($columns, $existingKeys, 'traffic_source');
            $columns = self::appendExportColumn($columns, $existingKeys, 'traffic_medium');
        }

        if ($request->filled('has_order') && $request->has_order === '1') {
            $columns = self::appendExportColumn($columns, $existingKeys, 'order_qty');
            $columns = self::appendExportColumn($columns, $existingKeys, 'order_value');
        }

        return $columns;
    }

    /**
     * Human-readable active filter summary for export headers.
     */
    public static function exportFilterSummary(Request $request): string
    {
        $parts = [];

        if (self::isValid($request->input('focus'))) {
            $parts[] = 'Section: '.self::label($request->input('focus'));
        }

        foreach (self::drawerFunnelFilterValues($request) as $funnelKey) {
            $parts[] = 'Funnel: '.(self::sidebarFunnelFilterOptions()[$funnelKey] ?? $funnelKey);
        }

        if ($request->filled('has_order')) {
            $parts[] = $request->has_order === '1' ? 'Has order' : 'No order';
        }

        if ($request->filled('logged_in')) {
            $parts[] = $request->logged_in === '1' ? 'Logged in' : 'Guest';
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'device_type')) {
            $devices = TrackerMultiSelectFilter::allowedValues(
                $request->input('device_type'),
                ['desktop', 'mobile', 'tablet'],
            );
            $parts[] = 'Device: '.implode(', ', array_map('ucfirst', $devices));
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'duration_bucket')) {
            $parts[] = 'Duration filtered';
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'utm_source')) {
            $parts[] = 'Source filtered';
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'utm_medium')) {
            $parts[] = 'Medium filtered';
        }

        if ($request->filled('department')) {
            $parts[] = 'Department: '.$request->department;
        }

        if ($request->filled('category')) {
            $categories = TrackerMultiSelectFilter::requestValues($request, 'category');
            $parts[] = 'Category: '.implode(', ', $categories);
        }

        if ($request->filled('search')) {
            $parts[] = 'Search: '.Str::limit(trim((string) $request->search), 40);
        }

        $sortBy = EcomActivitySessionSort::effectiveSortBy($request);
        $sortLabel = EcomActivitySessionSort::sortOptions()[$sortBy] ?? $sortBy;
        $sortDir = strtoupper(EcomActivitySessionSort::resolveSortDir($request, $sortBy));
        $parts[] = 'Sort: '.$sortLabel.' ('.$sortDir.')';

        return implode(' · ', array_values(array_filter($parts)));
    }

    /**
     * @return list<string>
     */
    private static function exportFunnelColumnKeys(?string $focus, Request $request): array
    {
        $keys = [];

        foreach (self::resolveExportFunnelKeys($focus, $request) as $funnelKey) {
            $keys = array_merge($keys, match ($funnelKey) {
                'payment_success' => ['order_qty', 'order_value'],
                'cart_abandonment' => ['cart_qty', 'cart_value', 'abandoned_at'],
                'begin_checkout_abandonment', 'proceed_checkout_abandonment' => ['checkout_qty', 'checkout_value', 'abandoned_at'],
                default => [],
            });
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private static function resolveExportFunnelKeys(?string $focus, Request $request): array
    {
        if (self::isValid($focus)) {
            $definition = self::definition($focus);

            if (! empty($definition['payment_success']) || ! empty($definition['funnel'])) {
                return [(string) $focus];
            }
        }

        return self::drawerFunnelFilterValues($request);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $existingKeys
     * @return array<int, array<string, mixed>>
     */
    private static function appendExportColumn(array $columns, array &$existingKeys, string $key): array
    {
        if (in_array($key, $existingKeys, true)) {
            return $columns;
        }

        $column = self::exportColumnDefinition($key);

        if ($column === null) {
            return $columns;
        }

        $columns[] = $column;
        $existingKeys[] = $key;

        return $columns;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function exportColumnDefinition(string $key): ?array
    {
        return match ($key) {
            'device' => ['key' => 'device', 'label' => 'Device'],
            'order_qty' => ['key' => 'order_qty', 'label' => 'Orders', 'class' => 'etd-num'],
            'order_value' => ['key' => 'order_value', 'label' => 'Order value', 'class' => 'etd-num'],
            'cart_qty' => ['key' => 'cart_qty', 'label' => 'Cart qty', 'class' => 'etd-num'],
            'cart_value' => ['key' => 'cart_value', 'label' => 'Cart value', 'class' => 'etd-num'],
            'checkout_qty' => ['key' => 'checkout_qty', 'label' => 'Qty', 'class' => 'etd-num'],
            'checkout_value' => ['key' => 'checkout_value', 'label' => 'Value', 'class' => 'etd-num'],
            'abandoned_at' => ['key' => 'abandoned_at', 'label' => 'Abandoned'],
            'traffic_source' => ['key' => 'traffic_source', 'label' => 'Source'],
            'traffic_medium' => ['key' => 'traffic_medium', 'label' => 'Medium'],
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function focusColumnKeys(?string $focus): array
    {
        return self::definition($focus)['columns'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function implicitQueryParams(?string $focus): array
    {
        return self::definition($focus)['implicit'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{session_ids: Collection<int, string>, metrics: array<string, array<string, mixed>>}
     */
    public static function resolveFunnelContext(
        ?string $focus,
        Carbon $from,
        Carbon $to,
        array $sessionFilters,
        ?string $period,
        EcomActivityFunnelSessions $funnelSessions,
    ): array {
        $definition = self::definition($focus);

        if ($definition === null) {
            return ['session_ids' => collect(), 'metrics' => []];
        }

        if (! empty($definition['payment_success'])) {
            $data = $funnelSessions->paymentSuccessSessions($from, $to, $sessionFilters, $period);
        } elseif (! empty($definition['funnel'])) {
            $funnel = $definition['funnel'];
            $data = $funnelSessions->abandonedSessions(
                $from,
                $to,
                $funnel['stage'],
                $funnel['payload'],
                $funnel['exclude'],
                $sessionFilters,
                $period,
            );
        } else {
            return ['session_ids' => collect(), 'metrics' => []];
        }

        $metrics = [];

        foreach ($data['rows'] as $row) {
            $metrics[$row['session_id']] = $row;
        }

        return [
            'session_ids' => $data['session_ids'],
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sidebarFunnelFilterOptions(): array
    {
        return [
            '' => 'All',
            'cart_abandonment' => self::label('cart_abandonment') ?? 'Cart abandoned',
            'begin_checkout_abandonment' => self::label('begin_checkout_abandonment') ?? 'Begin checkout abandoned',
            'proceed_checkout_abandonment' => self::label('proceed_checkout_abandonment') ?? 'Proceed checkout abandoned',
            'payment_success' => self::label('payment_success') ?? 'Payment success',
        ];
    }

    public static function isSidebarFunnelFilterKey(?string $key): bool
    {
        return filled($key) && in_array($key, self::SIDEBAR_FUNNEL_FILTER_KEYS, true);
    }

    public static function drawerFunnelSelectedValue(Request $request): string
    {
        return self::drawerFunnelSelectedValues($request)[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function drawerFunnelSelectedValues(Request $request): array
    {
        $selected = TrackerMultiSelectFilter::allowedValues(
            $request->input('funnel'),
            self::SIDEBAR_FUNNEL_FILTER_KEYS,
        );

        if ($selected !== []) {
            return $selected;
        }

        $focus = $request->input('focus');

        if (self::isSidebarFunnelFilterKey($focus)) {
            return [(string) $focus];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function drawerFunnelFilterValues(Request $request): array
    {
        $focus = $request->input('focus');
        $selected = self::drawerFunnelSelectedValues($request);

        if ($selected === []) {
            return [];
        }

        if (self::isValid($focus) && in_array($focus, $selected, true)) {
            $selected = array_values(array_filter(
                $selected,
                static fn (string $value) => $value !== $focus,
            ));
        }

        return $selected;
    }

    public static function shouldApplyDrawerFunnelFilter(Request $request): bool
    {
        return self::drawerFunnelFilterValues($request) !== [];
    }

    public static function applyDrawerFunnelFilter(
        Builder $query,
        Request $request,
        Carbon $from,
        Carbon $to,
        EcomActivityFunnelSessions $funnelSessions,
    ): void {
        if (! self::shouldApplyDrawerFunnelFilter($request)) {
            return;
        }

        CommerceFunnelQuery::applySidebarFunnelKeys(
            $query,
            self::drawerFunnelFilterValues($request),
            $from,
            $to,
        );
    }

    public static function applyFocusFilter(
        Builder $query,
        ?string $focus,
        Carbon $from,
        Carbon $to,
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        EcomActivityFunnelSessions $funnelSessions,
        array $except = [],
    ): void {
        if (! self::isValid($focus)) {
            return;
        }

        $definition = self::definition($focus);
        $period = $request->input('period', '24h');
        $sessionFilters = self::sessionFiltersFromRequest($request);

        if (! empty($definition['funnel']) || ! empty($definition['payment_success'])) {
            if (! empty($definition['payment_success'])) {
                CommerceFunnelQuery::applyPaymentSuccessSessionFilter($query, $from, $to);
            } elseif (! empty($definition['funnel'])) {
                $funnel = $definition['funnel'];
                CommerceFunnelQuery::applyAbandonedSessionFilter(
                    $query,
                    $funnel['stage'],
                    $funnel['exclude'],
                    $from,
                    $to,
                );
            }

            return;
        }

        if (! empty($definition['action_types'])) {
            if (
                in_array($focus, ['products', 'categories'], true)
                && self::productCatalogFiltersFromRequest($request, $except) !== []
            ) {
                self::applyProductCatalogConstraints($query, $from, $to, $request, $dashboardService, $period, $except);

                return;
            }

            $query->where(function (Builder $inner) use ($from, $to, $focus) {
                if ($focus === 'products') {
                    $inner->where('has_add_to_cart', true)
                        ->orWhere('has_begin_checkout', true)
                        ->orWhere('has_proceed_checkout', true)
                        ->orWhere('has_payment_success', true);
                }

                $inner->orWhereExists(function ($exists) use ($from, $to, $focus) {
                    $exists->selectRaw('1')
                        ->from('activity_ecom_commerce_line_items as li')
                        ->whereColumn('li.session_id', 'activity_ecom_user.session_id')
                        ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to));

                    if ($focus === 'categories') {
                        $exists->where(function ($category) {
                            $category->where('li.funnel_stage', 'payment_success')
                                ->orWhere(function ($named) {
                                    $named->whereNotNull('li.category_name')
                                        ->where('li.category_name', '!=', '');
                                });
                        });
                    }
                });
            });

            if ($focus === 'products' || $focus === 'categories') {
                self::applyProductCatalogConstraints($query, $from, $to, $request, $dashboardService, $period, $except);
            }

            return;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionFiltersFromRequest(Request $request, array $except = []): array
    {
        $keys = array_merge(self::SHARED_SESSION_FILTER_KEYS, self::DASHBOARD_AUDIENCE_FILTER_KEYS);
        $keys = array_values(array_diff($keys, $except));

        return array_filter(
            array_intersect_key($request->only($keys), array_flip($keys)),
            static fn ($value) => is_array($value) ? $value !== [] : filled($value),
        );
    }

    /**
     * Catalog / keyword filters used for activity summary funnel totals.
     *
     * @return array<string, mixed>
     */
    public static function activitySummaryCatalogFiltersFromRequest(Request $request): array
    {
        $keys = [
            'search',
            'product_code',
            'product_name',
            'category',
            'department',
            'color',
            'size',
            'activity',
            'has_purchases',
            'has_views',
            'has_adds',
            'event_scenario',
        ];

        $filters = array_filter(
            array_intersect_key($request->only($keys), array_flip($keys)),
            fn ($value) => filled($value),
        );

        if (self::shouldApplySessionKeywordSearch($request)) {
            unset($filters['search']);
        }

        return $filters;
    }

    public static function activitySummaryKeywordSearch(Request $request): ?string
    {
        if (! self::shouldApplySessionKeywordSearch($request)) {
            return null;
        }

        $search = trim((string) $request->input('search', ''));

        return $search !== '' ? $search : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function activitySummaryFiltersFromRequest(Request $request): array
    {
        $filters = array_merge(
            self::sessionFiltersFromRequest($request),
            self::activitySummaryCatalogFiltersFromRequest($request),
        );

        if ($keyword = self::activitySummaryKeywordSearch($request)) {
            $filters['keyword_search'] = $keyword;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public static function usesActionScopedSessionDate(Request $request): bool
    {
        $focus = $request->input('focus');

        if (! in_array($focus, ['products', 'categories'], true)) {
            return false;
        }

        return self::productCatalogFiltersFromRequest($request) !== [];
    }

    public static function productCatalogFiltersFromRequest(Request $request, array $except = []): array
    {
        $keys = array_values(array_diff([
            'search',
            'product_code',
            'product_name',
            'category',
            'department',
            'color',
            'size',
            'activity',
            'has_purchases',
            'has_views',
            'has_adds',
            'event_scenario',
        ], $except));

        if (! self::usesCatalogScopedSearch($request)) {
            $keys = array_values(array_diff($keys, ['search']));
        } elseif (self::resolvedProductDrillLabel($request) !== null) {
            $keys = array_values(array_diff($keys, ['search']));
        }

        return array_filter(
            array_intersect_key($request->only($keys), array_flip($keys)),
            static fn ($value) => is_array($value) ? $value !== [] : filled($value),
        );
    }

    /**
     * Catalog filters for the activity index, including strict product-code search.
     *
     * @return array<string, mixed>
     */
    public static function indexCatalogFiltersFromRequest(Request $request, array $except = []): array
    {
        $filters = self::productCatalogFiltersFromRequest($request, $except);

        if (
            $request->filled('search')
            && ! self::usesCatalogScopedSearch($request)
            && self::shouldUseProductScopedSearchInIndex($request)
        ) {
            $filters['search'] = trim((string) $request->search);
        }

        return $filters;
    }

    public static function looksLikeIdentitySearch(string $search): bool
    {
        return str_contains(trim($search), '@');
    }

    public static function looksLikeProductCodeSearch(string $search): bool
    {
        $search = trim($search);

        if ($search === '' || self::looksLikeIdentitySearch($search)) {
            return false;
        }

        return (bool) preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9\-_.]+$/', $search);
    }

    public static function shouldUseProductScopedSearchInIndex(Request $request): bool
    {
        if (! $request->filled('search') || self::usesCatalogScopedSearch($request)) {
            return false;
        }

        return self::looksLikeProductCodeSearch((string) $request->search);
    }

    public static function searchFilterLabel(Request $request): string
    {
        return self::usesCatalogScopedSearch($request) ? 'Product search' : 'Search';
    }

    public static function showCatalogFiltersInDrawer(Request $request): bool
    {
        return in_array($request->input('focus'), ['products', 'categories'], true);
    }

    public static function isDashboardSectionFocus(?string $focus): bool
    {
        return self::isValid($focus);
    }

    /**
     * Extra catalog controls (color, size, activity, etc.) belong on the dashboard,
     * not in activity drill-down drawers — keep those aligned with payment success.
     */
    public static function showProductCatalogExtrasInDrawer(Request $request): bool
    {
        return false;
    }

    /**
     * Keyword search field in the activity filter drawer.
     */
    public static function showActivitySearchInDrawer(Request $request): bool
    {
        return true;
    }

    public static function usesCatalogScopedSearch(Request $request): bool
    {
        return self::showCatalogFiltersInDrawer($request);
    }

    /**
     * @return array<int, string>
     */
    public static function catalogFilterQueryKeys(): array
    {
        return [
            'search',
            'color',
            'size',
            'activity',
            'event_scenario',
            'has_purchases',
            'has_views',
            'has_adds',
        ];
    }

    public static function shouldApplyCatalogConstraintsInIndexQuery(?string $focus, Request $request): bool
    {
        if (self::indexCatalogFiltersFromRequest($request) === []) {
            return false;
        }

        if (! self::isValid($focus)) {
            return true;
        }

        return ! in_array($focus, ['products', 'categories'], true);
    }

    /**
     * @return array<int, string>
     */
    public static function sidebarFilterQueryKeys(?Request $request = null): array
    {
        return array_merge(
            ['search', 'department', 'category', 'funnel', 'duration_bucket'],
            self::SHARED_SESSION_FILTER_KEYS,
            self::DASHBOARD_AUDIENCE_FILTER_KEYS,
        );
    }

    /**
     * @return list<string>
     */
    public static function activitySidebarChipLabels(Request $request): array
    {
        $labels = ['Department', 'Category', 'Funnel', 'Device', 'Login', 'Orders', 'Visitor type', 'Source', 'Medium', 'Duration'];

        if (self::showActivitySearchInDrawer($request) && ! self::usesCatalogScopedSearch($request)) {
            $labels[] = self::searchFilterLabel($request);
        }

        if (self::showCatalogFiltersInDrawer($request)) {
            $labels[] = 'Product';

            if (self::shouldApplySessionKeywordSearch($request)) {
                $labels[] = 'Search';
            } elseif ($request->filled('search')) {
                $labels[] = 'Product search';
            }
        }

        return $labels;
    }

    /**
     * Drill-down and catalog params kept when applying sidebar filters.
     *
     * @return array<string, mixed>
     */
    public static function drawerPreserveQueryParams(Request $request): array
    {
        $editableKeys = self::sidebarFilterQueryKeys($request);

        $preserveKeys = array_values(array_diff(
            EcomTrackerViewData::activityQueryKeys(),
            $editableKeys,
        ));

        return array_filter(
            $request->only($preserveKeys),
            fn ($value) => filled($value),
        );
    }

    public static function sidebarFilterActiveCount(Request $request): int
    {
        return self::activeFilterCount($request);
    }

    public static function activeFilterCount(Request $request): int
    {
        $count = collect(self::sidebarFilterQueryKeys($request))
            ->sum(function (string $key) use ($request) {
                if ($key === 'funnel') {
                    return count(TrackerMultiSelectFilter::allowedValues(
                        $request->input('funnel'),
                        self::SIDEBAR_FUNNEL_FILTER_KEYS,
                    ));
                }

                return count(TrackerMultiSelectFilter::requestValues($request, $key));
            });

        if (self::resolvedProductDrillLabel($request) !== null) {
            $count++;
        }

        return $count;
    }

    public static function sidebarFilterResetUrl(Request $request): string
    {
        return route('admin.ecom-activity.index', self::drawerPreserveQueryParams($request));
    }

    public static function shouldDeferHasOrderFilter(Request $request): bool
    {
        $focus = $request->input('focus');

        if (! in_array($focus, ['categories', 'products'], true)) {
            return false;
        }

        return self::productCatalogFiltersFromRequest($request) !== [];
    }

    public static function resolvedCategoryDepartment(
        Request $request,
        Carbon $from,
        Carbon $to,
        ?string $period,
        array $filterOptions = [],
    ): ?string {
        if (! TrackerMultiSelectFilter::requestFilled($request, 'category')) {
            return null;
        }

        $category = TrackerMultiSelectFilter::requestValues($request, 'category')[0] ?? '';

        if ($filterOptions !== []) {
            $departments = TrackerCategoryIdentity::departmentsForCategoryInFilterOptions($category, $filterOptions);

            if (count($departments) === 1) {
                return $departments[0];
            }

            if ($request->filled('department')) {
                $department = TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department'));

                if (in_array($department, $departments, true)) {
                    return $department;
                }

                return $departments[0] ?? TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department'));
            }
        }

        if ($request->filled('department')) {
            return TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department'));
        }

        $row = app(EcomTrackerDashboardService::class)->categoryPerformanceForName(
            $from,
            $to,
            $category,
            self::sessionFiltersFromRequest($request),
            $period,
        );

        $departmentName = trim((string) ($row['department_name'] ?? ''));

        return $departmentName !== '' ? $departmentName : null;
    }

    /**
     * @return array{department?: string|null, category?: string|null}|null
     */
    public static function reconcileCatalogFilters(Request $request, array $filterOptions): ?array
    {
        $categories = TrackerMultiSelectFilter::requestValues($request, 'category');

        if ($categories === []) {
            return null;
        }

        if (count($categories) === 1) {
            return self::reconcileSingleCatalogCategory($request, $filterOptions, $categories[0]);
        }

        $department = TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department', ''));

        if ($department === '') {
            return null;
        }

        $validCategories = array_values(array_filter(
            $categories,
            static fn (string $category) => TrackerCategoryIdentity::categoryListedForDepartment(
                $category,
                $department,
                $filterOptions,
            ),
        ));

        if ($validCategories === $categories) {
            return null;
        }

        return [
            'department' => $department,
            'category' => $validCategories === [] ? null : $validCategories,
        ];
    }

    /**
     * @return array{department?: string|null, category?: string|null}|null
     */
    private static function reconcileSingleCatalogCategory(
        Request $request,
        array $filterOptions,
        string $category,
    ): ?array {
        $department = TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department', ''));

        if ($department !== ''
            && TrackerCategoryIdentity::categoryListedForDepartment($category, $department, $filterOptions)) {
            return null;
        }

        $departments = TrackerCategoryIdentity::departmentsForCategoryInFilterOptions($category, $filterOptions);

        if ($departments === []) {
            return [
                'department' => $department !== '' ? $department : null,
                'category' => null,
            ];
        }

        if (count($departments) === 1) {
            return [
                'department' => $departments[0],
                'category' => $category,
            ];
        }

        if ($department !== '' && in_array($department, $departments, true)) {
            return null;
        }

        return [
            'department' => $departments[0],
            'category' => $category,
        ];
    }

    public static function applyProductCatalogConstraints(
        Builder $query,
        Carbon $from,
        Carbon $to,
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        ?string $period,
        array $except = [],
    ): void {
        $productFilters = array_merge(
            self::sessionFiltersFromRequest($request, $except),
            self::indexCatalogFiltersFromRequest($request, $except),
        );

        if ($productFilters === []) {
            return;
        }

        $ids = $dashboardService->productCatalogSessionIds($from, $to, $productFilters, $period);

        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        self::constrainToSessionIds($query, $ids);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  Collection<int, string>  $sessionIds
     */
    public static function constrainToSessionIds(Builder $query, Collection $sessionIds): void
    {
        $ids = $sessionIds->values()->all();

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $query->getModel()->getTable().'.session_id';

        if (count($ids) <= 1000) {
            $query->whereIn($column, $ids);

            return;
        }

        $query->where(function (Builder $inner) use ($ids, $column) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $inner->orWhereIn($column, $chunk);
            }
        });
    }

    public static function resolveFilterSummaryFocus(Request $request): ?string
    {
        $focus = $request->input('focus');

        if (self::isValid($focus)) {
            return $focus;
        }

        if ($request->filled('category')) {
            return 'categories';
        }

        if ($request->filled('department')) {
            return 'categories';
        }

        if ($request->filled('product_code') || $request->filled('product_name')
            || self::productCatalogFiltersFromRequest($request) !== []) {
            return 'products';
        }

        if ($request->filled('device_type')) {
            return 'devices';
        }

        if ($request->filled('duration_bucket')) {
            return 'duration';
        }

        if ($request->filled('utm_source') || $request->filled('utm_medium')) {
            return 'traffic';
        }

        $drawerFunnels = self::drawerFunnelFilterValues($request);

        if (count($drawerFunnels) === 1 && self::isValid($drawerFunnels[0])) {
            return $drawerFunnels[0];
        }

        if (self::activeFilterCount($request) > 0) {
            return 'audience';
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<string, mixed>|null
     */
    public static function activityListContext(
        Request $request,
        string $rangeLabel,
        int $sessionCount,
        array $funnelMetrics = [],
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $period = null,
    ): ?array {
        $summaryFocus = self::resolveFilterSummaryFocus($request);

        if ($summaryFocus === null) {
            return null;
        }

        $criteria = self::filterCriteriaFromRequest($request);

        if ($summaryFocus === 'categories' && $request->filled('category') && $from !== null && $to !== null) {
            $criteria = self::dedupeCategoryDrillDownCriteria(
                self::enrichCategoryDrillDownCriteria($request, $criteria, $from, $to, $period),
            );
        }

        $metrics = self::summaryForFocus($summaryFocus, $sessionCount, $funnelMetrics, $request, $from, $to, $period);
        $hasDashboardFocus = self::isValid($request->input('focus'));

        return [
            'section' => self::label($summaryFocus),
            'description' => self::drillDownDescription($summaryFocus) ?? self::filterSummaryDescription($summaryFocus),
            'range_label' => $rangeLabel,
            'criteria' => $criteria,
            'metrics' => $metrics,
            'filter_chips' => self::filterChipsFromCriteria($request, $criteria, $hasDashboardFocus),
            'clear_focus_url' => $hasDashboardFocus
                ? $request->fullUrlWithQuery(['focus' => null, 'page' => null])
                : self::sidebarFilterResetUrl($request),
            'clear_label' => $hasDashboardFocus ? 'Clear section' : 'Clear filters',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<string, mixed>|null
     */
    public static function drillDownContext(
        Request $request,
        ?string $focus,
        string $rangeLabel,
        int $sessionCount,
        array $funnelMetrics = [],
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $period = null,
    ): ?array {
        return self::activityListContext(
            $request,
            $rangeLabel,
            $sessionCount,
            $funnelMetrics,
            $from,
            $to,
            $period,
        );
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function filterCriteriaFromRequest(Request $request): array
    {
        $criteria = [];
        $visitorLabels = VisitorClassificationLabels::filterTypeLabels();
        $service = app(EcomTrackerDashboardService::class);
        $scenarioOptions = $service->productCatalogEventScenarioOptions();
        $activityOptions = $service->productCatalogActivityFilterOptions();

        $add = static function (string $label, mixed $value, ?string $token = null) use (&$criteria): void {
            if (filled($value)) {
                $entry = ['label' => $label, 'value' => (string) $value];

                if ($token !== null && $token !== '') {
                    $entry['token'] = $token;
                }

                $criteria[] = $entry;
            }
        };

        if ($productLabel = self::resolvedProductDrillLabel($request)) {
            $add('Product', $productLabel);
        }

        if ($request->filled('search')) {
            if (self::shouldApplySessionKeywordSearch($request)) {
                $add('Search', '"'.$request->search.'"');
            } elseif (self::usesCatalogScopedSearch($request)) {
                $add('Product search', '"'.$request->search.'"');
            } else {
                $add(self::searchFilterLabel($request), '"'.$request->search.'"');
            }
        }

        if ($request->filled('category')) {
            foreach (TrackerMultiSelectFilter::requestValues($request, 'category') as $category) {
                $add(
                    'Category',
                    TrackerCategoryIdentity::label(
                        (string) $request->input('department', ''),
                        $category,
                    ),
                    $category,
                );
            }
        } elseif ($request->filled('department')) {
            $add('Department', (string) $request->input('department'));
        }

        $add('Color', $request->input('color'));
        $add('Size', $request->input('size'));

        foreach (TrackerMultiSelectFilter::allowedValues(
            $request->input('device_type'),
            ['desktop', 'mobile', 'tablet'],
        ) as $device) {
            $add('Device', ucfirst($device), $device);
        }

        foreach (TrackerMultiSelectFilter::requestValues($request, 'duration_bucket') as $durationBucket) {
            $add(
                'Duration',
                SessionDurationBuckets::labelForKey($durationBucket) ?? $durationBucket,
                $durationBucket,
            );
        }

        if ($request->filled('logged_in')) {
            $add('Login', $request->logged_in === '1' ? 'Logged in' : 'Guest');
        }

        if ($request->filled('has_order')) {
            $add('Orders', $request->has_order === '1' ? 'With order' : 'No order');
        }

        if (self::shouldApplyDrawerFunnelFilter($request)) {
            foreach (self::drawerFunnelFilterValues($request) as $funnel) {
                $add('Funnel', self::sidebarFunnelFilterOptions()[$funnel] ?? $funnel, $funnel);
            }
        }

        if ($request->filled('visitor_type')) {
            $add('Visitor type', $visitorLabels[$request->visitor_type] ?? (string) $request->visitor_type);
        }

        $add('Country', $request->input('country'));

        if ($request->filled('utm_source')) {
            foreach (TrackerMultiSelectFilter::requestValues($request, 'utm_source') as $source) {
                $sourceLabel = TrackerUtmFilter::sources()[$source] ?? $source;
                $add('Source', $sourceLabel, $source);
            }
        }

        if ($request->filled('utm_medium')) {
            foreach (TrackerMultiSelectFilter::requestValues($request, 'utm_medium') as $medium) {
                $mediumLabel = TrackerUtmFilter::mediums()[$medium] ?? $medium;
                $add('Medium', $mediumLabel, $medium);
            }
        }

        if ($request->filled('activity')) {
            $add('Activity', $activityOptions[$request->activity] ?? $request->activity);
        }

        if ($request->filled('event_scenario')) {
            $add('Funnel step', $scenarioOptions[$request->event_scenario] ?? $request->event_scenario);
        }

        foreach (['has_purchases' => 'Has purchases', 'has_views' => 'Has views', 'has_adds' => 'Has cart adds'] as $key => $label) {
            if ($request->input($key) === '1') {
                $criteria[] = ['label' => $label, 'value' => 'Yes'];
            }
        }

        return $criteria;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $criteria
     * @return array<int, array{label: string, value: string}>
     */
    private static function enrichCategoryDrillDownCriteria(
        Request $request,
        array $criteria,
        Carbon $from,
        Carbon $to,
        ?string $period,
    ): array {
        $departmentName = self::resolvedCategoryDepartment($request, $from, $to, $period)
            ?? TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department', ''));

        return collect($criteria)->map(function (array $criterion) use ($departmentName) {
            if (($criterion['label'] ?? '') !== 'Category') {
                return $criterion;
            }

            $categoryName = trim((string) ($criterion['token'] ?? ''));

            if ($categoryName === '') {
                return $criterion;
            }

            $entry = [
                'label' => 'Category',
                'value' => TrackerCategoryIdentity::label($departmentName, $categoryName),
                'token' => $categoryName,
            ];

            return $entry;
        })->all();
    }

    /**
     * @param  array<int, array{label: string, value: string, token?: string}>  $criteria
     * @return array<int, array{label: string, value: string, token?: string}>
     */
    private static function dedupeCategoryDrillDownCriteria(array $criteria): array
    {
        $seenTokens = [];
        $deduped = [];

        foreach ($criteria as $criterion) {
            if (($criterion['label'] ?? '') !== 'Category') {
                $deduped[] = $criterion;

                continue;
            }

            $token = mb_strtolower(trim((string) ($criterion['token'] ?? '')));

            if ($token !== '' && isset($seenTokens[$token])) {
                continue;
            }

            if ($token !== '') {
                $seenTokens[$token] = true;
            }

            $deduped[] = $criterion;
        }

        return $deduped;
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    public static function filterChipsFromRequest(Request $request): array
    {
        return self::filterChipsFromCriteria(
            $request,
            self::filterCriteriaFromRequest($request),
            includeFocus: true,
        );
    }

    /**
     * @param  array<int, array{label: string, value: string, token?: string}>  $criteria
     * @return array<int, array{label: string, remove_url: string}>
     */
    public static function filterChipsFromCriteria(
        Request $request,
        array $criteria,
        bool $includeFocus = false,
    ): array {
        $chips = [];

        if ($includeFocus && $request->filled('focus') && self::isValid($request->input('focus'))) {
            $chips[] = [
                'label' => 'Section: '.self::label($request->input('focus')),
                'remove_url' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
            ];
        }

        foreach ($criteria as $criterion) {
            $chip = self::filterChipFromCriterion($request, $criterion);

            if ($chip !== null) {
                $chips[] = $chip;
            }
        }

        return $chips;
    }

    /**
     * @param  array{label: string, value: string, token?: string}  $criterion
     * @return array{label: string, remove_url: string}|null
     */
    private static function filterChipFromCriterion(Request $request, array $criterion): ?array
    {
        $key = self::filterChipKeyForCriterionLabel($criterion['label'] ?? '');

        if ($key === null) {
            return null;
        }

        $label = self::filterChipDisplayLabel($criterion);

        if ($key === 'category') {
            return [
                'label' => $label,
                'remove_url' => $request->fullUrlWithQuery(array_merge(
                    self::filterChipRemoveQuery($request, 'category', $criterion['token'] ?? null),
                    ['page' => null],
                )),
            ];
        }

        if ($key === 'department') {
            return [
                'label' => $label,
                'remove_url' => $request->fullUrlWithQuery(['department' => null, 'category' => null, 'page' => null]),
            ];
        }

        if ($key === 'product') {
            return [
                'label' => $label,
                'remove_url' => $request->fullUrlWithQuery(['product_code' => null, 'product_name' => null, 'page' => null]),
            ];
        }

        return [
            'label' => $label,
            'remove_url' => $request->fullUrlWithQuery(array_merge(
                self::filterChipRemoveQuery($request, $key, $criterion['token'] ?? null),
                ['page' => null],
            )),
        ];
    }

    /**
     * @param  array{label: string, value: string, token?: string}  $criterion
     */
    private static function filterChipDisplayLabel(array $criterion): string
    {
        $label = trim((string) ($criterion['label'] ?? ''));
        $value = trim((string) ($criterion['value'] ?? ''));

        if ($label === 'Product') {
            return 'Product: '.$value;
        }

        if ($value === '') {
            return $label;
        }

        return $label !== '' ? $label.': '.$value : $value;
    }

    private static function filterChipKeyForCriterionLabel(string $label): ?string
    {
        return match ($label) {
            'Product', 'Product code' => 'product',
            'Product search', 'Search' => 'search',
            'Category' => 'category',
            'Department' => 'department',
            'Color' => 'color',
            'Size' => 'size',
            'Device' => 'device_type',
            'Duration' => 'duration_bucket',
            'Login' => 'logged_in',
            'Orders' => 'has_order',
            'Funnel' => 'funnel',
            'Visitor type' => 'visitor_type',
            'Country' => 'country',
            'Source' => 'utm_source',
            'Medium' => 'utm_medium',
            'Activity' => 'activity',
            'Funnel step' => 'event_scenario',
            'Has purchases' => 'has_purchases',
            'Has views' => 'has_views',
            'Has cart adds' => 'has_adds',
            default => null,
        };
    }

    /**
     * Short product drill-down label for filters and summary chips.
     */
    public static function resolvedProductDrillLabel(Request $request): ?string
    {
        $code = trim((string) $request->input('product_code', ''));

        if ($code !== '') {
            return $code;
        }

        $name = trim((string) $request->input('product_name', ''));

        return $name !== '' ? $name : null;
    }

    /**
     * Session keyword search in the filter drawer (email, session id, phone, etc.).
     */
    public static function showSessionKeywordSearchInDrawer(Request $request): bool
    {
        if (! self::showCatalogFiltersInDrawer($request)) {
            return true;
        }

        return self::resolvedProductDrillLabel($request) !== null;
    }

    /**
     * Apply session keyword search on the activity index (not catalog product search).
     */
    public static function shouldApplySessionKeywordSearch(Request $request): bool
    {
        if (! $request->filled('search')) {
            return false;
        }

        if (! self::usesCatalogScopedSearch($request)) {
            return ! self::shouldUseProductScopedSearchInIndex($request);
        }

        return self::resolvedProductDrillLabel($request) !== null;
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    public static function sidebarFilterChipsFromRequest(Request $request): array
    {
        return self::activeFilterChipsFromRequest($request);
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    public static function activeFilterChipsFromRequest(Request $request): array
    {
        $chipKeyMap = [
            'Product' => 'product',
            'Product search' => 'search',
            'Search' => 'search',
            'Category' => 'category',
            'Department' => 'department',
            'Color' => 'color',
            'Size' => 'size',
            'Device' => 'device_type',
            'Duration' => 'duration_bucket',
            'Login' => 'logged_in',
            'Orders' => 'has_order',
            'Funnel' => 'funnel',
            'Visitor type' => 'visitor_type',
            'Country' => 'country',
            'Source' => 'utm_source',
            'Medium' => 'utm_medium',
            'Activity' => 'activity',
            'Funnel step' => 'event_scenario',
            'Has purchases' => 'has_purchases',
            'Has views' => 'has_views',
            'Has cart adds' => 'has_adds',
        ];

        $sidebarLabels = self::activitySidebarChipLabels($request);

        $allowedLabels = $sidebarLabels;

        $chips = [];

        foreach (self::filterCriteriaFromRequest($request) as $criterion) {
            $label = $criterion['label'] ?? '';

            if (! in_array($label, $allowedLabels, true)) {
                continue;
            }

            $key = $chipKeyMap[$label] ?? null;

            if ($key === null) {
                continue;
            }

            if ($key === 'category') {
                $chips[] = [
                    'label' => $label.': '.$criterion['value'],
                    'remove_url' => $request->fullUrlWithQuery(array_merge(
                        self::filterChipRemoveQuery($request, 'category', $criterion['token'] ?? null),
                        ['page' => null],
                    )),
                ];

                continue;
            }

            if ($key === 'department') {
                $chips[] = [
                    'label' => $label.': '.$criterion['value'],
                    'remove_url' => $request->fullUrlWithQuery(['department' => null, 'category' => null, 'page' => null]),
                ];

                continue;
            }

            if ($key === 'product') {
                $chips[] = [
                    'label' => 'Product: '.$criterion['value'],
                    'remove_url' => $request->fullUrlWithQuery(['product_code' => null, 'product_name' => null, 'page' => null]),
                ];

                continue;
            }

            $chips[] = [
                'label' => $label.': '.$criterion['value'],
                'remove_url' => $request->fullUrlWithQuery(array_merge(
                    self::filterChipRemoveQuery($request, $key, $criterion['token'] ?? null),
                    ['page' => null],
                )),
            ];
        }

        return $chips;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filterChipRemoveQuery(Request $request, string $key, ?string $token = null): array
    {
        $multiKeys = ['device_type', 'duration_bucket', 'utm_source', 'utm_medium', 'funnel', 'category'];

        if ($token !== null && $token !== '' && in_array($key, $multiKeys, true)) {
            $query = TrackerMultiSelectFilter::queryWithoutValue(
                $key,
                TrackerMultiSelectFilter::requestValues($request, $key),
                $token,
            );

            if ($key === 'category' && ($query['category'] ?? null) === null) {
                $query['department'] = null;
            }

            return $query;
        }

        if ($key === 'category' || $key === 'department') {
            return ['department' => null, 'category' => null];
        }

        if ($key === 'product') {
            return ['product_code' => null, 'product_name' => null];
        }

        return [$key => null];
    }

    private static function drillDownDescription(?string $focus): ?string
    {
        return match ($focus) {
            'cart_abandonment' => 'Sessions that added to cart in this period but did not begin checkout, proceed, or pay.',
            'begin_checkout_abandonment' => 'Sessions that began checkout in this period and did not proceed, pay, or return to cart.',
            'proceed_checkout_abandonment' => 'Sessions that proceeded to checkout in this period and did not pay or return to an earlier step.',
            'payment_success' => 'Sessions with a completed payment in this period.',
            'conversion' => 'Sessions with a completed order in this period.',
            'products' => 'Sessions with product views, cart, or purchase activity matching the filters below.',
            'categories' => 'Sessions with category or product activity in the selected category.',
            'devices' => 'Sessions on the selected device type from the dashboard.',
            'duration' => 'Sessions whose stay time falls in the selected duration bucket.',
            'traffic' => 'Sessions from the selected traffic source or medium.',
            'session_quality' => 'Sessions matching the selected visitor classification.',
            'audience' => 'All sessions in the selected date range.',
            default => null,
        };
    }

    private static function filterSummaryDescription(?string $summaryFocus): ?string
    {
        return match ($summaryFocus) {
            'devices' => 'Sessions on the selected device type.',
            'duration' => 'Sessions whose stay time falls in the selected duration bucket.',
            'traffic' => 'Sessions from the selected traffic source or medium.',
            'categories' => 'Sessions with category or product activity in the selected category.',
            'products' => 'Sessions with product views, cart, or purchase activity matching the filters below.',
            'audience' => 'Sessions matching the selected filters.',
            default => null,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<int, array{label: string, value: int|string}>
     */
    public static function summaryForFocus(
        ?string $focus,
        int $sessionCount,
        array $funnelMetrics = [],
        ?Request $request = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $period = null,
    ): array {
        if (! self::isValid($focus)) {
            return [];
        }

        $atStake = round(collect($funnelMetrics)->sum(fn (array $row) => (float) ($row['value'] ?? 0)), 2);

        return match ($focus) {
            'cart_abandonment', 'begin_checkout_abandonment', 'proceed_checkout_abandonment' => array_merge(
                [
                    ['label' => 'Matching sessions', 'value' => $sessionCount],
                    ['label' => 'At stake', 'value' => '£'.number_format($atStake, 2)],
                ],
                self::abandonmentSummaryExtras($funnelMetrics),
            ),
            'payment_success' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::paymentSuccessSummaryMetrics($sessionCount, $funnelMetrics),
            ),
            'conversion' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::conversionSummaryMetrics($sessionCount, $funnelMetrics, $request, $from, $to, $period),
            ),
            'categories' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::categoryPerformanceSummaryMetrics($request, $from, $to, $period),
            ),
            'products' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::productPerformanceSummaryMetrics($request, $from, $to, $period),
            ),
            'devices' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::devicePerformanceSummaryMetrics($request, $from, $to, $period),
            ),
            'traffic' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::trafficSourceSummaryMetrics($request, $from, $to, $period),
            ),
            'audience' => array_merge(
                [['label' => 'Matching sessions', 'value' => $sessionCount]],
                self::audienceSummaryMetrics($request, $from, $to, $period),
            ),
            default => [
                ['label' => 'Matching sessions', 'value' => $sessionCount],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{views: int, adds: int, begin_checkouts: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}
     */
    private static function normalizeAcquisitionRow(array $row): array
    {
        return [
            'views' => (int) ($row['views'] ?? 0),
            'adds' => (int) ($row['adds'] ?? $row['add_to_cart'] ?? 0),
            'begin_checkouts' => (int) ($row['begin_checkouts'] ?? $row['begin_checkout'] ?? 0),
            'proceed_checkouts' => (int) ($row['proceed_checkouts'] ?? $row['proceed_checkout'] ?? 0),
            'purchases' => (int) ($row['purchases'] ?? $row['payment_success'] ?? 0),
            'qty' => (int) ($row['qty'] ?? $row['sold_qty'] ?? $row['sale_items'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? $row['sale_amount'] ?? 0),
        ];
    }

    /**
     * @param  array{views: int, adds: int, begin_checkouts: int, proceed_checkouts: int, purchases: int, qty: int, revenue: float}  $normalized
     * @return array<int, array{label: string, value: string}>
     */
    private static function formatFunnelSummaryMetrics(array $normalized): array
    {
        $cartAbandonment = max(0, $normalized['adds'] - $normalized['proceed_checkouts']);

        return [
            ['label' => 'Adds', 'value' => number_format($normalized['adds'])],
            ['label' => 'Checkout', 'value' => number_format($normalized['begin_checkouts'])],
            ['label' => 'Proceed', 'value' => number_format($normalized['proceed_checkouts'])],
            ['label' => 'Cart abandoned', 'value' => number_format($cartAbandonment)],
            ['label' => 'Sold', 'value' => number_format($normalized['purchases'])],
            ['label' => 'Sold qty', 'value' => number_format($normalized['qty'])],
            ['label' => 'Sale', 'value' => '£'.number_format($normalized['revenue'], 2)],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function funnelSummaryMetricsFromRow(?array $row): array
    {
        if ($row === null) {
            return [];
        }

        return self::formatFunnelSummaryMetrics(self::normalizeAcquisitionRow($row));
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function categoryPerformanceSummaryMetrics(
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        if ($request === null || $from === null || $to === null || ! $request->filled('category')) {
            return [];
        }

        $categories = TrackerMultiSelectFilter::requestValues($request, 'category');

        if ($categories === []) {
            return [];
        }

        $filters = array_merge(
            self::sessionFiltersFromRequest($request),
            self::productCatalogFiltersFromRequest($request),
        );
        $catalogOptions = self::productCatalogFiltersFromRequest($request);
        $dashboard = app(EcomTrackerDashboardService::class);
        $departmentName = self::resolvedCategoryDepartment($request, $from, $to, $period)
            ?? TrackerCategoryIdentity::normalizeDepartmentName((string) $request->input('department', ''));

        $row = [
            'views' => 0,
            'adds' => 0,
            'begin_checkouts' => 0,
            'proceed_checkouts' => 0,
            'purchases' => 0,
            'sale_items' => 0,
            'sale_amount' => 0.0,
        ];
        $matchedCategories = 0;

        foreach ($categories as $categoryName) {
            $categoryRow = $dashboard->categoryPerformanceForName(
                $from,
                $to,
                $categoryName,
                $filters,
                $period,
                $departmentName !== '' ? $departmentName : null,
            );

            if ($categoryRow === null) {
                continue;
            }

            $matchedCategories++;
            $row['views'] += (int) ($categoryRow['views'] ?? 0);
            $row['adds'] += (int) ($categoryRow['adds'] ?? $categoryRow['add_to_cart'] ?? 0);
            $row['begin_checkouts'] += (int) ($categoryRow['begin_checkouts'] ?? $categoryRow['begin_checkout'] ?? 0);
            $row['proceed_checkouts'] += (int) ($categoryRow['proceed_checkouts'] ?? $categoryRow['proceed_checkout'] ?? 0);
        }

        if ($matchedCategories === 0) {
            return [];
        }

        $sessionIds = $dashboard->productCatalogSessionIds($from, $to, $filters, $period);
        $commerceTotals = $dashboard->categoryCatalogCommerceTotalsForSessions(
            $sessionIds,
            $from,
            $to,
            $catalogOptions,
        );

        $row['sale_amount'] = $commerceTotals['revenue'];
        $row['sale_items'] = $commerceTotals['qty'];
        $row['purchases'] = $commerceTotals['purchases'];

        return self::funnelSummaryMetricsFromRow($row);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function productPerformanceSummaryMetrics(
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        if ($request === null || $from === null || $to === null) {
            return [];
        }

        if (! self::hasProductPerformanceSummaryScope($request)) {
            return [];
        }

        $row = app(EcomTrackerDashboardService::class)->productPerformanceSummaryForFilters(
            $from,
            $to,
            self::activitySummaryFiltersFromRequest($request),
            $period,
        );

        if ($row === null) {
            return [];
        }

        return self::funnelSummaryMetricsFromRow($row);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function devicePerformanceSummaryMetrics(
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        if ($request === null || $from === null || $to === null || ! $request->filled('device_type')) {
            return [];
        }

        $row = app(EcomTrackerDashboardService::class)->devicePerformanceSummaryForFilters(
            $from,
            $to,
            self::sessionFiltersFromRequest($request),
            $period,
        );

        return self::funnelSummaryMetricsFromRow($row);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function trafficSourceSummaryMetrics(
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        if ($request === null || $from === null || $to === null || ! $request->filled('utm_source')) {
            return [];
        }

        $row = app(EcomTrackerDashboardService::class)->trafficSourceSummaryForFilters(
            $from,
            $to,
            self::sessionFiltersFromRequest($request),
            $period,
        );

        return self::funnelSummaryMetricsFromRow($row);
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<int, array{label: string, value: string}>
     */
    private static function conversionSummaryMetrics(
        int $sessionCount,
        array $funnelMetrics,
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        return self::saleTotalsSummaryMetrics(self::paymentSaleTotalsFromFunnelMetrics($funnelMetrics));
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<int, array{label: string, value: string}>
     */
    private static function paymentSuccessSummaryMetrics(int $sessionCount, array $funnelMetrics): array
    {
        $totals = self::paymentSaleTotalsFromFunnelMetrics($funnelMetrics);

        return array_merge(
            [['label' => 'Orders', 'value' => number_format($sessionCount)]],
            self::saleTotalsSummaryMetrics($totals),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array{qty: int, revenue: float}
     */
    private static function paymentSaleTotalsFromFunnelMetrics(array $funnelMetrics): array
    {
        return [
            'qty' => (int) collect($funnelMetrics)->sum(fn (array $row) => (int) ($row['qty'] ?? 0)),
            'revenue' => round(collect($funnelMetrics)->sum(fn (array $row) => (float) ($row['value'] ?? 0)), 2),
        ];
    }

    /**
     * @param  array{qty: int, revenue: float}  $totals
     * @return array<int, array{label: string, value: string}>
     */
    private static function saleTotalsSummaryMetrics(array $totals): array
    {
        return [
            ['label' => 'Sold qty', 'value' => number_format((int) ($totals['qty'] ?? 0))],
            ['label' => 'Sale', 'value' => '£'.number_format((float) ($totals['revenue'] ?? 0), 2)],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @return array<int, array{label: string, value: string}>
     */
    private static function abandonmentSummaryExtras(array $funnelMetrics): array
    {
        $itemsQty = (int) collect($funnelMetrics)->sum(fn (array $row) => (int) ($row['qty'] ?? 0));

        return [
            ['label' => 'Items in cart', 'value' => number_format($itemsQty)],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function audienceSummaryMetrics(
        ?Request $request,
        ?Carbon $from,
        ?Carbon $to,
        ?string $period,
    ): array {
        if ($request === null || $from === null || $to === null) {
            return [];
        }

        $dashboard = app(EcomTrackerDashboardService::class);
        $filters = self::activitySummaryFiltersFromRequest($request);
        $metrics = [];

        $funnelRow = $dashboard->activityFunnelSummaryForFilters($from, $to, $filters, $period);

        if ($funnelRow !== null) {
            $metrics = self::funnelSummaryMetricsFromRow($funnelRow);
        }

        $summary = $dashboard->audienceSummaryForFilters(
            $from,
            $to,
            $filters,
            $period,
        );

        if ($summary === null) {
            return $metrics;
        }

        $avgStayLabel = app(VisitorAnalyticsService::class)->formatDuration((int) ($summary['avg_stay_seconds'] ?? 0));

        return array_merge($metrics, [
            ['label' => 'Unique visitors', 'value' => number_format((int) ($summary['unique_visitors'] ?? 0))],
            ['label' => 'Avg stay', 'value' => $avgStayLabel],
        ]);
    }

    private static function hasProductPerformanceSummaryScope(Request $request): bool
    {
        if ($request->filled('product_code') || $request->filled('product_name') || $request->filled('search')) {
            return true;
        }

        return self::activitySummaryCatalogFiltersFromRequest($request) !== [];
    }
}
