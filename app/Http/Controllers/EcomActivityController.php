<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Services\EcomActivityFilterCounts;
use App\Services\EcomActivityFunnelSessions;
use App\Services\EcomActivityRowMetrics;
use App\Services\EcomActivityTimelinePresenter;
use App\Services\EcomTrackerDashboardService;
use App\Services\EcomTrackerFeatureGate;
use App\Support\EcomActivityFocus;
use App\Support\EcomTrackerLogger;
use App\Support\EcomTrackerViewData;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    public function __construct(
        EcomTrackerFeatureGate $featureGate,
        private EcomTrackerDashboardService $dashboardService,
        private EcomActivityFunnelSessions $funnelSessions,
        private EcomActivityRowMetrics $rowMetrics,
    ) {
        parent::__construct($featureGate);
    }

    public function index(Request $request): View
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.activity.index');

        $focus = $request->input('focus');
        $range = $this->resolveActivityRange($request);
        $resolvedDepartment = EcomActivityFocus::resolvedCategoryDepartment(
            $request,
            $range['from'],
            $range['to'],
            $range['period'],
        );

        if ($resolvedDepartment !== null && ! $request->filled('department')) {
            $request->merge(['department' => $resolvedDepartment]);
        }

        $funnelContext = EcomActivityFocus::isValid($focus)
            ? EcomActivityFocus::resolveFunnelContext(
                $focus,
                $range['from'],
                $range['to'],
                EcomActivityFocus::sessionFiltersFromRequest($request),
                $range['period'],
                $this->funnelSessions,
            )
            : ['session_ids' => collect(), 'metrics' => []];

        $query = $this->buildIndexQuery($request, $range);
        $sessions = $this->paginateSessions($query, $request, $focus, $funnelContext['session_ids']);

        $rowMetrics = $this->rowMetrics->forSessions(
            collect($sessions->items()),
            EcomActivityFocus::isValid($focus) ? $focus : null,
            $range['from'],
            $range['to'],
            $funnelContext['metrics'],
            in_array($focus, ['products', 'categories'], true)
                ? EcomActivityFocus::productCatalogFiltersFromRequest($request)
                : [],
        );

        $visitorQualitySummary = $this->visitorQualityCounts($request, $range);
        $filterOptionCounts = app(EcomActivityFilterCounts::class)->counts(
            $request,
            fn (Request $filterRequest, array $except) => $this->buildIndexQuery($filterRequest, $this->resolveActivityRange($filterRequest), $except, forCounts: true),
        );
        $utmFilterState = TrackerUtmFilter::formState(
            $request->input('utm_source'),
            $request->input('utm_medium'),
            $filterOptionCounts['utm_source'] ?? [],
            $filterOptionCounts['utm_medium'] ?? [],
        );

        $focusLabel = EcomActivityFocus::label($focus);
        $summaryCards = EcomActivityFocus::summaryForFocus(
            $focus,
            $sessions->total(),
            $funnelContext['metrics'],
            $request,
            $range['from'],
            $range['to'],
            $range['period'],
        );
        $drillDownContext = EcomActivityFocus::drillDownContext(
            $request,
            $focus,
            $range['label'],
            $sessions->total(),
            $funnelContext['metrics'],
            $range['from'],
            $range['to'],
            $range['period'],
        );
        $backUrl = EcomTrackerViewData::resolveBackUrl($request->input('back'));
        $breadcrumbs = $this->buildBreadcrumbs(
            $request,
            $drillDownContext ? null : $focusLabel,
            $backUrl,
        );
        $focusColumns = EcomActivityFocus::tableColumns($focus, $request);
        $emptyMessage = EcomActivityFocus::emptyMessage($focus);
        $clearFocusUrl = $request->fullUrlWithQuery(['focus' => null, 'page' => null]);

        EcomTrackerLogger::backend()->info('analytics.activity.index', 'Admin opened user activity list', [
            'session_count' => $sessions->total(),
            'focus' => $focus,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('ecom_activity.index', [
            'sessions' => $sessions,
            'visitorQualitySummary' => $visitorQualitySummary,
            'filterChips' => EcomActivityFocus::sidebarFilterChipsFromRequest($request),
            'sidebarFilterCount' => EcomActivityFocus::sidebarFilterActiveCount($request),
            'filterResetUrl' => EcomActivityFocus::sidebarFilterResetUrl($request),
            'filterOptionCounts' => $filterOptionCounts,
            'utmFilterState' => $utmFilterState,
            'focus' => $focus,
            'focusLabel' => $focusLabel,
            'summaryCards' => $summaryCards,
            'breadcrumbs' => $breadcrumbs,
            'focusColumns' => $focusColumns,
            'rowMetrics' => $rowMetrics,
            'emptyMessage' => $emptyMessage,
            'clearFocusUrl' => $clearFocusUrl,
            'rangeLabel' => $range['label'],
            'hasFocus' => EcomActivityFocus::isValid($focus),
            'backUrl' => $backUrl,
            'drillDownContext' => $drillDownContext,
        ]);
    }

    public function show(Request $request, string $session, EcomActivityTimelinePresenter $timelinePresenter): View
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.activity.show');

        $activityUser = ActivityEcomUser::query()
            ->with('botContext')
            ->where('session_id', $session)
            ->firstOrFail();

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $session)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $fullTimeline = $timelinePresenter->present($actions);
        $latestActionAt = $actions
            ->map(fn (ActivityEcomUserAction $action) => TrackerTime::toUtc($action->created_at))
            ->filter()
            ->sortByDesc(fn (?Carbon $at) => $at?->timestamp ?? 0)
            ->first();

        $reachedSteps = $fullTimeline
            ->pluck('action_type')
            ->unique()
            ->values()
            ->all();

        $page = max(1, (int) $request->query('timeline_page', 1));
        $total = $fullTimeline->count();
        $items = $fullTimeline->slice(($page - 1) * self::TIMELINE_PER_PAGE, self::TIMELINE_PER_PAGE)->values();

        $showRouteParams = EcomTrackerViewData::activityShowParams($session, $request->input('back'));

        $timeline = new LengthAwarePaginator(
            $items,
            $total,
            self::TIMELINE_PER_PAGE,
            $page,
            [
                'path' => route('admin.ecom-activity.show', $showRouteParams),
                'pageName' => 'timeline_page',
            ],
        );

        $timeline->appends($request->except('timeline_page'));

        $returnQuery = EcomTrackerViewData::activityIndexQueryFromRequest($request);
        $backUrl = EcomTrackerViewData::resolveBackUrl(
            $request->input('back'),
            route('admin.ecom-activity.index', $returnQuery),
        );

        EcomTrackerLogger::backend()->info('analytics.activity.show', 'Admin opened one user session', [
            'session_id' => $session,
            'action_count' => $actions->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $trafficAttribution = SessionTrafficAttribution::displayFields($activityUser, $actions);
        $landingPage = filled($activityUser->landing_page)
            ? $activityUser->landing_page
            : $actions
                ->filter(fn (ActivityEcomUserAction $action) => filled($action->page_url))
                ->sortBy([
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first()
                ?->page_url;

        return view('ecom_activity.show', [
            'activityUser' => $activityUser,
            'timeline' => $timeline,
            'funnelSteps' => self::FUNNEL_STEPS,
            'reachedSteps' => $reachedSteps,
            'backUrl' => $backUrl,
            'trafficAttribution' => $trafficAttribution,
            'landingPage' => $landingPage,
            'latestActionAt' => $latestActionAt,
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

        $range = $this->dashboardService->resolveDateRange([
            'period' => $request->input('period', '24h'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'period' => $range['period'] ?? $request->input('period', '24h'),
        ];
    }

    private function applySessionDateFilter(Builder $query, Request $request, array $range): void
    {
        if (($range['period'] ?? null) === 'all') {
            return;
        }

        TrackerTime::applyEcomActivitySessionScope(
            $query,
            $range['from'],
            $range['to'],
            $range['period'] ?? $request->input('period', '24h'),
        );
    }

    /**
     * @param  array<int, string>  $except
     */
    private function buildIndexQuery(
        Request $request,
        array $range,
        array $except = [],
        bool $forCounts = false,
    ): Builder {
        $query = ActivityEcomUser::query();
        $focus = $request->input('focus');

        if (! $forCounts) {
            $query->with(['botContext', 'firstAction', 'firstRefererAction'])
                ->withCount('actions')
                ->withCount(['actions as order_qty' => fn ($q) => $q->where('action_type', 'payment_success')]);
        }

        if (! EcomActivityFocus::usesActionScopedSessionDate($request)) {
            $this->applySessionDateFilter($query, $request, $range);
        }

        if (! in_array('focus', $except, true)) {
            EcomActivityFocus::applyFocusFilter(
                $query,
                $focus,
                $range['from'],
                $range['to'],
                $request,
                $this->dashboardService,
                $this->funnelSessions,
            );
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                    ->orWhere('visitor_id', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('user_phone', 'like', "%{$search}%")
                    ->orWhere('utm_source', 'like', "%{$search}%")
                    ->orWhere('utm_medium', 'like', "%{$search}%")
                    ->orWhere('utm_campaign', 'like', "%{$search}%")
                    ->orWhere('landing_page', 'like', "%{$search}%")
                    ->orWhereHas('botContext', fn ($b) => $b
                        ->where('client_ip', 'like', "%{$search}%")
                        ->orWhere('ip_country', 'like', "%{$search}%")
                        ->orWhere('cf_ray', 'like', "%{$search}%")
                        ->orWhere('bot_reason', 'like', "%{$search}%"))
                    ->orWhereHas('actions', fn ($actions) => $actions
                        ->where('page_url', 'like', "%{$search}%")
                        ->orWhere('referer', 'like', "%{$search}%"));
            });
        }

        if (! in_array('country', $except, true) && $request->filled('country')) {
            $query->where(function ($q) use ($request) {
                $q->where('country', $request->country)
                    ->orWhereHas('botContext', fn ($b) => $b->where('ip_country', $request->country));
            });
        }

        if (! in_array('visitor_type', $except, true)) {
            $visitorType = $request->input('visitor_type');

            if ($visitorType === 'bot') {
                $query->whereHas('botContext', fn ($b) => $b->where('is_bot', true));
            } elseif ($visitorType === 'human') {
                $query->whereHas('botContext', fn ($b) => $b->where('is_bot', false));
            } elseif ($visitorType === 'unclassified') {
                $query->whereDoesntHave('botContext');
            }
        }

        if (! in_array('device_type', $except, true) && $request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        if (! in_array('logged_in', $except, true) && $request->filled('logged_in')) {
            $query->where('is_logged_in', $request->logged_in === '1');
        }

        if (! in_array('has_order', $except, true) && $request->filled('has_order') && ! EcomActivityFocus::shouldDeferHasOrderFilter($request)) {
            if ($request->has_order === '1') {
                $query->whereHas('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            } elseif ($request->has_order === '0') {
                $query->whereDoesntHave('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            }
        }

        if (! in_array('utm_source', $except, true)) {
            TrackerUtmFilter::applySourceFilter($query, $request->input('utm_source'));
        }

        if (! in_array('utm_medium', $except, true)) {
            TrackerUtmFilter::applyMediumFilter($query, $request->input('utm_medium'));
        }

        return $query;
    }

    /**
     * @param  Collection<int, string>  $funnelSessionIds
     */
    private function paginateSessions(
        Builder $query,
        Request $request,
        ?string $focus,
        Collection $funnelSessionIds,
    ): LengthAwarePaginator {
        $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));

        if (EcomActivityFocus::isValid($focus) && EcomActivityFocus::sortMode($focus) === 'value_desc' && $funnelSessionIds->isNotEmpty()) {
            $total = $funnelSessionIds->count();
            $pageIds = $funnelSessionIds->slice(($page - 1) * $perPage, $perPage)->values();
            $sessions = $query->whereIn('session_id', $pageIds->all())->get()->keyBy('session_id');
            $items = $pageIds
                ->map(fn (string $id) => $sessions->get($id))
                ->filter()
                ->values();

            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        }

        return $query
            ->orderByLatestActivity()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{real_shoppers: int, automated_traffic: int, not_classified: int}
     */
    private function visitorQualityCounts(Request $request, array $range): array
    {
        $base = $this->buildIndexQuery($request, $range, forCounts: true);

        return [
            'real_shoppers' => (clone $base)->whereHas('botContext', fn ($b) => $b->where('is_bot', false))->count(),
            'automated_traffic' => (clone $base)->whereHas('botContext', fn ($b) => $b->where('is_bot', true))->count(),
            'not_classified' => (clone $base)->whereDoesntHave('botContext')->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, url?: string}>
     */
    private function buildBreadcrumbs(Request $request, ?string $focusLabel, ?string $dashboardBack = null): array
    {
        $dashboardBack ??= EcomTrackerViewData::resolveBackUrl($request->input('back'));

        if ($dashboardBack !== null) {
            return filled($focusLabel) ? [['label' => $focusLabel]] : [];
        }

        if (! filled($focusLabel)) {
            return [];
        }

        return [['label' => $focusLabel]];
    }
}
