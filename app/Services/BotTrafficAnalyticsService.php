<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserBotContext;
use App\Models\TrackerUtmFilter;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerRedisSupport;
use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bot traffic analytics with GA4-style comparison ranges.
 *
 * Caching: aggregated portions use Cache::remember (3 min hub, 5 min summaryOnly).
 * Session paginator is always queried live.
 */
class BotTrafficAnalyticsService
{
    private const CACHE_TTL_HUB_SECONDS = 180;

    private const CACHE_TTL_SUMMARY_SECONDS = 300;

    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildReport(array $filters): array
    {
        $startedAt = microtime(true);
        $currentRange = $this->resolveRange($filters);
        $compareMode = $filters['compare'] ?? 'none';
        $comparisonRange = $this->resolveComparisonRange($currentRange, $compareMode);

        $cacheKey = 'bot_traffic_report:v2:' . md5(json_encode([
            'from' => $currentRange['from']->toIso8601String(),
            'to' => $currentRange['to']->toIso8601String(),
            'compare' => $compareMode,
            'country' => $filters['country'] ?? '',
            'search' => $filters['search'] ?? '',
            'device_type' => $filters['device_type'] ?? '',
            'logged_in' => $filters['logged_in'] ?? '',
            'has_order' => $filters['has_order'] ?? '',
            'utm_source' => $filters['utm_source'] ?? '',
            'utm_medium' => $filters['utm_medium'] ?? '',
        ]));

        $aggregated = $this->remember($cacheKey, self::CACHE_TTL_HUB_SECONDS, function () use ($currentRange, $comparisonRange, $compareMode, $filters) {
            return [
                'summary' => $this->buildBotPageSummary($currentRange, $comparisonRange, $compareMode, $filters),
                'trend' => $this->buildTrend($currentRange, $filters),
                'reason_breakdown' => $this->buildReasonBreakdown($currentRange, $filters),
                'country_breakdown' => $this->buildCountryBreakdown($currentRange, $filters),
            ];
        });

        $sessions = $this->paginateSessions($currentRange, $filters);

        EcomTrackerLogger::backend()->info('analytics.bot_traffic.build', 'Bot traffic data ready', [
            'from' => $currentRange['from']->toIso8601String(),
            'to' => $currentRange['to']->toIso8601String(),
            'session_count' => $sessions->total(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return array_merge($aggregated, [
            'range' => $currentRange,
            'comparison_range' => $comparisonRange,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Compact summary for dashboard / visitor analytics strips (no comparison deltas in v1).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summaryOnly(array $filters): array
    {
        $currentRange = $this->resolveRange($filters);
        $emptyComparison = [
            'from' => $currentRange['from'],
            'to' => $currentRange['to'],
            'label' => '',
            'mode' => 'none',
        ];

        $cacheKey = 'bot_traffic_summary:v3:' . md5(json_encode([
            'from' => $currentRange['from']->toIso8601String(),
            'to' => $currentRange['to']->toIso8601String(),
            'device_type' => $filters['device_type'] ?? '',
            'country' => $filters['country'] ?? '',
            'logged_in' => $filters['logged_in'] ?? '',
            'has_order' => $filters['has_order'] ?? '',
            'utm_source' => $filters['utm_source'] ?? '',
            'utm_medium' => $filters['utm_medium'] ?? '',
        ]));

        return $this->remember($cacheKey, self::CACHE_TTL_SUMMARY_SECONDS, function () use ($currentRange, $emptyComparison, $filters) {
            return $this->buildSummary($currentRange, $emptyComparison, 'none', $filters);
        });
    }

    /**
     * @return array{current: int, compare: int, delta_pct: ?float, delta_direction: ?string, delta_label: ?string, sparkline: array<int, int>}
     */
    public function computeMetricSummary(int $current, int $compare, array $sparkline, string $compareMode): array
    {
        $delta = $this->computeDelta($current, $compare, $compareMode);

        return array_merge($delta, [
            'current' => $current,
            'compare' => $compare,
            'sparkline' => $sparkline,
        ]);
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: ?string, delta_label: ?string}
     */
    public function computeDelta(int $current, int $compare, string $compareMode = 'previous_period'): array
    {
        if ($compareMode === 'none') {
            return ['delta_pct' => null, 'delta_direction' => null, 'delta_label' => null];
        }

        if ($compare === 0) {
            if ($current > 0) {
                return ['delta_pct' => null, 'delta_direction' => null, 'delta_label' => 'new'];
            }

            return ['delta_pct' => null, 'delta_direction' => null, 'delta_label' => 'no_prior_data'];
        }

        if ($current === 0) {
            return ['delta_pct' => -100.0, 'delta_direction' => 'down', 'delta_label' => null];
        }

        $deltaPct = (($current - $compare) / $compare) * 100;

        return [
            'delta_pct' => round($deltaPct, 1),
            'delta_direction' => $deltaPct > 0 ? 'up' : ($deltaPct < 0 ? 'down' : 'flat'),
            'delta_label' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @return array{from: Carbon, to: Carbon, label: string, mode: string}
     */
    public function resolveComparisonRange(array $currentRange, string $mode): array
    {
        if ($mode === 'none') {
            return [
                'from' => $currentRange['from'],
                'to' => $currentRange['to'],
                'label' => '',
                'mode' => 'none',
            ];
        }

        $from = $currentRange['from'];
        $to = $currentRange['to'];
        $seconds = (int) $from->diffInSeconds($to);

        if ($mode === 'previous_year') {
            return [
                'from' => $from->copy()->subYear(),
                'to' => $to->copy()->subYear(),
                'label' => 'Previous year',
                'mode' => 'previous_year',
            ];
        }

        return [
            'from' => $from->copy()->subSeconds($seconds + 1),
            'to' => $from->copy()->subSecond(),
            'label' => 'Previous period',
            'mode' => 'previous_period',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: Carbon, to: Carbon, label: string, period: string}
     */
    public function resolveRange(array $filters): array
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return $this->dashboardService->resolveDateRange([
                'period' => 'custom',
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ]);
        }

        return $this->dashboardService->resolveDateRange([
            'period' => $filters['period'] ?? '7d',
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $comparisonRange
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function buildSummary(array $currentRange, array $comparisonRange, string $compareMode, array $filters): array
    {
        $comparisonLabel = $comparisonRange['label'] ?? '';
        $currentCounts = $this->countVisitorQualityMetrics($currentRange['from'], $currentRange['to'], $filters);
        $compareCounts = $compareMode === 'none'
            ? [
                'real_shoppers' => 0,
                'automated_traffic' => 0,
                'not_classified' => 0,
            ]
            : $this->countVisitorQualityMetrics($comparisonRange['from'], $comparisonRange['to'], $filters);

        $summary = [];

        foreach ($currentCounts as $key => $current) {
            $summary[$key] = array_merge(
                $this->computeMetricSummary($current, (int) $compareCounts[$key], [], $compareMode),
                ['comparison_label' => $comparisonLabel],
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $comparisonRange
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function buildBotPageSummary(array $currentRange, array $comparisonRange, string $compareMode, array $filters): array
    {
        $comparisonLabel = $comparisonRange['label'] ?? '';

        $metrics = [
            'automated_traffic' => fn (Carbon $from, Carbon $to) => $this->countBotSessions($from, $to, $filters),
            'bot_countries' => fn (Carbon $from, Carbon $to) => $this->countBotCountries($from, $to, $filters),
        ];

        $summary = [];

        foreach ($metrics as $key => $counter) {
            $current = $counter($currentRange['from'], $currentRange['to']);
            $compare = $compareMode === 'none'
                ? 0
                : $counter($comparisonRange['from'], $comparisonRange['to']);
            $sparkline = $this->sparklineForBotPageMetric($key, $currentRange, $filters);

            $summary[$key] = array_merge(
                $this->computeMetricSummary($current, $compare, $sparkline, $compareMode),
                ['comparison_label' => $comparisonLabel],
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countBotSessions(Carbon $from, Carbon $to, array $filters): int
    {
        return $this->botSessionQuery($from, $to, $filters)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countBotCountries(Carbon $from, Carbon $to, array $filters): int
    {
        $countryExpression = "UPPER(COALESCE(NULLIF(bc.ip_country, ''), NULLIF(s.country, '')))";

        return (int) DB::table('activity_ecom_user as s')
            ->join('activity_ecom_user_bot_context as bc', 's.session_id', '=', 'bc.session_id')
            ->whereIn('s.session_id', $this->botSessionQuery($from, $to, $filters)->select('activity_ecom_user.session_id'))
            ->where('bc.is_bot', true)
            ->whereRaw("{$countryExpression} IS NOT NULL")
            ->whereRaw("{$countryExpression} != ''")
            ->distinct()
            ->count(DB::raw($countryExpression));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{real_shoppers: int, automated_traffic: int, not_classified: int}
     */
    private function countVisitorQualityMetrics(Carbon $from, Carbon $to, array $filters): array
    {
        $row = $this->allSessionQuery($from, $to, $filters)
            ->leftJoin('activity_ecom_user_bot_context as bc', 'activity_ecom_user.session_id', '=', 'bc.session_id')
            ->toBase()
            ->select([
                DB::raw('COUNT(CASE WHEN bc.session_id IS NOT NULL AND bc.is_bot = 0 THEN 1 END) as real_shoppers'),
                DB::raw('COUNT(CASE WHEN bc.is_bot = 1 THEN 1 END) as automated_traffic'),
                DB::raw('COUNT(CASE WHEN bc.session_id IS NULL THEN 1 END) as not_classified'),
            ])
            ->first();

        return [
            'real_shoppers' => (int) ($row->real_shoppers ?? 0),
            'automated_traffic' => (int) ($row->automated_traffic ?? 0),
            'not_classified' => (int) ($row->not_classified ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countClassification(Carbon $from, Carbon $to, string $type, array $filters): int
    {
        $query = $this->allSessionQuery($from, $to, $filters);

        return match ($type) {
            'human' => (clone $query)->whereHas('botContext', fn ($b) => $b->where('is_bot', false))->count(),
            'bot' => (clone $query)->whereHas('botContext', fn ($b) => $b->where('is_bot', true))->count(),
            default => (clone $query)->whereDoesntHave('botContext')->count(),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countUkShoppers(Carbon $from, Carbon $to, array $filters): int
    {
        return $this->allSessionQuery($from, $to, $filters)
            ->whereHas('botContext', fn ($b) => $b->where('is_bot', false)->where('ip_country', 'GB'))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function sparklineForVisitorQualityMetric(string $metricKey, array $currentRange, array $filters): array
    {
        $from = $currentRange['from'];
        $to = $currentRange['to'];
        $days = max(1, min(14, (int) $from->diffInDays($to) + 1));
        $buckets = [];

        for ($i = 0; $i < $days; $i++) {
            $dayStart = $from->copy()->addDays($i)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $type = match ($metricKey) {
                'automated_traffic' => 'bot',
                'not_classified' => 'unclassified',
                'uk_shoppers' => 'uk',
                default => 'human',
            };

            $buckets[] = $type === 'uk'
                ? $this->countUkShoppers($dayStart, $dayEnd, $filters)
                : $this->countClassification($dayStart, $dayEnd, $type, $filters);
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function sparklineForBotPageMetric(string $metricKey, array $currentRange, array $filters): array
    {
        $from = $currentRange['from'];
        $to = $currentRange['to'];
        $days = max(1, min(14, (int) $from->diffInDays($to) + 1));
        $buckets = [];

        for ($i = 0; $i < $days; $i++) {
            $dayStart = $from->copy()->addDays($i)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $buckets[] = $metricKey === 'bot_countries'
                ? $this->countBotCountries($dayStart, $dayEnd, $filters)
                : $this->countBotSessions($dayStart, $dayEnd, $filters);
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function sparklineForMetric(string $metricKey, array $currentRange, array $filters): array
    {
        return $this->sparklineForVisitorQualityMetric($metricKey, $currentRange, $filters);
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array{labels: array<int, string>, bot: array<int, int>}
     */
    private function buildTrend(array $currentRange, array $filters): array
    {
        $from = $currentRange['from'];
        $to = $currentRange['to'];
        $days = max(1, min(30, (int) $from->diffInDays($to) + 1));
        $labels = [];
        $bot = [];

        for ($i = 0; $i < $days; $i++) {
            $dayStart = $from->copy()->addDays($i)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $labels[] = TrackerTime::toLocal($dayStart)?->format('d M') ?? $dayStart->format('d M');
            $bot[] = $this->countBotSessions($dayStart, $dayEnd, $filters);
        }

        return compact('labels', 'bot');
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, count: int, pct: float}>
     */
    private function buildReasonBreakdown(array $currentRange, array $filters): array
    {
        $rows = ActivityEcomUserBotContext::query()
            ->where('is_bot', true)
            ->whereHas('session', function ($q) use ($currentRange, $filters) {
                $this->applySessionWindow($q, $currentRange['from'], $currentRange['to']);
                $this->applySessionFilters($q, $filters);
            })
            ->select('bot_reason', DB::raw('COUNT(*) as total'))
            ->groupBy('bot_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $total = (int) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'label' => VisitorClassificationLabels::breakdownLabel($row->bot_reason, true),
            'count' => (int) $row->total,
            'pct' => $total > 0 ? round(((int) $row->total / $total) * 100, 1) : 0.0,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $currentRange
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, count: int, pct: float}>
     */
    private function buildCountryBreakdown(array $currentRange, array $filters): array
    {
        $countryExpression = "UPPER(COALESCE(NULLIF(bc.ip_country, ''), NULLIF(s.country, '')))";

        $filteredSessions = $this->botSessionQuery($currentRange['from'], $currentRange['to'], $filters)
            ->select('activity_ecom_user.session_id');

        $visitorCountries = DB::table('activity_ecom_user as s')
            ->join('activity_ecom_user_bot_context as bc', 's.session_id', '=', 'bc.session_id')
            ->whereIn('s.session_id', $filteredSessions)
            ->where('bc.is_bot', true)
            ->whereRaw("{$countryExpression} IS NOT NULL")
            ->whereRaw("{$countryExpression} != ''")
            ->selectRaw("{$countryExpression} as country_code");

        $rows = DB::query()
            ->fromSub($visitorCountries, 'visitor_countries')
            ->select('country_code', DB::raw('COUNT(*) as total'))
            ->groupBy('country_code')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');

        if ($total === 0) {
            return [];
        }

        return $rows->map(fn ($row) => [
            'label' => VisitorClassificationLabels::countryBreakdownLabel((string) $row->country_code),
            'count' => (int) $row->total,
            'pct' => round(((int) $row->total / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateSessions(array $currentRange, array $filters): LengthAwarePaginator
    {
        return $this->botSessionQuery($currentRange['from'], $currentRange['to'], $filters)
            ->with('botContext')
            ->orderByDesc('last_active_at')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function botSessionQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        $query = ActivityEcomUser::query()
            ->whereHas('botContext', fn ($b) => $b->where('is_bot', true));
        $this->applySessionWindow($query, $from, $to);
        $this->applySessionFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function allSessionQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        $query = ActivityEcomUser::query();
        $this->applySessionWindow($query, $from, $to);
        $this->applySessionFilters($query, $filters);

        return $query;
    }

    private function applySessionWindow(Builder $query, Carbon $from, Carbon $to): void
    {
        $table = $query->getModel()->getTable();
        TrackerTime::applySessionActivityWindow($query, $from, $to, $table);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySessionFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
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

        if (! empty($filters['country'])) {
            $country = $filters['country'];
            $query->where(function ($q) use ($country) {
                $q->where('country', $country)
                    ->orWhereHas('botContext', fn ($b) => $b->where('ip_country', $country));
            });
        }

        if (! empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (array_key_exists('logged_in', $filters) && $filters['logged_in'] !== '' && $filters['logged_in'] !== null) {
            $query->where('is_logged_in', $filters['logged_in'] === '1');
        }

        if (array_key_exists('has_order', $filters) && $filters['has_order'] !== '' && $filters['has_order'] !== null) {
            if ($filters['has_order'] === '1') {
                $query->whereHas('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            } elseif ($filters['has_order'] === '0') {
                $query->whereDoesntHave('actions', fn ($q) => $q->where('action_type', 'payment_success'));
            }
        }

        TrackerUtmFilter::applySourceFilter($query, $filters['utm_source'] ?? null);
        TrackerUtmFilter::applyMediumFilter($query, $filters['utm_medium'] ?? null);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        if (! config('tracker.analytics_cache_enabled', true)) {
            EcomTrackerLogger::backend()->info('redis.cache.bypass', 'Analytics cache OFF — loading from database', [
                'cache_key' => $key,
                'reason' => 'cache_disabled',
            ]);

            return $callback();
        }

        if (TrackerRedisSupport::usesMemoryBypass()) {
            EcomTrackerLogger::backend()->warning('redis.cache.bypass', 'Analytics using Laravel cache (Redis bypass ON)', [
                'cache_key' => $key,
            ]);
        } else {
            TrackerRedisSupport::logBackendHealth('bot_traffic_report');
        }

        $wasCached = Cache::has($key);
        $result = Cache::remember($key, $ttlSeconds, $callback);

        EcomTrackerLogger::backend()->debug('redis.cache.read', $wasCached
            ? 'Analytics data loaded from cache OK'
            : 'Analytics data loaded from database OK', [
            'cache_key' => $key,
            'storage' => TrackerRedisSupport::usesMemoryBypass() ? 'memory' : 'laravel_cache',
            'hit' => $wasCached,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return $result;
    }
}
