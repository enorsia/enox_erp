<?php

namespace App\Services;

use App\Support\EcomTrackerCompareFilters;
use App\Support\EcomTrackerCompareKpiGroups;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EcomTrackerCompareService
{
    /** @var list<string> */
    private const FILTER_KEYS = [
        'device_type', 'logged_in', 'has_order', 'visitor_type', 'utm_source', 'utm_medium',
    ];

    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
        private EcomActivityListService $activityList,
    ) {}

    public function handle(Request $request): View|RedirectResponse
    {
        $leftFilters = $this->sideFiltersFromRequest($request, 'left');

        if (! $this->hasSideParams($request, 'right')) {
            $leftRange = EcomActivityListService::periodRange(
                EcomActivityListService::normalizePeriod((string) ($leftFilters['period'] ?? '24h')),
                (string) ($leftFilters['date_from'] ?? ''),
                (string) ($leftFilters['date_to'] ?? ''),
            );
            $rightFilters = $this->defaultRightFilters($leftFilters, $leftRange);

            return redirect()->to($this->compareUrl($request, $leftFilters, $rightFilters));
        }

        $rightFilters = $this->sideFiltersFromRequest($request, 'right');
        $backUrl = $this->resolveBackUrl($request);
        $compareSelf = $request->fullUrl();

        $leftDashboard = $this->loadDashboard($leftFilters, $compareSelf);
        $rightDashboard = $this->loadDashboard($rightFilters, $compareSelf);

        $leftColumn = $this->buildColumn('left', 'Period A', 'etd-compare-column--a', $leftFilters, $rightFilters, $leftDashboard, $request, $backUrl, $compareSelf);
        $rightColumn = $this->buildColumn('right', 'Period B', 'etd-compare-column--b', $rightFilters, $leftFilters, $rightDashboard, $request, $backUrl, $compareSelf);

        return view('ecom_tracker.compare', [
            'compare' => [
                'backUrl' => $backUrl,
                'printButtonId' => 'etdComparePrintBtn',
                'header' => [
                    'pageTitle' => 'Store performance',
                    'modeLabel' => 'Compare',
                    'rangeA' => $leftColumn['rangeLabel'],
                    'rangeB' => $rightColumn['rangeLabel'],
                    'timezone' => 'All times '.TrackerTime::timezoneLabel(),
                ],
                'executive' => $this->buildExecutiveRows($leftDashboard, $rightDashboard),
                'columns' => [$leftColumn, $rightColumn],
                'chartData' => [
                    'left' => [
                        'trend' => $leftDashboard['charts']['trend'] ?? [],
                        'new_returning' => $leftDashboard['charts']['new_returning'] ?? null,
                    ],
                    'right' => [
                        'trend' => $rightDashboard['charts']['trend'] ?? [],
                        'new_returning' => $rightDashboard['charts']['new_returning'] ?? null,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $otherFilters
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    private function buildColumn(
        string $side,
        string $badge,
        string $modifier,
        array $filters,
        array $otherFilters,
        array $dashboard,
        Request $request,
        string $backUrl,
        string $compareSelfUrl,
    ): array {
        $period = EcomActivityListService::normalizePeriod((string) ($filters['period'] ?? '24h'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? ''),
        );
        $prefix = $side === 'right' ? 'right_' : 'left_';

        $chartSuffix = $side === 'right' ? 'Right' : 'Left';

        return [
            'side' => $side,
            'badge' => $badge,
            'modifier' => $modifier,
            'rangeLabel' => $this->rangeLabel($period, $range),
            'periodNav' => $this->periodNav($side, $filters, $otherFilters, $range, $request, $backUrl),
            'filter' => $this->filterForm($side, $filters, $otherFilters, $request, $backUrl),
            'kpiGroups' => $this->kpiGroupsWithActivityLinks(
                $dashboard['kpiGroups'] ?? [],
                $filters,
                $compareSelfUrl,
            ),
            'chart' => [
                'canvasId' => 'etdTrendChart'.$chartSuffix,
                'scrollId' => 'etdTrendChartScroll'.$chartSuffix,
                'wrapId' => 'etdTrendChartWrap'.$chartSuffix,
                'legendId' => 'etdTrendLegend'.$chartSuffix,
                'hintId' => 'etdTrendChartScrollHint'.$chartSuffix,
                'newReturningCanvasId' => 'etdNewReturningChart'.$chartSuffix,
            ],
            'merchandising' => $this->merchandising($dashboard, $filters, $compareSelfUrl),
            'recoverable' => $this->recoverableComparePanels($dashboard, $filters, $compareSelfUrl),
            'acquisition' => $this->acquisition($dashboard, $filters, $compareSelfUrl),
            'prefix' => $prefix,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $kpiGroups
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function kpiGroupsWithActivityLinks(array $kpiGroups, array $filters, string $backUrl): array
    {
        $baseQuery = EcomTrackerCompareFilters::activityIndexQuery($filters, $backUrl);

        return array_map(function (array $group) use ($baseQuery) {
            $metrics = array_map(function (array $metric) use ($baseQuery) {
                $activity = $metric['activity'] ?? [];
                $href = $activity !== []
                    ? route('admin.ecom-activity.index', array_merge(
                        $baseQuery,
                        array_filter($activity, fn ($value) => $value !== null),
                    ))
                    : null;

                return array_merge($metric, ['href' => $href]);
            }, $group['metrics'] ?? []);

            return array_merge($group, ['metrics' => $metrics]);
        }, $kpiGroups);
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<string, string>
     */
    private function kpiStrings(array $dashboard): array
    {
        $byLabel = [];
        foreach ($dashboard['kpiGroups'] as $group) {
            foreach ($group['metrics'] as $metric) {
                $byLabel[$metric['label'] ?? ''] = (string) ($metric['formatted'] ?? '');
            }
        }

        return [
            'visitors' => $byLabel['Unique visitors'] ?? '0',
            'sessions' => $byLabel['Sessions'] ?? '0',
            'itemsSold' => $byLabel['Items sold'] ?? '0',
            'revenue' => $byLabel['Sale amount'] ?? '£0.00',
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return list<array{label: string, a: string, b: string, change: string, changeClass: string}>
     */
    private function buildExecutiveRows(array $left, array $right): array
    {
        $leftKpis = $this->kpiStrings($left);
        $rightKpis = $this->kpiStrings($right);

        $defs = [
            ['label' => 'Unique visitors', 'key' => 'visitors', 'numeric' => true],
            ['label' => 'Sessions', 'key' => 'sessions', 'numeric' => true],
            ['label' => 'Sale amount', 'key' => 'revenue', 'numeric' => false],
            ['label' => 'Items sold', 'key' => 'itemsSold', 'numeric' => true],
        ];

        $rows = [];
        foreach ($defs as $def) {
            $aRaw = $leftKpis[$def['key']] ?? '0';
            $bRaw = $rightKpis[$def['key']] ?? '0';
            $delta = $this->formatExecutiveDelta(
                $this->parseDisplayNumber($aRaw, $def['numeric']),
                $this->parseDisplayNumber($bRaw, $def['numeric']),
            );
            $rows[] = [
                'label' => $def['label'],
                'a' => $aRaw,
                'b' => $bRaw,
                'change' => $delta['text'],
                'changeClass' => $delta['class'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{text: string, class: string}
     */
    private function formatExecutiveDelta(float $a, float $b): array
    {
        if ($b == 0.0) {
            if ($a > 0) {
                return ['text' => '↑ New', 'class' => 'etd-compare-delta--good'];
            }

            return ['text' => '—', 'class' => 'etd-compare-delta--neutral'];
        }

        $pct = round((($a - $b) / $b) * 100, 1);
        $good = $pct >= 0;
        $arrow = $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '—');
        $sign = $pct > 0 ? '+' : '';

        return [
            'text' => $arrow.' '.$sign.number_format($pct, 1).'%',
            'class' => $pct === 0.0 ? 'etd-compare-delta--neutral' : ($good ? 'etd-compare-delta--good' : 'etd-compare-delta--bad'),
        ];
    }

    private function parseDisplayNumber(string $value, bool $stripCurrency): float
    {
        $clean = str_replace([',', '£'], '', $value);

        return (float) $clean;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $filters
     * @return array{
     *     categoryTotals: array<string, int>,
     *     departments: list<array<string, mixed>>,
     *     productTotals: array<string, int>,
     *     products: list<array<string, mixed>>
     * }
     */
    private function merchandising(array $dashboard, array $filters, string $backUrl): array
    {
        $baseQuery = EcomTrackerCompareFilters::activityIndexQuery($filters, $backUrl);
        $departments = $dashboard['categories']['departments'] ?? [];

        foreach ($departments as $departmentIndex => $department) {
            $categories = $department['categories'] ?? [];
            foreach ($categories as $categoryIndex => $category) {
                $departments[$departmentIndex]['categories'][$categoryIndex]['activity_url'] = route(
                    'admin.ecom-activity.index',
                    array_merge($baseQuery, array_filter([
                        'department' => $category['department_id'] ?? null,
                        'category' => $category['category_id'] ?? null,
                    ], fn ($value) => $value !== null && $value !== '')),
                );
            }
        }

        $products = [];
        foreach ($dashboard['products']['rows'] ?? [] as $product) {
            $products[] = array_merge($product, [
                'activity_url' => route(
                    'admin.ecom-activity.index',
                    array_merge($baseQuery, ['search' => (string) ($product['code'] ?? '')]),
                ),
            ]);
        }

        return [
            'categoryTotals' => $dashboard['categories']['totals'] ?? [
                'category_views' => 0,
                'product_views' => 0,
                'category_count' => 0,
            ],
            'departments' => $departments,
            'productTotals' => $dashboard['products']['totals'] ?? [
                'views' => 0,
                'product_count' => 0,
            ],
            'products' => $products,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function recoverableComparePanels(array $dashboard, array $filters, string $backUrl): array
    {
        $baseQuery = EcomTrackerCompareFilters::activityIndexQuery($filters, $backUrl);
        $panels = [];

        foreach ($dashboard['recoverable'] ?? [] as $panel) {
            $stage = $panel['stage'] ?? null;
            $panels[] = [
                'title' => (string) ($panel['title'] ?? ''),
                'tone' => (string) ($panel['tone'] ?? 'cart'),
                'session_count' => (int) ($panel['session_count'] ?? 0),
                'at_stake' => (float) ($panel['at_stake'] ?? 0),
                'href' => route(
                    'admin.ecom-activity.index',
                    array_merge($baseQuery, array_filter(['funnel' => $stage], fn ($value) => $value !== null)),
                ),
            ];
        }

        return $panels;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function acquisition(array $dashboard, array $filters, string $backUrl): array
    {
        $baseQuery = EcomTrackerCompareFilters::activityIndexQuery($filters, $backUrl);
        $activityIndexUrl = route('admin.ecom-activity.index', $baseQuery);

        $byDevice = [];
        foreach ($dashboard['devices']['by_device'] ?? [] as $row) {
            $deviceType = $row['id'] ?? null;
            $byDevice[] = array_merge($row, [
                'activity_url' => route(
                    'admin.ecom-activity.index',
                    array_merge($baseQuery, array_filter([
                        'device_type' => $deviceType,
                    ], fn ($value) => $value !== null && $value !== '')),
                ),
            ]);
        }

        $byBrowser = [];
        foreach ($dashboard['devices']['by_browser'] ?? [] as $row) {
            $byBrowser[] = array_merge($row, [
                'activity_url' => $activityIndexUrl,
            ]);
        }

        $traffic = [];
        foreach ($dashboard['traffic'] ?? [] as $row) {
            $sourceId = $row['id'] ?? null;
            $traffic[] = array_merge($row, [
                'activity_url' => ($row['source'] ?? '') === 'Other' || $sourceId === null
                    ? null
                    : route(
                        'admin.ecom-activity.index',
                        array_merge($baseQuery, ['utm_source' => $sourceId]),
                    ),
            ]);
        }

        $durations = $dashboard['durations'] ?? ['buckets' => [], 'total_sessions' => 0, 'median_label' => '0s'];
        $durationBuckets = [];
        foreach ($durations['buckets'] ?? [] as $bucket) {
            $durationBuckets[] = array_merge($bucket, [
                'activity_url' => route(
                    'admin.ecom-activity.index',
                    array_merge($baseQuery, array_filter([
                        'duration' => $bucket['key'] ?? null,
                    ], fn ($value) => $value !== null && $value !== '')),
                ),
            ]);
        }
        $durations['buckets'] = $durationBuckets;

        return [
            'devices' => [
                'by_device' => $byDevice,
                'by_browser' => $byBrowser,
            ],
            'devicesDetailUrl' => $activityIndexUrl,
            'traffic' => $traffic,
            'trafficDetailUrl' => $activityIndexUrl,
            'durations' => $durations,
            'newReturning' => $dashboard['newReturning'] ?? ['unique' => 0, 'returning' => 0],
            'audienceDetailUrl' => $activityIndexUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function loadDashboard(array $filters, string $backUrl): array
    {
        $period = EcomActivityListService::normalizePeriod((string) ($filters['period'] ?? '24h'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? ''),
        );

        $dashboard = $this->dashboardService->dashboard($range, $period, $backUrl);
        $sessionFilters = EcomTrackerCompareFilters::toActivityFilters($filters);

        if (! EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return $dashboard;
        }

        $days = (int) $range['from']->copy()->startOfDay()->diffInDays($range['to']->copy()->startOfDay()) + 1;
        $prevFrom = $range['from']->copy()->subDays($days);
        $prevTo = $range['from']->copy()->subDay();
        $prevRange = ['from' => $prevFrom->copy()->startOfDay(), 'to' => $prevTo->copy()->endOfDay()];

        $current = $this->activityList->dashboardTotals($range, $sessionFilters);
        $previous = $this->activityList->dashboardTotals($prevRange, $sessionFilters);
        $returning = max(0, $current['sessions'] - $current['unique_visitors']);

        $dashboard['kpiGroups'] = EcomTrackerCompareKpiGroups::build($current, $previous, $period, $prevFrom, $prevTo);
        $dashboard['newReturning'] = ['unique' => $current['unique_visitors'], 'returning' => $returning];
        $dashboard['charts']['new_returning'] = [
            'labels' => ['Unique', 'Returning'],
            'values' => [$current['unique_visitors'], $returning],
        ];

        return $dashboard;
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     */
    private function rangeLabel(string $period, array $range): string
    {
        $parts = TrackerTime::rangeDisplayParts([
            'period' => $period,
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
        ]);

        if (($parts['type'] ?? '') === 'range') {
            return $parts['from'].' – '.$parts['to'];
        }

        return (string) ($parts['label'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $otherFilters
     * @param  array{from: Carbon, to: Carbon}  $range
     * @return array<string, mixed>
     */
    private function periodNav(
        string $side,
        array $filters,
        array $otherFilters,
        array $range,
        Request $request,
        string $backUrl,
    ): array {
        $period = EcomActivityListService::normalizePeriod((string) ($filters['period'] ?? '24h'));
        $presets = [];
        foreach (['24h' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7d', '30d' => '30d'] as $key => $label) {
            $next = collect($filters)->except(['date_from', 'date_to'])->merge(['period' => $key])->all();
            $presets[] = [
                'label' => $label,
                'url' => $side === 'left'
                    ? $this->compareUrl($request, $next, $otherFilters)
                    : $this->compareUrl($request, $otherFilters, $next),
                'active' => $period === $key,
            ];
        }

        $shiftedPrev = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $shiftedNext = EcomActivityListService::shiftedPeriodQuery($range, 1);
        $prevFilters = $shiftedPrev
            ? collect($filters)->except(['date_from', 'date_to'])->merge($shiftedPrev)->all()
            : null;
        $nextFilters = $shiftedNext
            ? collect($filters)->except(['date_from', 'date_to'])->merge($shiftedNext)->all()
            : null;

        return [
            'presets' => $presets,
            'prevUrl' => $prevFilters
                ? ($side === 'left'
                    ? $this->compareUrl($request, $prevFilters, $otherFilters)
                    : $this->compareUrl($request, $otherFilters, $prevFilters))
                : null,
            'nextUrl' => $nextFilters
                ? ($side === 'left'
                    ? $this->compareUrl($request, $nextFilters, $otherFilters)
                    : $this->compareUrl($request, $otherFilters, $nextFilters))
                : null,
            'dateFrom' => $range['from']->toDateString(),
            'dateTo' => $range['to']->toDateString(),
            'period' => $period,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $otherFilters
     * @return array<string, mixed>
     */
    private function filterForm(string $side, array $filters, array $otherFilters, Request $request, string $backUrl): array
    {
        $prefix = $side === 'right' ? 'right_' : 'left_';
        $hidden = [];
        foreach ($this->sideQuery($side === 'left' ? 'right' : 'left', $otherFilters) as $name => $value) {
            $hidden[] = ['name' => $name, 'value' => $value];
        }
        if ($request->filled('back')) {
            $hidden[] = ['name' => 'back', 'value' => (string) $request->input('back')];
        }

        $filterOptions = $this->activityList->filterOptions();

        return [
            'action' => route('admin.ecom-tracker.dashboard.compare'),
            'prefix' => $prefix,
            'hidden' => $hidden,
            'period' => (string) ($filters['period'] ?? '24h'),
            'dateFrom' => (string) ($filters['date_from'] ?? ''),
            'dateTo' => (string) ($filters['date_to'] ?? ''),
            'deviceOptions' => EcomTrackerCompareFilters::idSelectOptions(
                $filterOptions['devices'],
                $filters['device_type'] ?? '',
                true,
            ),
            'utmSourceOptions' => EcomTrackerCompareFilters::idSelectOptions(
                $filterOptions['utmSources'],
                $filters['utm_source'] ?? '',
            ),
            'utmMediumOptions' => EcomTrackerCompareFilters::stringSelectOptions(
                $filterOptions['utmMediums'],
                $filters['utm_medium'] ?? '',
            ),
            'loggedInOptions' => $this->selectOptions($filters['logged_in'] ?? '', ['' => 'All', '1' => 'Logged in', '0' => 'Guest']),
            'hasOrderOptions' => $this->selectOptions($filters['has_order'] ?? '', ['' => 'All', '1' => 'With order', '0' => 'No order']),
            'visitorTypeOptions' => $this->selectOptions($filters['visitor_type'] ?? '', [
                '' => 'All', 'human' => 'Real visitors', 'bot' => 'Automated traffic', 'unclassified' => 'Not classified',
            ]),
        ];
    }

    /**
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private function selectOptions(mixed $current, array $labels): array
    {
        $current = (string) $current;
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => $label,
                'selected' => $current === (string) $value,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $leftFilters
     * @param  array<string, mixed>  $rightFilters
     */
    private function compareUrl(Request $request, array $leftFilters, array $rightFilters): string
    {
        $query = array_merge(
            $this->sideQuery('left', $leftFilters),
            $this->sideQuery('right', $rightFilters),
        );

        if ($request->filled('back')) {
            $query['back'] = $request->input('back');
        }

        return route('admin.ecom-tracker.dashboard.compare', $query);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function sideQuery(string $side, array $filters): array
    {
        $prefix = $side === 'right' ? 'right_' : 'left_';
        $query = [];
        $period = (string) ($filters['period'] ?? '24h');
        $query["{$prefix}period"] = $period;

        if ($period === 'custom') {
            if (filled($filters['date_from'] ?? null)) {
                $query["{$prefix}date_from"] = (string) $filters['date_from'];
            }
            if (filled($filters['date_to'] ?? null)) {
                $query["{$prefix}date_to"] = (string) $filters['date_to'];
            }
        }

        foreach (self::FILTER_KEYS as $key) {
            if (filled($filters[$key] ?? null)) {
                $query["{$prefix}{$key}"] = (string) $filters[$key];
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function sideFiltersFromRequest(Request $request, string $side): array
    {
        $prefix = $side === 'right' ? 'right_' : 'left_';
        $period = $request->input("{$prefix}period");

        $filters = [
            'period' => filled($period) ? (string) $period : '24h',
            'date_from' => $request->input("{$prefix}date_from"),
            'date_to' => $request->input("{$prefix}date_to"),
        ];

        foreach (self::FILTER_KEYS as $key) {
            $filters[$key] = $request->input("{$prefix}{$key}");
        }

        return array_filter(
            $filters,
            fn ($value, string $key) => $key === 'period' || filled($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function hasSideParams(Request $request, string $side): bool
    {
        $prefix = $side === 'right' ? 'right_' : 'left_';

        return $request->filled("{$prefix}period")
            || $request->filled("{$prefix}date_from")
            || $request->filled("{$prefix}date_to");
    }

    /**
     * @param  array<string, mixed>  $leftFilters
     * @param  array{from: Carbon, to: Carbon}  $leftRange
     * @return array<string, mixed>
     */
    private function defaultRightFilters(array $leftFilters, array $leftRange): array
    {
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($leftRange, -1);
        $sessionFilters = collect($leftFilters)
            ->except(['period', 'date_from', 'date_to'])
            ->filter(fn ($value) => filled($value))
            ->all();

        if ($prevQuery === null) {
            return array_merge($sessionFilters, ['period' => 'yesterday']);
        }

        return array_merge($sessionFilters, $prevQuery);
    }

    private function resolveBackUrl(Request $request): string
    {
        $back = $request->input('back');

        if (is_string($back) && filled($back)) {
            return $back;
        }

        return route('admin.ecom-tracker.dashboard');
    }
}
