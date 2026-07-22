<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Services\EcomActivityTimelinePresenter;
use App\Support\EcomTrackerViewData;
use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Illuminate\Contracts\View\View;
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
        Gate::authorize('ecom_tracker.activity.index');

        $query = $this->buildIndexQuery($request);

        $sessions = (clone $query)
            ->orderByDesc('last_active_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $visitorQualitySummary = $this->visitorQualityCounts($request);

        return view('ecom_activity.index', [
            'sessions' => $sessions,
            'visitorQualitySummary' => $visitorQualitySummary,
            'filterChips' => $this->buildActivityFilterChips($request),
        ]);
    }

    public function show(Request $request, string $session, EcomActivityTimelinePresenter $timelinePresenter): View
    {
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

        return view('ecom_activity.show', [
            'activityUser' => $activityUser,
            'timeline' => $timeline,
            'funnelSteps' => self::FUNNEL_STEPS,
            'reachedSteps' => $reachedSteps,
            'backUrl' => $backUrl,
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

        $from = TrackerTime::localNow()->subHours(24)->utc();
        $to = TrackerTime::nowUtc();

        $query->where(function ($inner) use ($from, $to) {
            $inner->whereBetween('created_at', [$from, $to])
                ->orWhereBetween('last_active_at', [$from, $to]);
        });
    }

    private function buildIndexQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = ActivityEcomUser::query()
            ->with('botContext')
            ->withCount('actions')
            ->withCount(['actions as order_qty' => fn ($q) => $q->where('action_type', 'payment_success')]);

        $this->applySessionDateFilter($query, $request);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                    ->orWhere('visitor_id', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhereHas('botContext', fn ($b) => $b
                        ->where('client_ip', 'like', "%{$search}%")
                        ->orWhere('ip_country', 'like', "%{$search}%")
                        ->orWhere('cf_ray', 'like', "%{$search}%")
                        ->orWhere('bot_reason', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('country')) {
            $query->where(function ($q) use ($request) {
                $q->where('country', $request->country)
                    ->orWhereHas('botContext', fn ($b) => $b->where('ip_country', $request->country));
            });
        }

        $visitorType = $request->input('visitor_type');

        if ($visitorType === 'bot') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', true));
        } elseif ($visitorType === 'human') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', false));
        } elseif ($visitorType === 'unclassified') {
            $query->whereDoesntHave('botContext');
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        if ($request->filled('logged_in')) {
            $query->where('is_logged_in', $request->logged_in === '1');
        }

        if ($request->filled('has_order')) {
            if ($request->has_order === '1') {
                $query->whereHas('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            } elseif ($request->has_order === '0') {
                $query->whereDoesntHave('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            }
        }

        TrackerUtmFilter::applySourceFilter($query, $request->input('utm_source'));
        TrackerUtmFilter::applyMediumFilter($query, $request->input('utm_medium'));

        return $query;
    }

    /**
     * @return array{real_shoppers: int, automated_traffic: int, not_classified: int}
     */
    private function visitorQualityCounts(Request $request): array
    {
        $base = $this->buildIndexQuery($request);

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

        return $chips;
    }
}
