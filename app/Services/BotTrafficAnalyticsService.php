<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use App\Support\CommerceHasOrderFilter;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerMultiSelectFilter;
use App\Support\TrackerRedisSupport;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Visitor quality metrics for dashboard and visitor analytics strips.
 */
class BotTrafficAnalyticsService
{
    private const CACHE_TTL_SUMMARY_SECONDS = 300;

    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
    ) {}

    /**
     * Compact summary for dashboard / visitor analytics strips.
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

        $cacheKey = 'visitor_quality_summary:v1:' . md5(json_encode([
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
     * @param  array<string, mixed>  $filters
     * @return array{real_shoppers: int, automated_traffic: int, not_classified: int}
     */
    private function countVisitorQualityMetrics(Carbon $from, Carbon $to, array $filters): array
    {
        return [
            'real_shoppers' => $this->countClassification($from, $to, 'human', $filters),
            'automated_traffic' => $this->countClassification($from, $to, 'bot', $filters),
            'not_classified' => $this->countClassification($from, $to, 'unclassified', $filters),
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
    private function allSessionQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        $query = ActivityEcomUser::query();
        $this->applySessionWindow($query, $from, $to, $filters['period'] ?? null);
        $this->applySessionFilters($query, $filters, $from, $to);

        return $query;
    }

    private function applySessionWindow(Builder $query, Carbon $from, Carbon $to, ?string $period = null): void
    {
        $table = $query->getModel()->getTable();
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, $table);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySessionFilters(Builder $query, array $filters, ?Carbon $from = null, ?Carbon $to = null): void
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
            $devices = TrackerMultiSelectFilter::allowedValues(
                $filters['device_type'],
                ['desktop', 'mobile', 'tablet'],
            );

            if ($devices !== []) {
                $query->whereIn('device_type', $devices);
            }
        }

        if (array_key_exists('logged_in', $filters) && $filters['logged_in'] !== '' && $filters['logged_in'] !== null) {
            $query->where('is_logged_in', $filters['logged_in'] === '1');
        }

        if (array_key_exists('has_order', $filters) && $filters['has_order'] !== '' && $filters['has_order'] !== null) {
            $hasOrder = $filters['has_order'] === '1';

            if ($from instanceof Carbon && $to instanceof Carbon) {
                CommerceHasOrderFilter::apply($query, $hasOrder, $from, $to);
            } else {
                CommerceHasOrderFilter::apply($query, $hasOrder);
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
            TrackerRedisSupport::logBackendHealth('visitor_quality_summary');
        }

        $wasCached = true;
        $result = Cache::remember($key, $ttlSeconds, function () use ($callback, &$wasCached) {
            $wasCached = false;

            return $callback();
        });

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
