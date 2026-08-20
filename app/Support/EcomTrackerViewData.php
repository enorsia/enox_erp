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
            'activitySourceLink' => fn (string $source) => self::activitySourceLink($filters, $source),
            'hasActiveFilters' => $activeFilterCount > 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function visitorQueryKeys(): array
    {
        return ['window', 'datetime_from', 'datetime_to'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forVisitors(Request $request, array $filters): array
    {
        $window = $filters['window'] ?? '24h';
        $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
        $datetimeFromValue = filled($filters['datetime_from'] ?? null)
            ? TrackerTime::toLocal($filters['datetime_from'])?->format('Y-m-d\TH:i')
            : '';
        $datetimeToValue = filled($filters['datetime_to'] ?? null)
            ? TrackerTime::toLocal($filters['datetime_to'])?->format('Y-m-d\TH:i')
            : '';
        $activeFilterCount = $hasCustomRange ? 0 : (($request->has('window') && ! in_array($window, ['24h', '7d', '30d', '90d'], true)) ? 1 : 0);
        $exportQuery = array_filter($request->only(self::visitorQueryKeys()), fn ($value) => filled($value));
        $back = $request->fullUrl();

        return [
            'window' => $window,
            'activeWindow' => $hasCustomRange ? 'custom' : $window,
            'hasCustomRange' => $hasCustomRange,
            'datetimeFromValue' => $datetimeFromValue,
            'datetimeToValue' => $datetimeToValue,
            'activeFilterCount' => $activeFilterCount,
            'rangeLabel' => $filters['window_label'] ?? TrackerTime::todayPresetLabel(),
            'resetUrl' => route('admin.ecom-tracker.visitors'),
            'resetActive' => count($request->query()) > 0,
            'presetWindows' => [
                '3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => TrackerTime::todayPresetButtonLabel(),
                '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '1y' => '1 year',
            ],
            'exportUrl' => route('admin.ecom-tracker.visitors.export', $exportQuery),
            'detailLink' => fn (string $section) => route('admin.ecom-tracker.visitors.details', $section).'?'.http_build_query(array_merge($request->only(self::visitorQueryKeys()), ['back' => $back])),
            'activityLink' => fn (string $visitorId) => route('admin.ecom-activity.index', ['search' => $visitorId]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forVisitorDetail(Request $request, array $filters, string $title, string $section, int $activeFilterCount): array
    {
        $window = $filters['window'] ?? '24h';
        $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
        $datetimeFromValue = filled($filters['datetime_from'] ?? null)
            ? TrackerTime::toLocal($filters['datetime_from'])?->format('Y-m-d\TH:i')
            : '';
        $datetimeToValue = filled($filters['datetime_to'] ?? null)
            ? TrackerTime::toLocal($filters['datetime_to'])?->format('Y-m-d\TH:i')
            : '';
        $queryParams = $request->only(array_merge(self::visitorQueryKeys(), [
            'search', 'device_type', 'logged_in', 'has_order', 'utm_source', 'utm_medium', 'sort_by',
        ]));
        $visitorsBack = EcomTrackerViewData::resolveBackUrl(
            $request->input('back'),
            route('admin.ecom-tracker.visitors', $queryParams),
        );
        $exportQuery = array_filter($request->only(self::visitorQueryKeys()), fn ($value) => filled($value));
        $resetQuery = array_filter([
            'section' => $section,
            'back' => $request->input('back'),
        ], fn ($value) => filled($value));

        return [
            'window' => $window,
            'activeWindow' => $hasCustomRange ? 'custom' : $window,
            'hasCustomRange' => $hasCustomRange,
            'datetimeFromValue' => $datetimeFromValue,
            'datetimeToValue' => $datetimeToValue,
            'activeFilterCount' => $activeFilterCount,
            'rangeLabel' => $filters['window_label'] ?? TrackerTime::todayPresetLabel(),
            'exportUrl' => route('admin.ecom-tracker.visitors.export', $exportQuery),
            'resetUrl' => route('admin.ecom-tracker.visitors.details', $resetQuery),
            'resetActive' => count($request->query()) > 0,
            'presetWindows' => [
                '3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => TrackerTime::todayPresetButtonLabel(),
                '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '1y' => '1 year',
            ],
            'breadcrumbs' => [
                ['label' => 'Visitor analytics', 'url' => $visitorsBack],
                ['label' => $title],
            ],
            'backUrl' => $visitorsBack,
            'activityLink' => fn (string $visitorId) => route('admin.ecom-activity.index', ['search' => $visitorId]),
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
            'period', 'date_from', 'date_to', 'focus', 'back',
            'device_type', 'logged_in', 'has_order', 'country', 'visitor_type',
            'utm_source', 'utm_medium', 'search', 'category', 'color', 'size',
            'product_code', 'product_name', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario',
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
    public static function dashboardDayNavigation(array $baseQuery, array $range, string $routeName = 'admin.ecom-tracker.dashboard'): array
    {
        $fromLocal = TrackerTime::toLocal($range['from']);
        $toLocal = TrackerTime::toLocal($range['to']);

        if ($fromLocal === null || $toLocal === null) {
            return [
                'previous_url' => route($routeName, array_merge($baseQuery, ['period' => '24h'])),
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
            ),
            'next_url' => $canGoNext
                ? self::dashboardPeriodUrl(
                    $baseQuery,
                    $fromLocal->copy()->addDay(),
                    $toLocal->copy()->addDay(),
                    $routeName,
                )
                : null,
            'can_go_next' => $canGoNext,
        ];
    }

    /**
     * @param  array<string, mixed>  $baseQuery
     */
    private static function dashboardPeriodUrl(array $baseQuery, \Carbon\Carbon $fromLocal, \Carbon\Carbon $toLocal, string $routeName): string
    {
        $today = TrackerTime::localNow()->startOfDay();
        $yesterday = $today->copy()->subDay();

        if ($fromLocal->isSameDay($toLocal)) {
            if ($fromLocal->isSameDay($today)) {
                return route($routeName, array_merge($baseQuery, ['period' => '24h']));
            }

            if ($fromLocal->isSameDay($yesterday)) {
                return route($routeName, array_merge($baseQuery, ['period' => 'yesterday']));
            }
        }

        return route($routeName, array_merge($baseQuery, [
            'period' => 'custom',
            'date_from' => $fromLocal->toDateString(),
            'date_to' => $toLocal->toDateString(),
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
            $query['utm_source'] = (string) $filters['utm_source'];
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
    public static function activitySourceLink(array $filters, string $source): string
    {
        if ($source === '' || $source === 'Other') {
            return '';
        }

        $resolved = $source === '(direct)'
            ? '(direct)'
            : (SessionTrafficAttribution::normalizeSource($source) ?? $source);

        return self::activityDrillDownLink('traffic', array_merge($filters, ['utm_source' => $resolved]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function forBotTraffic(Request $request, int $activeFilterCount): array
    {
        $queryParams = array_filter(
            $request->only(['search', 'device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium', 'period', 'date_from', 'date_to']),
            fn ($value) => filled($value),
        );

        return [
            'activityLink' => route('admin.ecom-activity.index', array_merge($queryParams, ['visitor_type' => 'bot'])),
            'hasActiveFilters' => $activeFilterCount > 0,
        ];
    }
}
