<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Services\EcomActivityFunnelSessions;
use App\Services\EcomTrackerDashboardService;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class EcomActivityFocus
{
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
            'sort' => 'value_desc',
            'implicit' => ['has_order' => '1'],
            'payment_success' => true,
        ],
        'cart_abandonment' => [
            'label' => 'Cart abandoned',
            'empty' => 'No abandoned carts in this period — nice.',
            'columns' => [],
            'sort' => 'value_desc',
            'funnel' => ['stage' => 'add_to_cart', 'payload' => 'add_to_cart', 'exclude' => 'begin_checkout'],
        ],
        'begin_checkout_abandonment' => [
            'label' => 'Begin checkout abandoned',
            'empty' => 'No begin checkout abandonment in this period.',
            'columns' => [],
            'sort' => 'value_desc',
            'funnel' => ['stage' => 'begin_checkout', 'payload' => 'begin_checkout', 'exclude' => 'proceed_checkout'],
        ],
        'proceed_checkout_abandonment' => [
            'label' => 'Proceed checkout abandoned',
            'empty' => 'No proceed checkout abandonment in this period.',
            'columns' => [],
            'sort' => 'value_desc',
            'funnel' => ['stage' => 'proceed_checkout', 'payload' => 'proceed_to_checkout', 'exclude' => 'payment_success'],
        ],
        'payment_success' => [
            'label' => 'Payment success',
            'empty' => 'No payment success events in this period.',
            'columns' => [],
            'sort' => 'value_desc',
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
    public static function tableColumns(?string $focus): array
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
            'purchases' => ['key' => 'purchases', 'label' => 'Purchases', 'class' => 'etd-num'],
            'device_detail' => ['key' => 'device_detail', 'label' => 'Device & browser'],
            'traffic_source' => ['key' => 'traffic_source', 'label' => 'Source'],
            'traffic_medium' => ['key' => 'traffic_medium', 'label' => 'Medium'],
            'classification_reason' => ['key' => 'classification_reason', 'label' => 'Classification'],
        ];

        $focusKeys = self::definition($focus)['columns'] ?? [];

        return array_values(array_filter(array_map(
            fn (string $key) => $defs[$key] ?? null,
            $focusKeys,
        )));
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

    public static function applyFocusFilter(
        Builder $query,
        ?string $focus,
        Carbon $from,
        Carbon $to,
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        EcomActivityFunnelSessions $funnelSessions,
    ): void {
        if (! self::isValid($focus)) {
            return;
        }

        $definition = self::definition($focus);
        $period = $request->input('period', '24h');
        $sessionFilters = self::sessionFiltersFromRequest($request);

        if (! empty($definition['funnel']) || ! empty($definition['payment_success'])) {
            $context = self::resolveFunnelContext($focus, $from, $to, $sessionFilters, $period, $funnelSessions);
            $ids = $context['session_ids'];

            if ($ids->isEmpty()) {
                $query->whereRaw('1 = 0');

                return;
            }

            self::constrainToSessionIds($query, $ids);

            return;
        }

        if (! empty($definition['action_types'])) {
            $types = $definition['action_types'];
            $query->whereHas('actions', fn (Builder $actions) => $actions
                ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
                ->whereIn('action_type', $types));

            if ($focus === 'products' || $focus === 'categories') {
                self::applyProductCatalogConstraints($query, $from, $to, $request, $dashboardService, $period);
            }

            return;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionFiltersFromRequest(Request $request): array
    {
        return array_filter([
            'device_type' => $request->input('device_type'),
            'logged_in' => $request->input('logged_in'),
            'has_order' => $request->input('has_order'),
            'country' => $request->input('country'),
            'visitor_type' => $request->input('visitor_type'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
        ], fn ($value) => filled($value));
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

    public static function productCatalogFiltersFromRequest(Request $request): array
    {
        return array_filter([
            'search' => $request->input('search'),
            'product_code' => $request->input('product_code'),
            'product_name' => $request->input('product_name'),
            'category' => $request->input('category'),
            'color' => $request->input('color'),
            'size' => $request->input('size'),
            'activity' => $request->input('activity'),
            'has_purchases' => $request->input('has_purchases'),
            'has_views' => $request->input('has_views'),
            'has_adds' => $request->input('has_adds'),
            'event_scenario' => $request->input('event_scenario'),
        ], fn ($value) => filled($value));
    }

    private static function applyProductCatalogConstraints(
        Builder $query,
        Carbon $from,
        Carbon $to,
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        ?string $period,
    ): void {
        $productFilters = array_merge(
            self::sessionFiltersFromRequest($request),
            self::productCatalogFiltersFromRequest($request),
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

        if (count($ids) <= 1000) {
            $query->whereIn('session_id', $ids);

            return;
        }

        $query->where(function (Builder $inner) use ($ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $inner->orWhereIn('session_id', $chunk);
            }
        });
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
    ): ?array {
        if (! self::isValid($focus)) {
            return null;
        }

        $criteria = self::filterCriteriaFromRequest($request);
        $metrics = self::summaryForFocus($focus, $sessionCount, $funnelMetrics);

        return [
            'section' => self::label($focus),
            'description' => self::drillDownDescription($focus),
            'range_label' => $rangeLabel,
            'criteria' => $criteria,
            'metrics' => $metrics,
            'clear_focus_url' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
        ];
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

        $add = static function (string $label, mixed $value) use (&$criteria): void {
            if (filled($value)) {
                $criteria[] = ['label' => $label, 'value' => (string) $value];
            }
        };

        $add('Product code', $request->input('product_code'));
        $add('Product', $request->input('product_name'));

        if ($request->filled('search') && ! $request->filled('product_code') && ! $request->filled('product_name')) {
            $add('Product search', '"'.$request->search.'"');
        }

        $add('Category', $request->input('category'));
        $add('Color', $request->input('color'));
        $add('Size', $request->input('size'));

        if ($request->filled('device_type')) {
            $add('Device', ucfirst((string) $request->device_type));
        }

        if ($request->filled('logged_in')) {
            $add('Login', $request->logged_in === '1' ? 'Logged in' : 'Guest');
        }

        if ($request->filled('has_order')) {
            $add('Orders', $request->has_order === '1' ? 'With order' : 'No order');
        }

        if ($request->filled('visitor_type')) {
            $add('Visitor type', $visitorLabels[$request->visitor_type] ?? (string) $request->visitor_type);
        }

        $add('Country', $request->input('country'));

        if ($request->filled('utm_source')) {
            $sourceLabel = TrackerUtmFilter::sources()[$request->utm_source] ?? $request->utm_source;
            $add('Source', $sourceLabel);
        }

        if ($request->filled('utm_medium')) {
            $mediumLabel = TrackerUtmFilter::mediums()[$request->utm_medium] ?? $request->utm_medium;
            $add('Medium', $mediumLabel);
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
     * @return array<int, array{label: string, remove_url: string}>
     */
    public static function filterChipsFromRequest(Request $request): array
    {
        $chips = [];

        if ($request->filled('focus') && self::isValid($request->input('focus'))) {
            $chips[] = [
                'label' => 'Section: '.self::label($request->input('focus')),
                'remove_url' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
            ];
        }

        foreach (self::filterCriteriaFromRequest($request) as $criterion) {
            $key = match ($criterion['label']) {
                'Product code' => 'product_code',
                'Product' => 'product_name',
                'Product search' => 'search',
                'Category' => 'category',
                'Color' => 'color',
                'Size' => 'size',
                'Device' => 'device_type',
                'Login' => 'logged_in',
                'Orders' => 'has_order',
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

            if ($key === null) {
                continue;
            }

            $chips[] = [
                'label' => $criterion['label'].': '.$criterion['value'],
                'remove_url' => $request->fullUrlWithQuery([$key => null, 'page' => null]),
            ];
        }

        return $chips;
    }

    private static function drillDownDescription(?string $focus): ?string
    {
        return match ($focus) {
            'cart_abandonment' => 'Sessions that added to cart but did not begin checkout.',
            'begin_checkout_abandonment' => 'Sessions that began checkout but did not proceed.',
            'proceed_checkout_abandonment' => 'Sessions that proceeded to checkout but did not complete payment.',
            'payment_success' => 'Sessions with a completed payment in this period.',
            'conversion' => 'Sessions with a completed order in this period.',
            'products' => 'Sessions with product views, cart, or purchase activity matching the filters below.',
            'categories' => 'Sessions with category or product activity in the selected category.',
            'devices' => 'Sessions on the selected device type from the dashboard.',
            'traffic' => 'Sessions from the selected traffic source or medium.',
            'session_quality' => 'Sessions matching the selected visitor classification.',
            'audience' => 'All sessions in the selected date range.',
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
    ): array {
        if (! self::isValid($focus)) {
            return [];
        }

        $atStake = round(collect($funnelMetrics)->sum(fn (array $row) => (float) ($row['value'] ?? 0)), 2);

        return match ($focus) {
            'cart_abandonment', 'begin_checkout_abandonment', 'proceed_checkout_abandonment' => [
                ['label' => 'Matching sessions', 'value' => $sessionCount],
                ['label' => 'At stake', 'value' => '£'.number_format($atStake, 2)],
            ],
            'payment_success', 'conversion' => [
                ['label' => 'Matching sessions', 'value' => $sessionCount],
                ['label' => 'Revenue', 'value' => '£'.number_format($atStake, 2)],
            ],
            default => [
                ['label' => 'Matching sessions', 'value' => $sessionCount],
            ],
        };
    }
}
