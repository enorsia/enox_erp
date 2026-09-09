<?php

namespace App\Services;

use App\Models\TrackerUtmFilter;
use App\Services\EcomTrackerDashboardService;
use App\Support\CommerceHasOrderFilter;
use App\Support\EcomActivityFocus;
use App\Support\TrackerTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EcomActivityFilterCounts
{
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
        $counts = [];

        foreach (self::DIMENSIONS as $dimension) {
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
            'utm_source' => TrackerUtmFilter::sourceCountsFrom($query),
            'utm_medium' => TrackerUtmFilter::mediumCountsFrom($query),
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

        $withOrder = clone $query;
        CommerceHasOrderFilter::apply($withOrder, true, $range['from'], $range['to']);

        $withoutOrder = clone $query;
        CommerceHasOrderFilter::apply($withoutOrder, false, $range['from'], $range['to']);

        return [
            '1' => $withOrder->count(),
            '0' => $withoutOrder->count(),
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
}
