<?php

namespace App\Services;

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use App\Support\TrackerRedisCache;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitorAnalyticsService
{
    /**
     * @return array<string, string>
     */
    public function visitorSortOptions(): array
    {
        return [
            'last_active_desc' => 'Last active · newest first',
            'last_active_asc' => 'Last active · oldest first',
            'first_seen_desc' => 'First seen · newest first',
            'first_seen_asc' => 'First seen · oldest first',
            'sessions_desc' => 'Most sessions',
            'sessions_asc' => 'Fewest sessions',
            'total_stay_desc' => 'Longest total stay',
            'total_stay_asc' => 'Shortest total stay',
            'avg_stay_desc' => 'Longest avg session',
            'avg_stay_asc' => 'Shortest avg session',
            'orders_desc' => 'Most orders',
            'orders_asc' => 'Fewest orders',
        ];
    }

    public function resolveVisitorSort(?string $sortBy): string
    {
        $options = $this->visitorSortOptions();

        if ($sortBy !== null && array_key_exists($sortBy, $options)) {
            return $sortBy;
        }

        return 'last_active_desc';
    }

    private function effectiveDurationSecondsSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'COALESCE(NULLIF(session_duration_seconds, 0), CAST((strftime(\'%s\', last_active_at) - strftime(\'%s\', created_at)) AS INTEGER), 0)';
        }

        return 'COALESCE(NULLIF(session_duration_seconds, 0), TIMESTAMPDIFF(SECOND, created_at, last_active_at), 0)';
    }

    public function resolveWindow(string $window, ?int $customValue = null): Carbon
    {
        $now = TrackerTime::localNow();

        if ($customValue !== null && $customValue > 0) {
            $from = match ($window) {
                'hours' => $now->copy()->subHours($customValue),
                'days' => $now->copy()->subDays($customValue),
                'weeks' => $now->copy()->subWeeks($customValue),
                'months' => $now->copy()->subMonths($customValue),
                'years' => $now->copy()->subYears($customValue),
                default => $now->copy()->subHours($customValue),
            };

            return $from->utc();
        }

        $from = match ($window) {
            '1h' => $now->copy()->subHour(),
            '3h' => $now->copy()->subHours(3),
            '6h' => $now->copy()->subHours(6),
            '12h' => $now->copy()->subHours(12),
            '24h' => $now->copy()->startOfDay()->addSecond(),
            '1d' => $now->copy()->subDay(),
            '7d' => $now->copy()->subDays(7),
            '14d' => $now->copy()->subDays(14),
            '30d' => $now->copy()->subDays(30),
            '90d' => $now->copy()->subDays(90),
            '1w' => $now->copy()->subWeek(),
            '4w' => $now->copy()->subWeeks(4),
            '12w' => $now->copy()->subWeeks(12),
            '52w' => $now->copy()->subWeeks(52),
            '1m' => $now->copy()->subMonth(),
            '3m' => $now->copy()->subMonths(3),
            '6m' => $now->copy()->subMonths(6),
            '12m' => $now->copy()->subMonths(12),
            '1y' => $now->copy()->subYear(),
            default => $now->copy()->startOfDay()->addSecond(),
        };

        return $from->utc();
    }

    private function applyLastActiveRange($query, Carbon $from, ?Carbon $until = null)
    {
        if ($until !== null) {
            return $query->whereBetween('last_active_at', TrackerTime::storageRange($from, $until));
        }

        return $query->where('last_active_at', '>=', $from);
    }

    private function applyCreatedRange($query, Carbon $from, ?Carbon $until = null)
    {
        if ($until !== null) {
            return $query->whereBetween('created_at', TrackerTime::storageRange($from, $until));
        }

        return $query->where('created_at', '>=', $from);
    }

    public function countActiveVisitors(Carbon $from, ?Carbon $until = null): int
    {
        return (int) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->count('visitor_id');
    }

    public function countNewVisitors(Carbon $from, ?Carbon $until = null): int
    {
        return $this->countUniqueVisitors($from, $until);
    }

    public function countUniqueVisitors(Carbon $from, ?Carbon $until = null): int
    {
        $firstVisits = ActivityEcomUser::query()
            ->select('visitor_id', DB::raw('MIN(created_at) as first_seen_at'))
            ->whereNotNull('visitor_id')
            ->groupBy('visitor_id');

        $query = DB::query()->fromSub($firstVisits, 'first_visits');

        if ($until !== null) {
            $query->whereBetween('first_seen_at', TrackerTime::storageRange($from, $until));
        } else {
            $query->where('first_seen_at', '>=', $from->format('Y-m-d H:i:s'));
        }

        return (int) $query->count();
    }

    public function countReturningVisitors(Carbon $from, ?Carbon $until = null): int
    {
        $sessions = $this->countSessions($from, $until);
        $uniqueVisitors = $this->countUniqueVisitors($from, $until);

        return max(0, $sessions - $uniqueVisitors);
    }

    public function countNewVisitorsInRange(Carbon $from, Carbon $to): int
    {
        return $this->countUniqueVisitors($from, $to);
    }

    public function countSessions(Carbon $from, ?Carbon $until = null): int
    {
        return (int) $this->applyCreatedRange(ActivityEcomUser::query(), $from, $until)->count();
    }

    public function avgSessionDuration(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) round((float) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw('AVG('.$durationSql.') as aggregate')
            ->value('aggregate'));
    }

    public function avgSessionDurationInRange(Carbon $from, Carbon $to): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) round((float) ActivityEcomUser::query()
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereNotNull('visitor_id')
            ->selectRaw('AVG('.$durationSql.') as aggregate')
            ->value('aggregate'));
    }

    public function totalStaySecondsInRange(Carbon $from, Carbon $to): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) ActivityEcomUser::query()
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereNotNull('visitor_id')
            ->selectRaw('SUM('.$durationSql.') as aggregate')
            ->value('aggregate');
    }

    public function avgVisitorStay(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $totals = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->select('visitor_id', DB::raw('SUM('.$durationSql.') as total_stay'))
            ->whereNotNull('visitor_id')
            ->groupBy('visitor_id')
            ->get();

        if ($totals->isEmpty()) {
            return 0;
        }

        return (int) round($totals->avg('total_stay'));
    }

    public function totalStaySeconds(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw('SUM('.$durationSql.') as aggregate')
            ->value('aggregate');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(Carbon $from, ?Carbon $until = null): array
    {
        $avgSessionDuration = $this->avgSessionDuration($from, $until);
        $totalStaySeconds = $this->totalStaySeconds($from, $until);

        $avgVisitorStay = $this->avgVisitorStay($from, $until);

        return [
            'unique_visitors' => $this->countUniqueVisitors($from, $until),
            'returning_visitors' => $this->countReturningVisitors($from, $until),
            'sessions' => $this->countSessions($from, $until),
            'avg_session_duration' => $avgSessionDuration,
            'avg_session_duration_label' => $this->formatDuration($avgSessionDuration),
            'total_stay_seconds' => $totalStaySeconds,
            'total_stay_label' => $this->formatDuration($totalStaySeconds),
            // Legacy keys kept for exports and rolling windows.
            'active_visitors' => $this->countActiveVisitors($from, $until),
            'new_visitors' => $this->countUniqueVisitors($from, $until),
            'avg_visitor_stay' => $avgVisitorStay,
            'avg_visitor_stay_label' => $this->formatDuration($avgVisitorStay),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildRollingWindows(): array
    {
        $windows = config('tracker.analytics_windows', []);
        $cards = [];

        foreach ($windows['hours'] ?? [] as $hours) {
            $since = $this->resolveWindow('hours', $hours);
            $cards[] = array_merge([
                'key' => $hours.'h',
                'label' => 'Last '.$hours.' hour'.($hours === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['days'] ?? [] as $days) {
            $since = $this->resolveWindow('days', $days);
            $cards[] = array_merge([
                'key' => $days.'d',
                'label' => 'Last '.$days.' day'.($days === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['weeks'] ?? [] as $weeks) {
            $since = $this->resolveWindow('weeks', $weeks);
            $cards[] = array_merge([
                'key' => $weeks.'w',
                'label' => 'Last '.$weeks.' week'.($weeks === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['months'] ?? [] as $months) {
            $since = $this->resolveWindow('months', $months);
            $cards[] = array_merge([
                'key' => $months.'m',
                'label' => 'Last '.$months.' month'.($months === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['years'] ?? [] as $years) {
            $since = $this->resolveWindow('years', $years);
            $cards[] = array_merge([
                'key' => $years.'y',
                'label' => 'Last '.$years.' year'.($years === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWindowCard(Carbon $since): array
    {
        $avgStay = $this->avgSessionDuration($since);

        return [
            'unique_visitors' => $this->countUniqueVisitors($since),
            'returning_visitors' => $this->countReturningVisitors($since),
            'sessions' => $this->countSessions($since),
            'avg_stay_seconds' => $avgStay,
            'avg_stay_label' => $this->formatDuration($avgStay),
            'total_stay_seconds' => $this->totalStaySeconds($since),
            'total_stay_label' => $this->formatDuration($this->totalStaySeconds($since)),
            'active_visitors' => $this->countActiveVisitors($since),
            'new_visitors' => $this->countUniqueVisitors($since),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOverview(Carbon $from, ?Carbon $until = null): array
    {
        $summary = $this->buildSummary($from, $until);
        $durationBuckets = $this->buildDurationBuckets($from, $until);
        $newReturning = $this->buildNewVsReturning($from, $until);
        $trend = $this->buildVisitorTrend($from, $until);
        $topVisitors = $this->buildVisitorBreakdown($from, 5, $until);

        return array_merge($summary, [
            'duration_buckets' => $durationBuckets,
            'new_returning' => $newReturning,
            'trend' => $trend,
            'top_visitors' => $topVisitors->items(),
        ]);
    }

    /**
     * @return array{overview: array<string, mixed>, analytics_cache: array<string, mixed>}
     */
    public function getCachedOverview(Carbon $from, ?Carbon $until = null): array
    {
        $cache = app(TrackerRedisCache::class);
        $ttl = (int) config('tracker.analytics_cache_ttl_seconds', 300);
        $cacheKey = 'visitors:overview:'.hash('sha256', json_encode($this->cacheIdentity($from, $until)));

        $cached = $cache->remember($cacheKey, $ttl, fn () => $this->buildOverview($from, $until));

        return [
            'overview' => $cache->payload($cached),
            'analytics_cache' => $this->analyticsCacheMeta($cache, $cached, $ttl),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, analytics_cache: array<string, mixed>}
     */
    public function getCachedSummary(Carbon $from, ?Carbon $until = null): array
    {
        $cache = app(TrackerRedisCache::class);
        $ttl = (int) config('tracker.analytics_cache_ttl_seconds', 300);
        $cacheKey = 'visitors:summary:'.hash('sha256', json_encode($this->cacheIdentity($from, $until)));

        $cached = $cache->remember($cacheKey, $ttl, fn () => $this->buildSummary($from, $until));

        return [
            'summary' => $cache->payload($cached),
            'analytics_cache' => $this->analyticsCacheMeta($cache, $cached, $ttl),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheIdentity(Carbon $from, ?Carbon $until = null): array
    {
        return [
            'from' => $from->toIso8601String(),
            'until' => $until?->toIso8601String(),
        ];
    }

    /**
     * @return array{enabled: bool, cached_at: ?string, ttl_seconds: int}
     */
    private function analyticsCacheMeta(TrackerRedisCache $cache, array $cached, int $ttl): array
    {
        return [
            'enabled' => (bool) config('tracker.analytics_cache_enabled', true),
            'cached_at' => $cache->cachedAt($cached),
            'ttl_seconds' => $ttl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildVisitorTrend(Carbon $from, ?Carbon $until = null): array
    {
        $to = $until ?? TrackerTime::nowUtc();
        $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay();
        $toLocal = TrackerTime::toLocal($to)?->copy()->endOfDay();

        if ($fromLocal === null || $toLocal === null) {
            return ['labels' => [], 'visitors' => [], 'sessions' => []];
        }

        $labels = [];
        $visitors = [];
        $sessions = [];
        $cursor = $fromLocal->copy();

        while ($cursor <= $toLocal) {
            $dayStart = $cursor->copy()->startOfDay()->utc();
            $dayEnd = $cursor->copy()->endOfDay()->utc();
            $labels[] = $cursor->format('d M');

            $visitors[] = $this->countUniqueVisitors($dayStart, $dayEnd);
            $sessions[] = $this->countSessions($dayStart, $dayEnd);

            $cursor->addDay();
        }

        return compact('labels', 'visitors', 'sessions');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildNewVsReturning(Carbon $from, ?Carbon $until = null): array
    {
        $uniqueVisitors = $this->countUniqueVisitors($from, $until);
        $returningVisitors = $this->countReturningVisitors($from, $until);

        return [
            'unique' => $uniqueVisitors,
            'returning' => $returningVisitors,
            'new' => $uniqueVisitors,
            'labels' => ['Unique', 'Returning'],
            'values' => [$uniqueVisitors, $returningVisitors],
        ];
    }

    public function buildVisitorBreakdown(Carbon $from, int $perPage = 25, ?Carbon $until = null, array $filters = []): LengthAwarePaginator
    {
        $durationSql = $this->effectiveDurationSecondsSql();
        $sortBy = $this->resolveVisitorSort($filters['sort_by'] ?? null);
        $to = $until ?? TrackerTime::nowUtc();

        $query = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->select([
                'visitor_id',
                DB::raw('COUNT(*) as session_count'),
                DB::raw('SUM('.$durationSql.') as total_stay_seconds'),
                DB::raw('AVG('.$durationSql.') as avg_stay_seconds'),
                DB::raw('MIN(created_at) as first_seen_at'),
                DB::raw('MAX(last_active_at) as last_active_at'),
            ])
            ->selectSub($this->visitorOrderQtySubquery($from, $to), 'order_qty')
            ->whereNotNull('visitor_id')
            ->groupBy('visitor_id');

        $this->applyVisitorBreakdownSort($query, $sortBy, $durationSql);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where('visitor_id', 'like', $search);
        }

        if (! empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (isset($filters['logged_in']) && $filters['logged_in'] !== '' && $filters['logged_in'] !== null) {
            $query->where('is_logged_in', (bool) $filters['logged_in']);
        }

        if (isset($filters['has_order']) && $filters['has_order'] !== '' && $filters['has_order'] !== null) {
            $hasOrder = (bool) $filters['has_order'];
            $exists = fn ($sub) => $sub->selectRaw('1')
                ->from('activity_ecom_user as purchase_sessions')
                ->whereColumn('purchase_sessions.visitor_id', 'activity_ecom_user.visitor_id')
                ->where('purchase_sessions.has_payment_success', true)
                ->whereBetween('purchase_sessions.first_payment_at', TrackerTime::storageRange($from, $to));

            if ($hasOrder) {
                $query->whereExists($exists);
            } else {
                $query->whereNotExists($exists);
            }
        }

        TrackerUtmFilter::applySourceFilter($query, $filters['utm_source'] ?? null);
        TrackerUtmFilter::applyMediumFilter($query, $filters['utm_medium'] ?? null);

        $paginator = $query->paginate($perPage)->withQueryString();

        $visitorIds = $paginator->getCollection()->pluck('visitor_id')->filter()->unique()->values();
        $latestSessions = $this->latestSessionsForVisitors($visitorIds);

        $paginator->getCollection()->transform(function ($row) use ($latestSessions) {
            $latest = $latestSessions->get($row->visitor_id);

            $totalStay = (int) $row->total_stay_seconds;
            $avgStay = (int) round((float) $row->avg_stay_seconds);

            return [
                'visitor_id' => $row->visitor_id,
                'session_count' => (int) $row->session_count,
                'total_stay_seconds' => $totalStay,
                'total_stay_label' => $this->formatDuration($totalStay),
                'avg_stay_seconds' => $avgStay,
                'avg_stay_label' => $this->formatDuration($avgStay),
                'first_seen_at' => $row->first_seen_at,
                'last_active_at' => $row->last_active_at,
                'order_qty' => (int) ($row->order_qty ?? 0),
                'device_type' => $latest?->device_type,
                'browser' => $latest?->browser,
                'latest_session' => $latest,
            ];
        });

        return $paginator;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $visitorIds
     * @return \Illuminate\Support\Collection<string, ActivityEcomUser>
     */
    private function latestSessionsForVisitors(Collection $visitorIds): Collection
    {
        if ($visitorIds->isEmpty()) {
            return collect();
        }

        return ActivityEcomUser::query()
            ->with('botContext')
            ->whereIn('visitor_id', $visitorIds)
            ->orderByDesc('last_active_at')
            ->get()
            ->unique('visitor_id')
            ->keyBy('visitor_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\ActivityEcomUser>  $query
     */
    private function applyVisitorBreakdownSort($query, string $sortBy, string $durationSql): void
    {
        match ($sortBy) {
            'last_active_asc' => $query->orderBy(DB::raw('MAX(last_active_at)')),
            'first_seen_desc' => $query->orderByDesc(DB::raw('MIN(created_at)')),
            'first_seen_asc' => $query->orderBy(DB::raw('MIN(created_at)')),
            'sessions_desc' => $query->orderByDesc(DB::raw('COUNT(*)')),
            'sessions_asc' => $query->orderBy(DB::raw('COUNT(*)')),
            'total_stay_desc' => $query->orderByDesc(DB::raw('SUM('.$durationSql.')')),
            'total_stay_asc' => $query->orderBy(DB::raw('SUM('.$durationSql.')')),
            'avg_stay_desc' => $query->orderByDesc(DB::raw('AVG('.$durationSql.')')),
            'avg_stay_asc' => $query->orderBy(DB::raw('AVG('.$durationSql.')')),
            'orders_desc' => $query->orderByDesc('order_qty'),
            'orders_asc' => $query->orderBy('order_qty'),
            default => $query->orderByDesc(DB::raw('MAX(last_active_at)')),
        };
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private function visitorOrderQtySubquery(Carbon $from, Carbon $to): \Closure
    {
        return function ($sub) use ($from, $to): void {
            $sub->from('activity_ecom_orders as orders')
                ->join('activity_ecom_user as order_sessions', 'order_sessions.session_id', '=', 'orders.session_id')
                ->whereColumn('order_sessions.visitor_id', 'activity_ecom_user.visitor_id')
                ->whereBetween('orders.ordered_at', [
                    TrackerTime::formatUtc($from),
                    TrackerTime::formatUtc($to),
                ])
                ->selectRaw('count(*)');
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildDurationBuckets(Carbon $from, ?Carbon $until = null): array
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $sessions = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw($durationSql.' as duration_seconds')
            ->pluck('duration_seconds');

        return \App\Support\SessionDurationBuckets::withCounts($sessions)['buckets'];
    }

    /**
     * @return array{
     *     buckets: array<int, array{label: string, min: int, max: int, count: int, pct: float}>,
     *     total_sessions: int,
     *     median_seconds: int,
     *     median_label: string
     * }
     */
    public function buildDurationDistribution(Carbon $from, ?Carbon $until = null): array
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $sessions = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw($durationSql.' as duration_seconds')
            ->pluck('duration_seconds');

        $distribution = \App\Support\SessionDurationBuckets::withCounts($sessions);

        return [
            'buckets' => $distribution['buckets'],
            'total_sessions' => $distribution['total_sessions'],
            'median_seconds' => $distribution['median_seconds'],
            'median_label' => $this->formatDuration($distribution['median_seconds']),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function buildExportRows(Carbon $from, ?Carbon $until = null): array
    {
        $summary = $this->buildSummary($from, $until);
        $trend = $this->buildVisitorTrend($from, $until);
        $newReturning = $this->buildNewVsReturning($from, $until);
        $durationBuckets = $this->buildDurationBuckets($from, $until);
        $visitors = $this->buildVisitorBreakdown($from, 10000, $until)->items();

        return [
            'kpis' => [
                ['metric' => 'Unique visitors', 'value' => $summary['unique_visitors'], 'formatted' => number_format($summary['unique_visitors'])],
                ['metric' => 'Returning visitors', 'value' => $summary['returning_visitors'], 'formatted' => number_format($summary['returning_visitors'])],
                ['metric' => 'Sessions', 'value' => $summary['sessions'], 'formatted' => number_format($summary['sessions'])],
                ['metric' => 'Avg session duration', 'value' => $summary['avg_session_duration'], 'formatted' => $summary['avg_session_duration_label']],
                ['metric' => 'Total time on site', 'value' => $summary['total_stay_seconds'], 'formatted' => $summary['total_stay_label']],
            ],
            'trend' => collect($trend['labels'])->map(function (string $label, int $index) use ($trend) {
                return [
                    'date' => $label,
                    'unique_visitors' => $trend['visitors'][$index] ?? 0,
                    'sessions' => $trend['sessions'][$index] ?? 0,
                ];
            })->values()->all(),
            'new_returning' => [
                ['segment' => 'Unique visitors', 'count' => $newReturning['unique']],
                ['segment' => 'Returning visitors', 'count' => $newReturning['returning']],
            ],
            'duration' => collect($durationBuckets)->map(fn (array $bucket) => [
                'duration_bucket' => $bucket['label'],
                'sessions' => $bucket['count'],
            ])->values()->all(),
            'visitors' => collect($visitors)->map(function (array $row) {
                return [
                    'visitor_id' => $row['visitor_id'],
                    'sessions' => $row['session_count'],
                    'orders' => $row['order_qty'],
                    'total_stay' => $row['total_stay_label'],
                    'avg_stay' => $row['avg_stay_label'],
                    'first_seen' => TrackerTime::formatFromStorage($row['first_seen_at']) ?? '',
                    'last_active' => TrackerTime::formatFromStorage($row['last_active_at']) ?? '',
                    'device' => $row['device_type'] ?? '',
                    'browser' => $row['browser'] ?? '',
                ];
            })->values()->all(),
        ];
    }

    public function formatDuration(int $seconds): string
    {
        return format_duration($seconds);
    }
}
