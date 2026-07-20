<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomActivityTimelinePresenter;
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

        $query = ActivityEcomUser::query()
            ->withCount('actions');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                    ->orWhere('visitor_id', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
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

        $sessions = $query
            ->orderByDesc('last_active_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('ecom_activity.index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, string $session, EcomActivityTimelinePresenter $timelinePresenter): View
    {
        Gate::authorize('ecom_tracker.activity.show');

        $activityUser = ActivityEcomUser::query()
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

        $timeline = new LengthAwarePaginator(
            $items,
            $total,
            self::TIMELINE_PER_PAGE,
            $page,
            [
                'path' => route('admin.ecom-activity.show', ['session' => $session]),
                'pageName' => 'timeline_page',
            ],
        );

        $timeline->appends($request->except('timeline_page'));

        $returnQuery = $request->only(['search', 'date_from', 'date_to', 'device_type', 'logged_in', 'has_order', 'page']);

        return view('ecom_activity.show', [
            'activityUser' => $activityUser,
            'timeline' => $timeline,
            'funnelSteps' => self::FUNNEL_STEPS,
            'reachedSteps' => $reachedSteps,
            'returnQuery' => $returnQuery,
        ]);
    }
}
