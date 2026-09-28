<?php

namespace App\Services;

use App\Models\TrackerUtmFilter;
use App\Services\EcomDailyMetricsQuery;
use App\Services\EcomTrackerDashboardService;
use App\Support\EcomActivityFocus;
use App\Support\TrackerTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcomActivityFilterCounts
{
    /** @var array<string, array{utm_source: array<string, int>, utm_medium: array<string, int>}> */
    private array $listTrafficFacetCache = [];

    /** @var list<string> */
    private const DIMENSIONS = [
        'device_type',
        'logged_in',
        'has_order',
        'utm_source',
        'utm_medium',
    ];

    /**
     * @param  callable(Request, array<int, string>): Builder  $queryBuilder
     * @param  null|callable(Request, array<int, string>): array<string, int>  $deferredHasOrderCounter
     * @return array<string, array<string, int>>
     */
    public function counts(Request $request, callable $queryBuilder, ?callable $deferredHasOrderCounter = null): array
    {
        if ($this->shouldUseRollupFacets($request)) {
            $range = app(EcomTrackerDashboardService::class)->resolveDateRange(
                $request->only(['period', 'date_from', 'date_to']),
            );
            $rollupCounts = app(EcomDailyMetricsQuery::class)->hybridActivityFacetCounts(
                $range['from'],
                $range['to'],
                $range['period'] ?? null,
            );

            if ($rollupCounts !== null) {
                return $rollupCounts;
            }
        }

        $counts = [];
        $deviceQuery = $queryBuilder($request, ['device_type']);
        $loggedInQuery = $queryBuilder($request, ['logged_in']);

        if ($deviceQuery->toRawSql() === $loggedInQuery->toRawSql()) {
            $combined = $this->groupDeviceAndLoggedInCounts($deviceQuery);
            $counts['device_type'] = $combined['device_type'];
            $counts['logged_in'] = $combined['logged_in'];
        } else {
            $counts['device_type'] = $this->groupCount($deviceQuery, 'device_type');
            $counts['logged_in'] = $this->groupLoggedInCount($loggedInQuery);
        }

        foreach (self::DIMENSIONS as $dimension) {
            if (in_array($dimension, ['device_type', 'logged_in'], true)) {
                continue;
            }

            $counts[$dimension] = $this->countDimension(
                $request,
                $queryBuilder,
                $dimension,
                $deferredHasOrderCounter,
            );
        }

        return $counts;
    }

    /**
     * @param  callable(Request, array<int, string>): Builder  $queryBuilder
     * @param  null|callable(Request, array<int, string>): array<string, int>  $deferredHasOrderCounter
     * @return array<string, int>
     */
    private function countDimension(
        Request $request,
        callable $queryBuilder,
        string $dimension,
        ?callable $deferredHasOrderCounter = null,
    ): array {
        $query = $queryBuilder($request, [$dimension]);

        return match ($dimension) {
            'device_type' => $this->groupCount($query, 'device_type'),
            'logged_in' => $this->groupLoggedInCount($query),
            'has_order' => $this->countHasOrder($request, $query, $deferredHasOrderCounter),
            'utm_source' => EcomActivityFocus::usesConversionSourceFilter($request)
                ? TrackerUtmFilter::conversionSourceCountsFrom($query)
                : $this->listTrafficFacetsForQuery($query)['utm_source'],
            'utm_medium' => EcomActivityFocus::usesConversionSourceFilter($request)
                ? TrackerUtmFilter::mediumCountsFrom($query)
                : $this->listTrafficFacetsForQuery($query)['utm_medium'],
            default => [],
        };
    }

    /**
     * @param  null|callable(Request, array<int, string>): array<string, int>  $deferredHasOrderCounter
     * @return array<string, int>
     */
    private function countHasOrder(Request $request, Builder $query, ?callable $deferredHasOrderCounter = null): array
    {
        if ($deferredHasOrderCounter !== null && EcomActivityFocus::shouldDeferHasOrderFilter($request)) {
            return $deferredHasOrderCounter($request, ['has_order']);
        }

        $range = app(EcomTrackerDashboardService::class)->resolveDateRange(
            $request->only(['period', 'date_from', 'date_to']),
        );

        $table = $query->getModel()->getTable();
        [$start, $end] = TrackerTime::storageRange($range['from'], $range['to']);

        $row = self::aggregateQuery($query)
            ->leftJoinSub(
                DB::table('activity_ecom_orders')
                    ->select('session_id')
                    ->whereBetween('ordered_at', [$start, $end])
                    ->groupBy('session_id'),
                'period_orders',
                'period_orders.session_id',
                '=',
                "{$table}.session_id",
            )
            ->selectRaw('SUM(CASE WHEN period_orders.session_id IS NOT NULL THEN 1 ELSE 0 END) as with_order')
            ->selectRaw('SUM(CASE WHEN period_orders.session_id IS NULL THEN 1 ELSE 0 END) as without_order')
            ->first();

        return [
            '1' => (int) ($row->with_order ?? 0),
            '0' => (int) ($row->without_order ?? 0),
        ];
    }

    /**
     * @return array{device_type: array<string, int>, logged_in: array<string, int>}
     */
    private function groupDeviceAndLoggedInCounts(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        $rows = self::aggregateQuery($query)
            ->selectRaw("{$table}.device_type as device_bucket, {$table}.is_logged_in as logged_in_flag, COUNT(*) as total")
            ->groupBy("{$table}.device_type", "{$table}.is_logged_in")
            ->get();

        $deviceCounts = [];
        $loggedInCounts = ['1' => 0, '0' => 0];

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $device = $row->device_bucket;

            if (filled($device) && (string) $device !== '') {
                $deviceKey = (string) $device;
                $deviceCounts[$deviceKey] = ($deviceCounts[$deviceKey] ?? 0) + $total;
            }

            $loggedKey = (int) ($row->logged_in_flag ?? 0) === 1 ? '1' : '0';
            $loggedInCounts[$loggedKey] += $total;
        }

        return [
            'device_type' => collect($deviceCounts)->sortDesc()->map(fn ($count) => (int) $count)->all(),
            'logged_in' => $loggedInCounts,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function groupLoggedInCount(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        $rows = self::aggregateQuery($query)
            ->selectRaw("CASE WHEN {$table}.is_logged_in = 1 THEN '1' ELSE '0' END as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            '1' => (int) ($rows['1'] ?? 0),
            '0' => (int) ($rows['0'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(Builder $query, string $column): array
    {
        $table = $query->getModel()->getTable();

        return self::aggregateQuery($query)
            ->selectRaw("{$table}.{$column} as bucket, COUNT(*) as total")
            ->whereNotNull("{$table}.{$column}")
            ->where("{$table}.{$column}", '!=', '')
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function aggregateQuery(Builder $query): Builder
    {
        $aggregate = clone $query;
        $aggregate->setEagerLoads([]);
        $aggregate->getQuery()->columns = [];
        $aggregate->getQuery()->orders = null;
        $aggregate->getQuery()->groups = null;
        $aggregate->getQuery()->havings = null;

        return $aggregate;
    }

    /**
     * @return array{utm_source: array<string, int>, utm_medium: array<string, int>}
     */
    private function listTrafficFacetsForQuery(Builder $query): array
    {
        $cacheKey = md5($query->toRawSql());

        if (! isset($this->listTrafficFacetCache[$cacheKey])) {
            $this->listTrafficFacetCache[$cacheKey] = TrackerUtmFilter::listTrafficFacetCountsFrom($query);
        }

        return $this->listTrafficFacetCache[$cacheKey];
    }

    private function shouldUseRollupFacets(Request $request): bool
    {
        if (! config('tracker.use_daily_rollups', true)) {
            return false;
        }

        if (EcomActivityFocus::usesConversionSourceFilter($request)) {
            return false;
        }

        $sessionFilters = EcomActivityFocus::sessionFiltersFromRequest($request);

        return $sessionFilters === [];
    }
}
