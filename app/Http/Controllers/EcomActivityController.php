<?php

namespace App\Http\Controllers;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomActivityListService;
use App\Support\EcomTrackerLogger;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    private const BADGE_COLORS = [
        'category_view' => 'badge-blue',
        'product_view' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        'product_view_popup' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
        'grid_impression' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-200',
        'product_click' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-200',
        'add_to_cart' => 'badge-amber',
        'begin_checkout' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
        'proceed_checkout' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'payment_success' => 'badge-green',
    ];

    public function index(Request $request, EcomActivityListService $activityList): View
    {
        Gate::authorize('ecom_tracker.activity.index');

        $period = EcomActivityListService::normalizePeriod($request->query('period'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );

        $data = [
            'period' => $period,
            'dateFrom' => $range['from']->toDateString(),
            'dateTo' => $range['to']->toDateString(),
            'rangeLabel' => TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
        ];

        $sortQuery = $request->except(['period', 'date_from', 'date_to', 'page']);
        $data['keepQuery'] = $sortQuery;
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $nextQuery = EcomActivityListService::shiftedPeriodQuery($range, 1);
        $data['prevUrl'] = $prevQuery ? route('admin.ecom-activity.index', $prevQuery + $sortQuery) : null;
        $data['nextUrl'] = $nextQuery ? route('admin.ecom-activity.index', $nextQuery + $sortQuery) : null;
        $data['backUrl'] = EcomActivityListService::backUrl($request, route('admin.ecom-tracker.dashboard'));
        $data['resetUrl'] = route('admin.ecom-activity.index', array_filter(['back' => $request->query('back')]));

        $sortBy = EcomActivityListService::normalizeSort($request->query('sort_by'));
        $sortOptions = EcomActivityListService::SORT_OPTIONS;
        $filters = EcomActivityListService::filtersFromRequest($request);

        $filterOptions = $activityList->filterOptions($filters['department']);

        if ($filters['department'] && $filters['categories'] === []) {
            $filters['categories'] = [$filters['department'], ...array_keys($filterOptions['categories'])];
        }

        $sessions = $activityList->paginate($range, $filters, $sortBy, 25);

        return view('ecom_activity.index', compact('data', 'sessions', 'sortBy', 'sortOptions', 'filterOptions'));
    }

    public function categories(Request $request, EcomActivityListService $activityList): JsonResponse
    {
        Gate::authorize('ecom_tracker.activity.index');

        $categories = $activityList->departmentCategories($request->integer('department'));

        return response()->json(collect($categories)->map(fn (string $name, int $id) => ['id' => $id, 'name' => $name])->values());
    }

    public function show(Request $request, string $session): View
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

        $fullTimeline = $this->buildTimeline(
            $actions->filter(
                fn (ActivityEcomUserAction $action) => $action->action_type !== 'product_click',
            ),
        );
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

        $showRouteParams = $this->activityShowRouteParams($session, $request->input('back'));

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

        $backUrl = EcomActivityListService::backUrl($request, route('admin.ecom-activity.index'));

        EcomTrackerLogger::backend()->info('analytics.activity.show', 'Admin opened one user session', [
            'session_id' => $session,
            'action_count' => $actions->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $trafficAttribution = SessionTrafficAttribution::displayFields($activityUser, $actions);
        $conversionAttribution = SessionTrafficAttribution::conversionOrMarketingDisplayFields($activityUser);
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
            'conversionAttribution' => $conversionAttribution,
            'landingPage' => $landingPage,
            'latestActionAt' => $latestActionAt,
            'relatedVisitorSessions' => $this->relatedVisitorSessions($activityUser),
            'badgeColors' => self::BADGE_COLORS,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function activityShowRouteParams(string $sessionId, mixed $back): array
    {
        $params = ['session' => $sessionId];

        if (is_string($back) && $back !== '') {
            $params['back'] = $back;
        }

        return $params;
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return Collection<int, object>
     */
    private function buildTimeline(Collection $actions): Collection
    {
        $sorted = $actions
            ->sortBy(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->start_time?->timestamp ?? 0,
                $action->id,
            ])
            ->values();

        $timeline = collect();
        $index = 0;

        while ($index < $sorted->count()) {
            /** @var ActivityEcomUserAction $action */
            $action = $sorted[$index];

            if ($action->action_type === 'grid_impression') {
                $group = collect([$action]);
                $pageKey = $this->timelineGridPageKey($action);
                $index++;

                while ($index < $sorted->count()) {
                    /** @var ActivityEcomUserAction $next */
                    $next = $sorted[$index];

                    if ($next->action_type !== 'grid_impression' || $this->timelineGridPageKey($next) !== $pageKey) {
                        break;
                    }

                    $group->push($next);
                    $index++;
                }

                $timeline->push($group->count() === 1
                    ? $this->wrapSingleGridTimelineItem($group->first())
                    : $this->wrapGroupedGridImpressionTimelineItem($group));

                continue;
            }

            if ($action->action_type === 'grid_click') {
                $timeline->push($this->wrapSingleGridTimelineItem($action));
                $index++;

                continue;
            }

            if (! in_array($action->action_type, ['product_view', 'product_view_popup'], true)) {
                $timeline->push($this->wrapSingleTimelineItem($action));
                $index++;

                continue;
            }

            $group = collect([$action]);
            $productKey = $this->timelineProductKey($action);
            $index++;

            while ($index < $sorted->count()) {
                /** @var ActivityEcomUserAction $next */
                $next = $sorted[$index];

                if (
                    ! in_array($next->action_type, ['product_view', 'product_view_popup'], true)
                    || $this->timelineProductKey($next) !== $productKey
                    || $action->action_type !== $next->action_type
                ) {
                    break;
                }

                $group->push($next);
                $index++;
            }

            $timeline->push($group->count() === 1
                ? $this->wrapSingleTimelineItem($group->first())
                : $this->wrapGroupedProductViewTimelineItem($group));
        }

        return $timeline
            ->sortByDesc(fn (object $item) => [
                $item->created_at?->timestamp ?? 0,
                $item->id ?? 0,
            ])
            ->values();
    }

    private function wrapSingleTimelineItem(ActivityEcomUserAction $action): object
    {
        $dwellSeconds = $this->actionDwellSeconds($action);
        $colorName = $action->general_color_name ?: 'Unknown';

        return (object) [
            'id' => $action->id,
            'is_grouped_product_view' => false,
            'action_type' => $action->action_type,
            'action' => $action,
            'actions' => collect([$action]),
            'category_name' => $action->category_name,
            'category_code' => $action->category_code,
            'product_name' => $action->product_name,
            'product_code' => $action->product_code,
            'sku' => $action->sku,
            'product_price' => $action->product_price,
            'referer' => $action->referer,
            'page_url' => $action->page_url,
            'start_time' => $action->start_time,
            'end_time' => $action->end_time,
            'created_at' => $action->created_at ?? $action->start_time,
            'dwell_seconds' => $dwellSeconds,
            'color_timeline' => $dwellSeconds !== null
                ? sprintf('%s (%ds)', $colorName, $dwellSeconds)
                : $colorName,
            'add_to_cart' => $action->add_to_cart,
            'begin_checkout' => $action->begin_checkout,
            'proceed_to_checkout' => $action->proceed_to_checkout,
            'payment_success' => $action->payment_success,
        ];
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $group
     */
    private function wrapGroupedProductViewTimelineItem(Collection $group): object
    {
        $segments = $group->map(function (ActivityEcomUserAction $action) {
            return [
                'name' => $action->general_color_name ?: 'Unknown',
                'seconds' => $this->actionDwellSeconds($action),
            ];
        });

        $colorTimeline = $segments
            ->map(function (array $segment) {
                if ($segment['seconds'] === null) {
                    return $segment['name'];
                }

                return sprintf('%s (%ds)', $segment['name'], $segment['seconds']);
            })
            ->join(' → ');

        $totalDwell = $segments
            ->pluck('seconds')
            ->filter(fn ($seconds) => $seconds !== null)
            ->sum();

        $first = $group->first();
        $last = $group->last();

        return (object) [
            'id' => $first->id,
            'is_grouped_product_view' => true,
            'action_type' => $first->action_type,
            'action' => $first,
            'actions' => $group->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->start_time?->timestamp ?? 0,
                $action->id,
            ])->values(),
            'category_name' => null,
            'category_code' => null,
            'product_name' => $first->product_name,
            'product_code' => $first->product_code,
            'sku' => $first->sku,
            'product_price' => $last->product_price,
            'referer' => $first->referer,
            'page_url' => $last->page_url,
            'start_time' => $first->start_time,
            'end_time' => $last->end_time,
            'created_at' => $first->created_at ?? $first->start_time,
            'dwell_seconds' => $totalDwell > 0 ? $totalDwell : null,
            'color_timeline' => $colorTimeline,
            'add_to_cart' => null,
            'begin_checkout' => null,
            'proceed_to_checkout' => null,
            'payment_success' => null,
        ];
    }

    private function wrapSingleGridTimelineItem(ActivityEcomUserAction $action): object
    {
        $sku = $this->timelineGridSkuLabel($action);

        return (object) array_merge((array) $this->wrapSingleTimelineItem($action), [
            'is_grouped_grid_impression' => false,
            'sku_timeline' => $sku,
        ]);
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $group
     */
    private function wrapGroupedGridImpressionTimelineItem(Collection $group): object
    {
        $ordered = $group
            ->sortBy(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])
            ->values();

        $segments = $ordered->map(function (ActivityEcomUserAction $action, int $index) use ($ordered) {
            $next = $ordered->get($index + 1);

            return [
                'name' => $this->timelineGridSkuLabel($action),
                'seconds' => $next ? $this->timelineGridGapSeconds($action, $next) : null,
            ];
        });

        $skuTimeline = $segments
            ->pluck('name')
            ->join(' → ');

        $totalGap = $segments
            ->pluck('seconds')
            ->filter(fn ($seconds) => $seconds !== null)
            ->sum();

        $first = $ordered->first();
        $last = $ordered->last();

        return (object) [
            'id' => $first->id,
            'is_grouped_product_view' => false,
            'is_grouped_grid_impression' => true,
            'action_type' => 'grid_impression',
            'action' => $first,
            'actions' => $ordered->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])->values(),
            'category_name' => $first->category_name,
            'category_code' => $first->category_code,
            'product_name' => null,
            'product_code' => null,
            'sku' => null,
            'product_price' => null,
            'referer' => $first->referer,
            'page_url' => $last->page_url,
            'start_time' => null,
            'end_time' => null,
            'created_at' => $first->created_at,
            'dwell_seconds' => $totalGap > 0 ? $totalGap : null,
            'color_timeline' => null,
            'sku_timeline' => $skuTimeline,
            'add_to_cart' => null,
            'begin_checkout' => null,
            'proceed_to_checkout' => null,
            'payment_success' => null,
        ];
    }

    private function timelineGridSkuLabel(ActivityEcomUserAction $action): string
    {
        $productCode = trim((string) ($action->product_code ?? ''));
        $legacyStyleCode = trim((string) ($action->sku ?? ''));

        if ($productCode !== '') {
            return $productCode;
        }

        return $legacyStyleCode !== '' ? $legacyStyleCode : 'Unknown';
    }

    private function timelineGridPageKey(ActivityEcomUserAction $action): string
    {
        $category = trim((string) ($action->category_code ?? $action->category_name ?? ''));

        return $this->timelineProductPathKey($action->page_url).'|'.$category;
    }

    private function timelineGridGapSeconds(ActivityEcomUserAction $action, ActivityEcomUserAction $next): int
    {
        if (! $action->created_at || ! $next->created_at) {
            return 0;
        }

        return max(0, (int) $action->created_at->diffInSeconds($next->created_at));
    }

    private function timelineProductKey(ActivityEcomUserAction $action): string
    {
        if (! empty($action->product_code)) {
            return 'code:'.$action->product_code;
        }

        return 'url:'.$this->timelineProductPathKey($action->page_url);
    }

    private function timelineProductPathKey(?string $url): string
    {
        if (! $url) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return rtrim($path, '/');
    }

    private function actionDwellSeconds(ActivityEcomUserAction $action): ?int
    {
        if (! $action->start_time || ! $action->end_time) {
            return null;
        }

        return (int) $action->start_time->diffInSeconds($action->end_time);
    }

    /**
     * @return Collection<int, ActivityEcomUser>
     */
    private function relatedVisitorSessions(ActivityEcomUser $session, int $limit = 20): Collection
    {
        $visitorId = trim((string) ($session->visitor_id ?? ''));

        if ($visitorId === '') {
            return collect();
        }

        return ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get([
                'session_id',
                'created_at',
                'last_active_at',
            ]);
    }
}
