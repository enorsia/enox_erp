<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Services\EcomActivityListService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductPerformanceSectionService
{
    public const PRODUCT_LIMIT = 10;

    use \App\Services\EcomTrackerDashboard\Concerns\InteractsWithSessionFilterRatios;

    public function products(Carbon $from, Carbon $to, string $period, array $sessionFilters = [], ?int $limit = self::PRODUCT_LIMIT): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return $this->productsWithSessionFilters($from, $to, $period, $sessionFilters, $limit);
        }

        $aggregated = TrackerDatabase::table('tracking_daily_products')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->groupBy('product_code')
            ->selectRaw('product_code as code')
            ->selectRaw('SUM(product_views) as views, SUM(add_to_carts) as adds, SUM(begin_checkouts) as begin_checkouts')
            ->selectRaw('SUM(proceed_checkouts) as proceed_checkouts')
            ->selectRaw('SUM(sold_qty) as qty, SUM(sale_amount) as revenue')
            ->get()
            ->keyBy('code');

        $all = $aggregated->map(function (object $row) {
            $code = (string) $row->code;

            return [
                'code' => $code,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'begin_checkouts' => (int) ($row->begin_checkouts ?? 0),
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'qty' => (int) $row->qty,
                'revenue' => round((float) $row->revenue, 2),
            ];
        });

        return $this->packageProductRows($all, $limit, $from, $to, $period, []);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return array{rows: list<array<string, mixed>>, totals: array{views: int, product_count: int}}
     */
    private function packageProductRows(
        Collection $all,
        ?int $limit,
        Carbon $from,
        Carbon $to,
        string $period,
        array $sessionFilters,
    ): array {
        $range = ['from' => $from, 'to' => $to, 'period' => $period];
        $sorted = $this->sortProductRows($all);
        $visible = $limit === null ? $sorted : $sorted->take($limit);

        $titles = TrackerDatabase::table('tracking_product')
            ->whereIn('code', $visible->pluck('code')->all())
            ->pluck('title', 'code');

        $maxRevenue = max(1.0, (float) $visible->max('revenue'));

        return [
            'rows' => $visible->map(function (array $row) use ($titles, $maxRevenue) {
                $code = $row['code'];

                return [
                    'code' => $code,
                    'name' => (string) ($titles[$code] ?? $code),
                    'views' => (int) ($row['views'] ?? 0),
                    'add_to_cart' => (int) ($row['adds'] ?? 0),
                    'begin_checkout' => (int) ($row['begin_checkouts'] ?? 0),
                    'proceed_checkout' => (int) ($row['proceed_checkouts'] ?? 0),
                    'paid' => (int) ($row['qty'] ?? 0),
                    'revenue' => $row['revenue'],
                    'revenue_bar_percent' => (int) round($row['revenue'] / $maxRevenue * 100),
                ];
            })->values()->all(),
            'totals' => [
                'views' => (int) $all->sum('views'),
                'product_count' => $all->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductRows(Collection $all): Collection
    {
        return $all
            ->sortBy([
                ['qty', 'desc'],
                ['proceed_checkouts', 'desc'],
                ['begin_checkouts', 'desc'],
                ['adds', 'desc'],
                ['views', 'desc'],
                ['revenue', 'desc'],
            ])
            ->values();
    }

    /**
     * Scale tracking_daily_products by filtered-session ratio per day (same pattern as categories).
     *
     * @param  array<string, mixed>  $sessionFilters
     */
    private function productsWithSessionFilters(Carbon $from, Carbon $to, string $period, array $sessionFilters, ?int $limit = self::PRODUCT_LIMIT): array
    {
        $withOrderOnly = ($sessionFilters['has_order'] ?? null) === true;
        $soldByCode = $this->filteredProductSoldTotalsByCode($from, $to, $period, $sessionFilters);

        if ($withOrderOnly && $soldByCode === []) {
            return $this->packageProductRows(collect(), $limit, $from, $to, $period, $sessionFilters);
        }

        $ratios = $this->sessionFilterRatiosByDate($from, $to, $period, $sessionFilters);
        $dailyRows = TrackerDatabase::table('tracking_daily_products')
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->when($withOrderOnly, fn ($query) => $query->whereIn('product_code', array_keys($soldByCode)))
            ->get([
                'metric_date',
                'product_code',
                'product_views',
                'add_to_carts',
                'begin_checkouts',
                'proceed_checkouts',
            ]);

        $byCode = [];
        foreach ($dailyRows as $row) {
            $ratio = $ratios[(string) $row->metric_date] ?? 0.0;
            if ($ratio <= 0) {
                continue;
            }

            $code = trim((string) $row->product_code);
            if ($code === '') {
                continue;
            }

            if ($withOrderOnly && ! isset($soldByCode[$code])) {
                continue;
            }

            $byCode[$code] ??= [
                'code' => $code,
                'views' => 0,
                'adds' => 0,
                'begin_checkouts' => 0,
                'proceed_checkouts' => 0,
                'qty' => 0,
                'revenue' => 0.0,
            ];
            $byCode[$code]['views'] += (int) round((int) $row->product_views * $ratio);
            $byCode[$code]['adds'] += (int) round((int) $row->add_to_carts * $ratio);
            $byCode[$code]['begin_checkouts'] += (int) round((int) $row->begin_checkouts * $ratio);
            $byCode[$code]['proceed_checkouts'] += (int) round((int) $row->proceed_checkouts * $ratio);
        }

        foreach ($soldByCode as $code => $sold) {
            $byCode[$code] ??= [
                'code' => $code,
                'views' => 0,
                'adds' => 0,
                'begin_checkouts' => 0,
                'proceed_checkouts' => 0,
                'qty' => 0,
                'revenue' => 0.0,
            ];
            $byCode[$code]['qty'] = (int) $sold['qty'];
            $byCode[$code]['revenue'] = (float) $sold['revenue'];
        }

        if ($withOrderOnly) {
            $byCode = array_intersect_key($byCode, $soldByCode);
        }

        $productCode = trim((string) ($sessionFilters['product_code'] ?? ''));
        if ($productCode !== '') {
            $byCode = isset($byCode[$productCode]) ? [$productCode => $byCode[$productCode]] : [];
        }

        return $this->packageProductRows(collect(array_values($byCode)), $limit, $from, $to, $period, $sessionFilters);
    }

    /**
     * Sold qty / sale amount for filtered sessions (same order lines as sync writes to tracking_daily_products).
     *
     * @param  array<string, mixed>  $sessionFilters
     * @return array<string, array{qty: int, revenue: float}>
     */
    private function filteredProductSoldTotalsByCode(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        [$fromBound, $toBound] = TrackerTime::storageRange($from, $to);
        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $rows = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->join('tracking_product as p', 'p.id', '=', 'od.tracking_product_id')
            ->whereBetween('o.ordered_at', [$fromBound, $toBound])
            ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 'ts'))
            ->tap(fn ($query) => $this->activityList->constrainDashboardSessionsInRange($query, $range, $sessionFilters))
            ->groupBy('p.code')
            ->selectRaw('p.code as product_code, SUM(od.qty) as qty, SUM(od.line_total) as revenue')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->product_code] = [
                'qty' => (int) $row->qty,
                'revenue' => round((float) $row->revenue, 2),
            ];
        }

        return $map;
    }

}
