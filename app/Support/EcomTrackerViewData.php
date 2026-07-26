<?php

namespace App\Support;

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
        $back = urlencode($request->fullUrl());

        return [
            'period' => $period,
            'queryParams' => $queryParams,
            'exportUrl' => route('admin.ecom-tracker.dashboard.export', $exportQuery),
            'detailLink' => fn (string $section) => route('admin.ecom-tracker.dashboard.details', $section).'?'.http_build_query(array_merge($queryParams, ['back' => $back])),
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
        $back = urlencode($request->fullUrl());

        return [
            'window' => $window,
            'activeWindow' => $hasCustomRange ? 'custom' : $window,
            'hasCustomRange' => $hasCustomRange,
            'datetimeFromValue' => $datetimeFromValue,
            'datetimeToValue' => $datetimeToValue,
            'activeFilterCount' => $activeFilterCount,
            'rangeLabel' => $filters['window_label'] ?? 'Last 24 hours',
            'resetUrl' => route('admin.ecom-tracker.visitors'),
            'resetActive' => count($request->query()) > 0,
            'presetWindows' => [
                '3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours',
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
        $visitorsBack = $request->filled('back')
            ? urldecode((string) $request->input('back'))
            : route('admin.ecom-tracker.visitors', $queryParams);
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
            'rangeLabel' => $filters['window_label'] ?? 'Last 24 hours',
            'exportUrl' => route('admin.ecom-tracker.visitors.export', $exportQuery),
            'resetUrl' => route('admin.ecom-tracker.visitors.details', $resetQuery),
            'resetActive' => count($request->query()) > 0,
            'presetWindows' => [
                '3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours',
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
            $params['back'] = urlencode(request()->fullUrl());
        }

        return $params;
    }

    public static function activityShowUrl(string $sessionId, ?string $back = null): string
    {
        return route('admin.ecom-activity.show', self::activityShowParams($sessionId, $back));
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
