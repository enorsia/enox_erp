<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use App\Services\EcomTrackerFeatureGate;
use App\Support\EcomActivityFocus;
use App\Support\EcomTrackerViewData;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class EcomActivityController extends EcomTrackerAdminController
{
    private const TIMELINE_PER_PAGE = 15;

    private const FUNNEL_STEPS = [
        'category_view',
        'product_view',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ];

    /** @var array<string, array<string, int>> */
    private const EMPTY_FILTER_OPTION_COUNTS = [
        'device_type' => [],
        'logged_in' => [],
        'has_order' => [],
        'visitor_type' => [],
        'utm_source' => [],
        'utm_medium' => [],
    ];

    public function __construct(EcomTrackerFeatureGate $featureGate)
    {
        parent::__construct($featureGate);
    }

    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.activity.index');

        $isTableFragment = $request->input('fragment') === 'table' && $request->ajax();
        $focus = $request->input('focus');
        $range = $this->resolveActivityRange($request);
        $sessions = $this->emptySessionsPaginator($request);
        $rowMetrics = [];
        $hasFocus = EcomActivityFocus::isValid($focus);

        $tableViewData = [
            'sessions' => $sessions,
            'focusColumns' => EcomActivityFocus::tableColumns($focus, $request),
            'rowMetrics' => $rowMetrics,
            'emptyMessage' => EcomActivityFocus::emptyMessage($focus),
            'clearFocusUrl' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
            'hasFocus' => $hasFocus,
        ];

        if ($isTableFragment) {
            return view('ecom_activity.partials.table-fragment', $tableViewData);
        }

        $filterOptionCounts = self::EMPTY_FILTER_OPTION_COUNTS;
        $utmFilterState = TrackerUtmFilter::formState(
            $request->input('utm_source'),
            $request->input('utm_medium'),
            $filterOptionCounts['utm_source'],
            $filterOptionCounts['utm_medium'],
        );
        $utmFilterState['sources'] = [];
        $utmFilterState['mediums'] = [];

        $focusLabel = EcomActivityFocus::label($focus);
        $summaryFocus = EcomActivityFocus::resolveFilterSummaryFocus($request);
        $summaryCards = $hasFocus
            ? [['label' => 'Matching sessions', 'value' => 0]]
            : [];
        $activityListContext = $this->activityListContextShell($request, $summaryFocus, $range['label'], $hasFocus);
        $backUrl = EcomTrackerViewData::activityIndexBackUrl($request);
        $breadcrumbs = $this->buildBreadcrumbs($request, $activityListContext ? null : $focusLabel);

        return view('ecom_activity.index', [
            'sessions' => $sessions,
            'visitorQualitySummary' => [
                'real_shoppers' => 0,
                'automated_traffic' => 0,
                'not_classified' => 0,
            ],
            'filterChips' => EcomActivityFocus::sidebarFilterChipsFromRequest($request),
            'sidebarFilterCount' => EcomActivityFocus::sidebarFilterActiveCount($request),
            'filterResetUrl' => EcomActivityFocus::sidebarFilterResetUrl($request),
            'filterOptionCounts' => $filterOptionCounts,
            'utmFilterState' => $utmFilterState,
            'focus' => $focus,
            'focusLabel' => $focusLabel,
            'summaryCards' => $summaryCards,
            'breadcrumbs' => $breadcrumbs,
            'focusColumns' => EcomActivityFocus::tableColumns($focus, $request),
            'rowMetrics' => $rowMetrics,
            'emptyMessage' => EcomActivityFocus::emptyMessage($focus),
            'clearFocusUrl' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
            'range' => [
                'from' => $range['from'],
                'to' => $range['to'],
                'label' => $range['label'],
            ],
            'rangeLabel' => $range['label'],
            'period' => ($range['period'] ?? $request->input('period', '24h')) === '90d'
                ? '30d'
                : ($range['period'] ?? $request->input('period', '24h')),
            'dateFrom' => $request->input('date_from', ''),
            'dateTo' => $request->input('date_to', ''),
            'hasFocus' => $hasFocus,
            'backUrl' => $backUrl,
            'drillDownContext' => $activityListContext,
            'activityListContext' => $activityListContext,
            'showCatalogFilters' => EcomActivityFocus::showCatalogFiltersInDrawer($request),
            'productFilterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
            'categoryFilterOptions' => ['departments' => [], 'categories_by_department' => []],
            'eventScenarioOptions' => [],
            'productSortGroups' => [],
            'productActivityOptions' => [],
            'activityExport' => null,
        ]);
    }

    public function show(Request $request, string $session): View
    {
        Gate::authorize('ecom_tracker.activity.show');

        $activityUser = new ActivityEcomUser(['session_id' => $session]);
        $showRouteParams = EcomTrackerViewData::activityShowParams($session, $request->input('back'));

        $timeline = new LengthAwarePaginator(
            [],
            0,
            self::TIMELINE_PER_PAGE,
            max(1, (int) $request->query('timeline_page', 1)),
            [
                'path' => route('admin.ecom-activity.show', $showRouteParams),
                'pageName' => 'timeline_page',
            ],
        );

        $timeline->appends($request->except('timeline_page'));

        return view('ecom_activity.show', [
            'activityUser' => $activityUser,
            'timeline' => $timeline,
            'funnelSteps' => self::FUNNEL_STEPS,
            'reachedSteps' => [],
            'backUrl' => EcomTrackerViewData::activityListBackUrlForShow($request),
            'trafficAttribution' => SessionTrafficAttribution::displayFields($activityUser),
            'conversionAttribution' => SessionTrafficAttribution::conversionOrMarketingDisplayFields($activityUser),
            'landingPage' => null,
            'latestActionAt' => null,
            'relatedVisitorSessions' => [],
        ]);
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, period: ?string}
     */
    private function resolveActivityRange(Request $request): array
    {
        if ($request->input('period') === 'all') {
            return [
                'from' => Carbon::parse('2000-01-01', 'UTC'),
                'to' => TrackerTime::nowUtc(),
                'label' => 'All sessions',
                'period' => 'all',
            ];
        }

        $period = $request->input('period', '24h') ?: '24h';

        if ($period === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            $fromLocal = Carbon::parse((string) $request->input('date_from'), TrackerTime::timezone())->startOfDay();
            $toLocal = Carbon::parse((string) $request->input('date_to'), TrackerTime::timezone())->endOfDay();

            return [
                'from' => $fromLocal->copy()->utc(),
                'to' => $toLocal->copy()->utc(),
                'label' => TrackerTime::formatLocalDateRangeLabel($fromLocal, $toLocal),
                'period' => 'custom',
            ];
        }

        if ($period === '24h') {
            $today = TrackerTime::todayRangeUtc();

            return [
                'from' => $today['from'],
                'to' => $today['to'],
                'label' => TrackerTime::todayPresetLabel(),
                'period' => '24h',
            ];
        }

        if ($period === 'yesterday') {
            $yesterday = TrackerTime::yesterdayRangeUtc();

            return [
                'from' => $yesterday['from'],
                'to' => $yesterday['to'],
                'label' => TrackerTime::yesterdayPresetLabel(),
                'period' => 'yesterday',
            ];
        }

        $days = $period === '7d' ? 7 : 30;
        $toLocal = TrackerTime::localNow()->endOfDay();
        $fromLocal = TrackerTime::localNow()->subDays($days - 1)->startOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
            'label' => "Last {$days} days",
            'period' => $period === '7d' ? '7d' : '30d',
        ];
    }

    private function emptySessionsPaginator(Request $request): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator(
            [],
            0,
            25,
            max(1, (int) $request->input('page', 1)),
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        );

        return $paginator->appends($request->except(['page', 'fragment']));
    }

    /**
     * @return array<int, array{label: string, url?: string}>
     */
    private function buildBreadcrumbs(Request $request, ?string $focusLabel): array
    {
        $dashboardBack = EcomTrackerViewData::resolveBackUrl($request->input('back'));

        if ($dashboardBack !== null) {
            return filled($focusLabel) ? [['label' => $focusLabel]] : [];
        }

        if (! filled($focusLabel)) {
            return [];
        }

        return [['label' => $focusLabel]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activityListContextShell(
        Request $request,
        ?string $summaryFocus,
        string $rangeLabel,
        bool $hasDashboardFocus,
    ): ?array {
        if ($summaryFocus === null) {
            return null;
        }

        $criteria = EcomActivityFocus::filterCriteriaFromRequest($request);

        return [
            'section' => EcomActivityFocus::label($summaryFocus),
            'description' => EcomActivityFocus::drillDownDescription($summaryFocus)
                ?? EcomActivityFocus::filterSummaryDescription($summaryFocus),
            'range_label' => $rangeLabel,
            'criteria' => $criteria,
            'metrics' => [['label' => 'Matching sessions', 'value' => 0]],
            'filter_chips' => EcomActivityFocus::filterChipsFromCriteria($request, $criteria, $hasDashboardFocus),
            'clear_focus_url' => $hasDashboardFocus
                ? $request->fullUrlWithQuery(['focus' => null, 'page' => null])
                : EcomActivityFocus::sidebarFilterResetUrl($request),
            'clear_label' => $hasDashboardFocus ? 'Clear section' : 'Clear filters',
        ];
    }
}
