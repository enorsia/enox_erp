<?php

namespace App\Support;

use App\Models\TrackerUtmFilter;
use Illuminate\Http\Request;

final class EcomTrackerViewData
{
    /**
     * @return array<int, string>
     */
    public static function dashboardQueryKeys(): array
    {
        return [
            'period', 'date_from', 'date_to', 'device_type', 'logged_in', 'has_order', 'country', 'visitor_type',
            'utm_source', 'utm_medium', 'search', 'category', 'color', 'size', 'sort_by', 'activity',
            'has_purchases', 'has_views', 'has_adds', 'event_scenario',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboardQueryParams(Request $request): array
    {
        return $request->only(self::dashboardQueryKeys());
    }

    /**
     * @return array<string, mixed>
     */
    public static function forDashboard(Request $request, array $filters, int $activeFilterCount): array
    {
        $period = $filters['period'] ?? '24h';
        $queryParams = self::dashboardQueryParams($request);
        $exportQuery = array_filter(array_merge($queryParams, ['period' => $period]), fn ($value) => filled($value));
        $back = $request->fullUrl();

        return [
            'period' => $period,
            'queryParams' => $queryParams,
            'exportUrl' => route('admin.ecom-tracker.dashboard.export', $exportQuery),
            'detailLink' => fn (string $section) => self::activityDrillDownLink(
                EcomActivityFocus::fromSection($section) ?? 'audience',
                array_merge($filters, $queryParams),
                self::dashboardSectionDrillExtras($section),
                $back,
            ),
            'activityFocusLink' => fn (string $focus, array $extra = []) => self::activityDrillDownLink(
                $focus,
                array_merge($filters, $queryParams),
                $extra,
                $back,
            ),
            'activitySourceLink' => fn (string $source) => self::activitySourceLink(
                array_merge($filters, $queryParams),
                $source,
                $back,
            ),
            'hasActiveFilters' => $activeFilterCount > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function activityShowParams(string $sessionId, ?string $back = null): array
    {
        $params = ['session' => $sessionId];

        if (filled($back)) {
            $params['back'] = $back;
        } elseif (request()->filled('back')) {
            $params['back'] = request()->input('back');
        } else {
            $params['back'] = request()->fullUrl();
        }

        return $params;
    }

    /**
     * @return array<int, string>
     */
    public static function activityQueryKeys(): array
    {
        return [
            'period', 'date_from', 'date_to', 'focus', 'back', 'funnel',
            'device_type', 'logged_in', 'has_order', 'country', 'visitor_type',
            'utm_source', 'utm_medium', 'duration_bucket', 'search', 'category', 'department', 'color', 'size',
            'product_code', 'product_name', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario',
            'sort_by', 'sort_dir',
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboardFilters
     * @param  array<string, mixed>  $extra
     */
    public static function activityDrillDownLink(
        string $focus,
        array $dashboardFilters,
        array $extra = [],
        ?string $back = null,
    ): string {
        $query = array_merge(
            self::activityIndexQueryFromFilters($dashboardFilters),
            EcomActivityFocus::implicitQueryParams($focus),
            array_filter(['focus' => $focus], fn ($value) => filled($value)),
            array_filter($extra, fn ($value) => filled($value)),
        );

        if (filled($back)) {
            $query['back'] = $back;
        }

        return route('admin.ecom-activity.index', $query);
    }

    /**
     * @return array<string, mixed>
     */
    private static function dashboardSectionDrillExtras(string $section): array
    {
        return match ($section) {
            'products', 'colors' => array_filter([
                'search' => request('search'),
                'category' => request('category'),
                'color' => request('color'),
                'size' => request('size'),
                'activity' => request('activity'),
                'has_purchases' => request('has_purchases'),
                'has_views' => request('has_views'),
                'has_adds' => request('has_adds'),
                'event_scenario' => request('event_scenario'),
            ], fn ($value) => filled($value)),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function activityIndexQueryFromRequest(Request $request): array
    {
        return array_filter(
            $request->only(self::activityQueryKeys()),
            fn ($value) => filled($value),
        );
    }

    public static function activityShowUrl(string $sessionId, ?string $back = null): string
    {
        return route('admin.ecom-activity.show', self::activityShowParams($sessionId, $back));
    }

    /**
     * Build show URL preserving current list filters for back navigation.
     */
    public static function activityShowUrlFromRequest(Request $request, string $sessionId): string
    {
        return self::activityShowUrl($sessionId, $request->fullUrl());
    }

    /**
     * @return array<int, string>
     */
    public static function sharedNavigationQueryKeys(): array
    {
        return [
            'period', 'date_from', 'date_to',
            'device_type', 'logged_in', 'has_order', 'country', 'visitor_type',
            'utm_source', 'utm_medium',
        ];
    }

    /**
     * Period and session filters shared when switching between dashboard and user activity.
     *
     * @return array<string, mixed>
     */
    public static function sharedNavigationQuery(Request $request): array
    {
        return self::activityIndexQueryFromFilters(
            array_merge(
                $request->only(self::sharedNavigationQueryKeys()),
                ['period' => $request->input('period', '24h')],
            ),
        );
    }

    public static function dashboardShortcutUrl(Request $request): string
    {
        return route('admin.ecom-tracker.dashboard', self::sharedNavigationQuery($request));
    }

    public static function activityShortcutUrl(Request $request): string
    {
        return route('admin.ecom-activity.index', self::sharedNavigationQuery($request));
    }

    /**
     * Dashboard return URL for activity drill-downs.
     *
     * Prefer the explicit `back` query param from store-performance links.
     * When a dashboard section focus is present without `back` (legacy traffic
     * source links), fall back to the dashboard with the current date range.
     */
    public static function activityIndexBackUrl(Request $request): ?string
    {
        $explicit = self::resolveBackUrl($request->input('back'));

        if ($explicit !== null) {
            return $explicit;
        }

        if (! EcomActivityFocus::isValid($request->input('focus'))) {
            return null;
        }

        return route('admin.ecom-tracker.dashboard', self::sharedNavigationQuery($request));
    }

    /**
     * Decode back URLs from query params (handles legacy double-encoded values).
     */
    public static function resolveBackUrl(?string $back, ?string $fallback = null): ?string
    {
        if (! filled($back)) {
            return $fallback;
        }

        $decoded = (string) $back;

        for ($i = 0; $i < 3 && str_contains($decoded, '%'); $i++) {
            $next = urldecode($decoded);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $baseQuery
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $range
     * @return array{previous_url: string, next_url: ?string, can_go_next: bool}
     */
    public static function dashboardDayNavigation(
        array $baseQuery,
        array $range,
        string $routeName = 'admin.ecom-tracker.dashboard',
        string $periodKey = 'period',
        string $dateFromKey = 'date_from',
        string $dateToKey = 'date_to',
    ): array {
        $fromLocal = TrackerTime::toLocal($range['from']);
        $toLocal = TrackerTime::toLocal($range['to']);

        if ($fromLocal === null || $toLocal === null) {
            return [
                'previous_url' => route($routeName, array_merge($baseQuery, [$periodKey => '24h'])),
                'next_url' => null,
                'can_go_next' => false,
            ];
        }

        $today = TrackerTime::localNow()->startOfDay();
        $canGoNext = $toLocal->copy()->startOfDay()->lt($today);

        return [
            'previous_url' => self::dashboardPeriodUrl(
                $baseQuery,
                $fromLocal->copy()->subDay(),
                $toLocal->copy()->subDay(),
                $routeName,
                $periodKey,
                $dateFromKey,
                $dateToKey,
            ),
            'next_url' => $canGoNext
                ? self::dashboardPeriodUrl(
                    $baseQuery,
                    $fromLocal->copy()->addDay(),
                    $toLocal->copy()->addDay(),
                    $routeName,
                    $periodKey,
                    $dateFromKey,
                    $dateToKey,
                )
                : null,
            'can_go_next' => $canGoNext,
        ];
    }

    /**
     * @param  array<string, mixed>  $baseQuery
     */
    private static function dashboardPeriodUrl(
        array $baseQuery,
        \Carbon\Carbon $fromLocal,
        \Carbon\Carbon $toLocal,
        string $routeName,
        string $periodKey = 'period',
        string $dateFromKey = 'date_from',
        string $dateToKey = 'date_to',
    ): string {
        $today = TrackerTime::localNow()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $query = $baseQuery;
        unset($query[$dateFromKey], $query[$dateToKey]);

        if ($fromLocal->isSameDay($toLocal)) {
            if ($fromLocal->isSameDay($today)) {
                return route($routeName, array_merge($query, [$periodKey => '24h']));
            }

            if ($fromLocal->isSameDay($yesterday)) {
                return route($routeName, array_merge($query, [$periodKey => 'yesterday']));
            }
        }

        return route($routeName, array_merge($query, [
            $periodKey => 'custom',
            $dateFromKey => $fromLocal->toDateString(),
            $dateToKey => $toLocal->toDateString(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public static function activityIndexQueryFromFilters(array $filters, ?string $utmSource = null): array
    {
        $query = array_filter([
            'period' => $filters['period'] ?? null,
            'device_type' => $filters['device_type'] ?? null,
            'logged_in' => $filters['logged_in'] ?? null,
            'has_order' => $filters['has_order'] ?? null,
            'country' => $filters['country'] ?? null,
            'visitor_type' => $filters['visitor_type'] ?? null,
            'utm_medium' => $filters['utm_medium'] ?? null,
            'search' => $filters['search'] ?? null,
            'category' => $filters['category'] ?? null,
            'color' => $filters['color'] ?? null,
            'size' => $filters['size'] ?? null,
            'activity' => $filters['activity'] ?? null,
            'has_purchases' => $filters['has_purchases'] ?? null,
            'has_views' => $filters['has_views'] ?? null,
            'has_adds' => $filters['has_adds'] ?? null,
            'event_scenario' => $filters['event_scenario'] ?? null,
        ], fn ($value) => filled($value));

        if ($utmSource !== null && $utmSource !== '' && $utmSource !== 'Other') {
            $resolved = $utmSource === '(direct)'
                ? '(direct)'
                : (SessionTrafficAttribution::normalizeSource($utmSource) ?? $utmSource);

            if ($resolved !== '') {
                $query['utm_source'] = $resolved;
            }
        } elseif (filled($filters['utm_source'] ?? null)) {
            $query['utm_source'] = $filters['utm_source'];
        }

        $period = $filters['period'] ?? '24h';

        if ($period === 'custom' && filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)) {
            $query['date_from'] = (string) $filters['date_from'];
            $query['date_to'] = (string) $filters['date_to'];
            $query['period'] = 'custom';

            return $query;
        }

        $today = TrackerTime::localNow()->copy()->startOfDay();
        $todayStr = $today->toDateString();

        if ($period === 'yesterday') {
            $yesterday = $today->copy()->subDay();

            return array_merge($query, [
                'period' => 'yesterday',
                'date_from' => $yesterday->toDateString(),
                'date_to' => $yesterday->toDateString(),
            ]);
        }

        if ($period === '7d') {
            return array_merge($query, [
                'period' => '7d',
                'date_from' => $today->copy()->subDays(6)->toDateString(),
                'date_to' => $todayStr,
            ]);
        }

        if (in_array($period, ['30d', '90d'], true)) {
            $days = $period === '90d' ? 89 : 29;

            return array_merge($query, [
                'period' => $period,
                'date_from' => $today->copy()->subDays($days)->toDateString(),
                'date_to' => $todayStr,
            ]);
        }

        if ($period === '24h') {
            $query['period'] = '24h';
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function activitySourceLink(array $filters, string $source, ?string $back = null): string
    {
        if ($source === '' || $source === 'Other') {
            return '';
        }

        $resolved = $source === '(direct)'
            ? '(direct)'
            : (SessionTrafficAttribution::normalizeSource($source) ?? $source);

        return self::activityDrillDownLink(
            'traffic',
            array_merge($filters, ['utm_source' => $resolved]),
            [],
            $back,
        );
    }

    /**
     * @return list<string>
     */
    public static function compareSideFilterKeys(): array
    {
        return array_merge(
            ['period', 'date_from', 'date_to', 'sort_by'],
            self::sharedNavigationQueryKeys(),
        );
    }

    public static function compareSidePrefix(string $side): string
    {
        return $side === 'right' ? 'right_' : 'left_';
    }

    public static function compareShortcutUrl(Request $request): string
    {
        $leftFilters = array_merge(
            ['period' => $request->input('period', '24h')],
            $request->only(array_merge(
                ['date_from', 'date_to'],
                self::sharedNavigationQueryKeys(),
            )),
        );

        $query = self::compareSideQuery('left', $leftFilters);
        $query['back'] = $request->fullUrl();

        return route('admin.ecom-tracker.dashboard.compare', $query);
    }

    public static function compareBackUrl(Request $request): string
    {
        $explicit = self::resolveBackUrl($request->input('back'));

        return $explicit ?? route('admin.ecom-tracker.dashboard');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function compareSideQuery(string $side, array $filters): array
    {
        $prefix = self::compareSidePrefix($side);
        $query = [];

        foreach (self::compareSideFilterKeys() as $key) {
            $value = $filters[$key] ?? null;

            if (filled($value)) {
                $query["{$prefix}{$key}"] = $value;
            }
        }

        if (($filters['period'] ?? '24h') === '24h' && $side === 'left' && ! array_key_exists("{$prefix}period", $query)) {
            $query["{$prefix}period"] = '24h';
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public static function compareSideFiltersFromRequest(Request $request, string $side): array
    {
        $prefix = self::compareSidePrefix($side);
        $period = $request->input("{$prefix}period");

        $filters = [
            'period' => filled($period) ? (string) $period : '24h',
            'date_from' => $request->input("{$prefix}date_from"),
            'date_to' => $request->input("{$prefix}date_to"),
            'sort_by' => $request->input("{$prefix}sort_by"),
        ];

        foreach (self::sharedNavigationQueryKeys() as $key) {
            if ($key === 'period' || $key === 'date_from' || $key === 'date_to') {
                continue;
            }

            $filters[$key] = $request->input("{$prefix}{$key}");
        }

        return array_filter(
            $filters,
            fn ($value, string $key) => $key === 'period' || filled($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, mixed>  $leftFilters
     * @param  array<string, mixed>  $rightFilters
     * @return array<string, mixed>
     */
    public static function comparePageQuery(Request $request, array $leftFilters, array $rightFilters): array
    {
        $query = array_merge(
            self::compareSideQuery('left', $leftFilters),
            self::compareSideQuery('right', $rightFilters),
        );

        if ($request->filled('back')) {
            $query['back'] = $request->input('back');
        }

        unset($query['period'], $query['date_from'], $query['date_to']);

        return $query;
    }

    public static function hasCompareSideParams(Request $request, string $side): bool
    {
        $prefix = self::compareSidePrefix($side);

        return $request->filled("{$prefix}period")
            || $request->filled("{$prefix}date_from")
            || $request->filled("{$prefix}date_to");
    }
}
