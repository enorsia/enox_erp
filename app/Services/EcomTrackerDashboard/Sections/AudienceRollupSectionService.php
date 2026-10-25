<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Models\TrackingDailyAudience;
use App\Support\TrackerDatabase;
use Illuminate\Support\Collection;

class AudienceRollupSectionService
{
    public function rollup(string $from, string $to): array
    {
        $rows = TrackerDatabase::table('tracking_daily_audiences as a')
            ->leftJoin('tracking_device as dv', fn ($join) => $join->on('dv.id', '=', 'a.dimension_id')->where('a.type', TrackingDailyAudience::TYPE_DEVICE))
            ->leftJoin('tracking_browser as br', fn ($join) => $join->on('br.id', '=', 'a.dimension_id')->where('a.type', TrackingDailyAudience::TYPE_BROWSER))
            ->whereBetween('a.metric_date', [$from, $to])
            ->groupBy('a.type', 'a.dimension_id', 'a.source', 'a.medium', 'dv.name', 'br.name')
            ->selectRaw('a.type, a.dimension_id, a.source, a.medium, COALESCE(dv.name, br.name) as name')
            ->selectRaw('SUM(a.sessions) as sessions, SUM(a.views) as views, SUM(a.add_to_carts) as add_to_cart')
            ->selectRaw('SUM(a.begin_checkouts) as begin_checkout, SUM(a.proceed_checkouts) as proceed_checkout')
            ->selectRaw('SUM(a.payments) as payment_success, SUM(a.sold_qty) as sold_qty, SUM(a.sale_amount) as revenue')
            ->get();

        $performanceKeys = [
            'sold' => 'sold_qty',
            'proceed' => 'proceed_checkout',
            'adds' => 'add_to_cart',
            'views' => 'views',
            'revenue' => 'revenue',
        ];

        return $rows
            ->groupBy('type')
            ->map(fn (Collection $typeRows) => $this->sortByFunnelPerformance(
                $typeRows->map(fn (object $row) => [
                    'id' => (int) $row->dimension_id ?: null,
                    'label' => ucfirst((string) ($row->name ?? 'Unknown')),
                    'source' => (string) $row->source,
                    'medium' => (string) $row->medium,
                    'sessions' => (int) $row->sessions,
                    'views' => (int) $row->views,
                    'add_to_cart' => (int) $row->add_to_cart,
                    'begin_checkout' => (int) $row->begin_checkout,
                    'proceed_checkout' => (int) $row->proceed_checkout,
                    'payment_success' => (int) $row->payment_success,
                    'sold_qty' => (int) $row->sold_qty,
                    'revenue' => (float) $row->revenue,
                    'conversion_rate' => (int) $row->sessions > 0 ? round((int) $row->payment_success / (int) $row->sessions * 100, 1) : 0,
                ])->all(),
                $performanceKeys,
            ))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{sold: string, proceed: string, adds: string, views: string, revenue: string}  $fields
     * @return list<array<string, mixed>>
     */
    private function sortByFunnelPerformance(array $rows, array $fields): array
    {
        usort($rows, function (array $a, array $b) use ($fields): int {
            foreach (['sold', 'proceed', 'adds', 'views', 'revenue'] as $metric) {
                $key = $fields[$metric];
                $cmp = ((float) ($b[$key] ?? 0)) <=> ((float) ($a[$key] ?? 0));

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        return array_values($rows);
    }

}
