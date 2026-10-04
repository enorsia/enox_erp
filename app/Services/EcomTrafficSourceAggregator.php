<?php

namespace App\Services;

use App\Models\TrackerUtmFilter;
use App\Support\CommerceHumanSessionFilter;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SQL aggregation for dashboard traffic-source buckets (one or more calendar days).
 */
class EcomTrafficSourceAggregator
{
    /**
     * @return array<string, array<string, int|float|string>>
     */
    public function bucketsForRange(
        Carbon $from,
        Carbon $to,
        ?Collection $sessionIds = null,
        ?string $period = null,
    ): array {
        if ($sessionIds !== null && $sessionIds->isEmpty()) {
            return [];
        }

        $sourceSql = TrackerUtmFilter::dashboardTrafficSourceBucketSql('s');
        $mediumSql = TrackerUtmFilter::dashboardTrafficMediumBucketSql('s');
        $buckets = [];

        $sessionQuery = DB::table('activity_ecom_user as s');
        TrackerTime::applyEcomActivitySessionScope($sessionQuery, $from, $to, $period, 's');
        CommerceHumanSessionFilter::apply($sessionQuery, 's');
        if ($sessionIds !== null) {
            $this->constrainSessionIds($sessionQuery, $sessionIds, 's.session_id');
        }

        foreach ($sessionQuery
            ->selectRaw("{$sourceSql} as traffic_source")
            ->selectRaw("{$mediumSql} as traffic_medium")
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('SUM(CASE WHEN s.has_add_to_cart = 1 THEN 1 ELSE 0 END) as add_to_cart')
            ->selectRaw('SUM(CASE WHEN s.has_begin_checkout = 1 THEN 1 ELSE 0 END) as begin_checkout')
            ->selectRaw('SUM(CASE WHEN s.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_checkout')
            ->groupByRaw('traffic_source, traffic_medium')
            ->get() as $row) {
            $source = (string) $row->traffic_source;
            $medium = (string) ($row->traffic_medium ?? 'none');
            $key = $source."\0".$medium;

            $this->incrementBucket($buckets, $key, $source, $medium, 'sessions', (int) $row->sessions);
            $this->incrementBucket($buckets, $key, field: 'add_to_cart', amount: (int) $row->add_to_cart);
            $this->incrementBucket($buckets, $key, field: 'begin_checkout', amount: (int) $row->begin_checkout);
            $this->incrementBucket($buckets, $key, field: 'proceed_checkout', amount: (int) $row->proceed_checkout);
        }

        if ($buckets === []) {
            return [];
        }

        [$rangeStart, $rangeEnd] = TrackerTime::storageRange($from, $to);

        $viewQuery = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereIn('li.funnel_stage', ['product_view', 'product_view_popup'])
            ->whereBetween('li.staged_at', [$rangeStart, $rangeEnd]);

        if ($sessionIds !== null) {
            $this->constrainSessionIds($viewQuery, $sessionIds, 'li.session_id');
        } else {
            TrackerTime::applyEcomActivitySessionScope($viewQuery, $from, $to, $period, 's');
        }
        CommerceHumanSessionFilter::apply($viewQuery, 's');

        foreach ($viewQuery
            ->selectRaw("{$sourceSql} as traffic_source")
            ->selectRaw("{$mediumSql} as traffic_medium")
            ->selectRaw('COUNT(*) as views')
            ->groupByRaw('traffic_source, traffic_medium')
            ->get() as $row) {
            $key = (string) $row->traffic_source."\0".(string) ($row->traffic_medium ?? 'none');
            $this->incrementBucket($buckets, $key, field: 'views', amount: (int) $row->views);
        }

        $orderQuery = DB::table('activity_ecom_orders as o')
            ->whereBetween('o.ordered_at', [$rangeStart, $rangeEnd]);

        $this->applyOptionalSessionScope($orderQuery, $sessionIds, $from, $to, $period, 'o.session_id');

        foreach ($orderQuery
            ->selectRaw("CASE
                WHEN o.conversion_utm_source IS NOT NULL AND o.conversion_utm_source != ''
                THEN o.conversion_utm_source
                ELSE '(direct)'
            END as traffic_source")
            ->selectRaw("CASE
                WHEN o.conversion_utm_medium IS NOT NULL AND TRIM(o.conversion_utm_medium) != ''
                THEN TRIM(o.conversion_utm_medium)
                ELSE 'none'
            END as traffic_medium")
            ->selectRaw('COUNT(DISTINCT o.session_id) as payment_success')
            ->selectRaw('SUM(GREATEST(0, COALESCE(o.item_qty, 0))) as sold_qty')
            ->selectRaw('SUM(COALESCE(o.amount_paid, 0)) as revenue')
            ->groupByRaw('traffic_source, traffic_medium')
            ->get() as $row) {
            $source = (string) $row->traffic_source;
            $medium = (string) $row->traffic_medium;
            $key = $source."\0".$medium;

            if (! isset($buckets[$key])) {
                $this->incrementBucket($buckets, $key, $source, $medium, 'sessions', 0);
            }

            $this->incrementBucket($buckets, $key, field: 'payment_success', amount: (int) $row->payment_success);
            $this->incrementBucket($buckets, $key, field: 'sold_qty', amount: (int) $row->sold_qty);
            $this->incrementBucket($buckets, $key, field: 'revenue', amount: (float) $row->revenue);
        }

        return $buckets;
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $target
     * @param  array<string, array<string, int|float|string>>  $add
     */
    public function mergeBuckets(array &$target, array $add): void
    {
        foreach ($add as $key => $row) {
            if (! isset($target[$key])) {
                $target[$key] = $row;

                continue;
            }

            foreach (['sessions', 'views', 'add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success', 'sold_qty'] as $field) {
                $target[$key][$field] = (int) $target[$key][$field] + (int) ($row[$field] ?? 0);
            }

            $target[$key]['revenue'] = round((float) $target[$key]['revenue'] + (float) ($row['revenue'] ?? 0), 2);
        }
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $buckets
     */
    private function incrementBucket(
        array &$buckets,
        string $key,
        ?string $source = null,
        ?string $medium = null,
        string $field = 'sessions',
        int|float $amount = 1,
    ): void {
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'source' => (string) $source,
                'medium' => (string) $medium,
                'sessions' => 0,
                'views' => 0,
                'add_to_cart' => 0,
                'begin_checkout' => 0,
                'proceed_checkout' => 0,
                'payment_success' => 0,
                'sold_qty' => 0,
                'revenue' => 0.0,
            ];
        }

        if ($field === 'revenue') {
            $buckets[$key][$field] = round((float) $buckets[$key][$field] + (float) $amount, 2);

            return;
        }

        $buckets[$key][$field] = (int) $buckets[$key][$field] + (int) $amount;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  Collection<int, string>  $sessionIds
     */
    private function constrainSessionIds($query, Collection $sessionIds, string $column): void
    {
        $ids = $sessionIds->values()->all();

        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $chunk = 1000;

        if (count($ids) <= $chunk) {
            $query->whereIn($column, $ids);

            return;
        }

        $query->where(function ($outer) use ($ids, $column, $chunk) {
            foreach (array_chunk($ids, $chunk) as $part) {
                $outer->orWhereIn($column, $part);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyOptionalSessionScope(
        $query,
        ?Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?string $period,
        string $column,
    ): void {
        if ($sessionIds !== null) {
            $this->constrainSessionIds($query, $sessionIds, $column);

            return;
        }

        $query->whereIn($column, function ($sub) use ($from, $to, $period) {
            $sub->from('activity_ecom_user')->select('session_id');
            TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
        });
    }
}
