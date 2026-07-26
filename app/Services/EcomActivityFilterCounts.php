<?php

namespace App\Services;

use App\Models\TrackerUtmFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EcomActivityFilterCounts
{
    /** @var list<string> */
    private const DIMENSIONS = [
        'device_type',
        'logged_in',
        'has_order',
        'visitor_type',
        'utm_source',
        'utm_medium',
    ];

    /**
     * @param  callable(Request, array<int, string>): Builder  $queryBuilder
     * @return array<string, array<string, int>>
     */
    public function counts(Request $request, callable $queryBuilder): array
    {
        $counts = [];

        foreach (self::DIMENSIONS as $dimension) {
            $counts[$dimension] = $this->countDimension($request, $queryBuilder, $dimension);
        }

        return $counts;
    }

    /**
     * @param  callable(Request, array<int, string>): Builder  $queryBuilder
     * @return array<string, int>
     */
    private function countDimension(Request $request, callable $queryBuilder, string $dimension): array
    {
        $query = $queryBuilder($request, [$dimension]);

        return match ($dimension) {
            'device_type' => $this->groupCount($query, 'device_type'),
            'logged_in' => [
                '1' => (clone $query)->where('is_logged_in', true)->count(),
                '0' => (clone $query)->where('is_logged_in', false)->count(),
            ],
            'has_order' => [
                '1' => (clone $query)->whereHas('actions', fn (Builder $actions) => $actions->where('action_type', 'payment_success'))->count(),
                '0' => (clone $query)->whereDoesntHave('actions', fn (Builder $actions) => $actions->where('action_type', 'payment_success'))->count(),
            ],
            'visitor_type' => [
                'human' => (clone $query)->whereHas('botContext', fn (Builder $bot) => $bot->where('is_bot', false))->count(),
                'bot' => (clone $query)->whereHas('botContext', fn (Builder $bot) => $bot->where('is_bot', true))->count(),
                'unclassified' => (clone $query)->whereDoesntHave('botContext')->count(),
            ],
            'utm_source' => TrackerUtmFilter::sourceCountsFrom($query),
            'utm_medium' => TrackerUtmFilter::mediumCountsFrom($query),
            default => [],
        };
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
