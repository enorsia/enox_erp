<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Services\EcomActivityFilterCounts;
use App\Services\EcomActivityTimelinePresenter;
use App\Support\EcomTrackerLogger;
use App\Support\EcomTrackerViewData;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class EcomActivityController extends Controller
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

    public function index(Request $request): View
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.activity.index');

        $query = $this->buildIndexQuery($request);

        $sessions = (clone $query)
            ->orderByDesc('last_active_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $visitorQualitySummary = $this->visitorQualityCounts($request);
        $filterOptionCounts = app(EcomActivityFilterCounts::class)->counts(
            $request,
            fn (Request $filterRequest, array $except) => $this->buildIndexQuery($filterRequest, $except, forCounts: true),
        );
        $utmFilterState = TrackerUtmFilter::formState(
            $request->input('utm_source'),
            $request->input('utm_medium'),
            $filterOptionCounts['utm_source'] ?? [],
            $filterOptionCounts['utm_medium'] ?? [],
        );

        EcomTrackerLogger::backend()->info('analytics.activity.index', 'Admin opened user activity list', [
            'session_count' => $sessions->total(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('ecom_activity.index', [
            'sessions' => $sessions,
            'visitorQualitySummary' => $visitorQualitySummary,
            'filterChips' => $this->buildActivityFilterChips($request),
            'filterOptionCounts' => $filterOptionCounts,
            'utmFilterState' => $utmFilterState,
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

        $returnQuery = $request->only(['search', 'period', 'date_from', 'date_to', 'device_type', 'logged_in', 'has_order', 'country', 'visitor_type', 'utm_source', 'utm_medium', 'page']);
        $backUrl = $request->filled('back')
            ? urldecode((string) $request->input('back'))
            : route('admin.ecom-activity.index', $returnQuery);

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
        ]);
    }

    private function applySessionDateFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        if ($request->input('period') === 'all') {
            return;
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            return;
        }

        $today = TrackerTime::todayRangeUtc();

        TrackerTime::applySessionActivityWindow($query, $today['from'], $today['to']);
    }

    /**
     * @param  array<int, string>  $except
     */
    private function buildIndexQuery(Request $request, array $except = [], bool $forCounts = false): Builder
    {
        $query = ActivityEcomUser::query();

        if (! $forCounts) {
            $query->with(['botContext', 'firstAction', 'firstRefererAction'])
                ->withCount('actions')
                ->withCount(['actions as order_qty' => fn ($q) => $q->where('action_type', 'payment_success')]);
        }

        $this->applySessionDateFilter($query, $request);

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

        if (! in_array('has_order', $except, true) && $request->filled('has_order')) {
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
     * @return array{real_shoppers: int, automated_traffic: int, not_classified: int}
     */
    private function visitorQualityCounts(Request $request): array
    {
        $base = $this->buildIndexQuery($request, forCounts: true);

        return [
            'real_shoppers' => (clone $base)->whereHas('botContext', fn ($b) => $b->where('is_bot', false))->count(),
            'automated_traffic' => (clone $base)->whereHas('botContext', fn ($b) => $b->where('is_bot', true))->count(),
            'not_classified' => (clone $base)->whereDoesntHave('botContext')->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    private function buildActivityFilterChips(Request $request): array
    {
        $chips = [];
        $labels = VisitorClassificationLabels::filterTypeLabels();

        if ($request->filled('visitor_type')) {
            $chips[] = [
                'label' => $labels[$request->visitor_type] ?? $request->visitor_type,
                'remove_url' => $request->fullUrlWithQuery(['visitor_type' => null, 'page' => null]),
            ];
        }

        if ($request->filled('country')) {
            $chips[] = [
                'label' => 'Country: '.$request->country,
                'remove_url' => $request->fullUrlWithQuery(['country' => null, 'page' => null]),
            ];
        }

        if ($request->filled('search')) {
            $chips[] = [
                'label' => '"'.$request->search.'"',
                'remove_url' => $request->fullUrlWithQuery(['search' => null, 'page' => null]),
            ];
        }

        if ($request->filled('utm_source')) {
            $sourceLabel = TrackerUtmFilter::sources()[$request->utm_source] ?? $request->utm_source;
            $chips[] = [
                'label' => 'Source: '.$sourceLabel,
                'remove_url' => $request->fullUrlWithQuery(['utm_source' => null, 'page' => null]),
            ];
        }

        if ($request->filled('utm_medium')) {
            $mediumLabel = TrackerUtmFilter::mediums()[$request->utm_medium] ?? $request->utm_medium;
            $chips[] = [
                'label' => 'Medium: '.$mediumLabel,
                'remove_url' => $request->fullUrlWithQuery(['utm_medium' => null, 'page' => null]),
            ];
        }

        return $chips;
    }
}
