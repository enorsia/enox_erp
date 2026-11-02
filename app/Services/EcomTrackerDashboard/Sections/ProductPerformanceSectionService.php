<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Services\EcomActivityListService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductPerformanceSectionService
{
    public const PRODUCT_LIMIT = 10;

    public const PRODUCT_LIST_DEFAULT_SORT = 'paid';

    /** @var list<string> */
    public const PRODUCT_LIST_SORT_KEYS = [
        'product',
        'views',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'paid',
        'revenue',
    ];

    /** @var list<string> Default tie-break order (desc) after the active sort column. */
    private const PRODUCT_LIST_SORT_SEQUENCE = [
        'paid',
        'proceed_checkout',
        'begin_checkout',
        'add_to_cart',
        'views',
    ];

    use \App\Services\EcomTrackerDashboard\Concerns\InteractsWithSessionFilterRatios;

    public static function normalizeProductListSort(mixed $sort): string
    {
        $sort = is_string($sort) ? $sort : '';

        return in_array($sort, self::PRODUCT_LIST_SORT_KEYS, true)
            ? $sort
            : self::PRODUCT_LIST_DEFAULT_SORT;
    }

    /** @return 'asc'|'desc' */
    public static function normalizeProductListSortDir(mixed $dir): string
    {
        $dir = strtolower(is_string($dir) ? $dir : '');

        return in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
    }

    public function products(Carbon $from, Carbon $to, string $period, array $sessionFilters = [], ?int $limit = self::PRODUCT_LIMIT): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (EcomActivityListService::hasDashboardSessionFilters($sessionFilters)) {
            return $this->productsWithSessionFilters($from, $to, $period, $sessionFilters, $limit);
        }

        $productCode = trim((string) ($sessionFilters['product_code'] ?? ''));
        $dailyProducts = TrackerDatabase::table('tracking_daily_products')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->when($productCode !== '', fn ($query) => $query->where('product_code', $productCode));

        $aggregated = $dailyProducts
            ->groupBy('product_code')
            ->selectRaw('product_code as code')
            ->selectRaw('SUM(product_views) as views, SUM(add_to_carts) as adds, SUM(begin_checkouts) as begin_checkouts')
            ->selectRaw('SUM(proceed_checkouts) as proceed_checkouts')
            ->get()
            ->keyBy('code');

        $soldByCode = $this->orderSoldTotalsByParentCode($from, $to, $period, $sessionFilters);

        $all = $aggregated->map(function (object $row) use ($soldByCode) {
            $code = (string) $row->code;
            $sold = $soldByCode[$code] ?? ['qty' => 0, 'revenue' => 0.0];

            return [
                'code' => $code,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'begin_checkouts' => (int) ($row->begin_checkouts ?? 0),
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'qty' => (int) $sold['qty'],
                'revenue' => round((float) $sold['revenue'], 2),
            ];
        });

        foreach ($soldByCode as $code => $sold) {
            if ($all->has($code)) {
                continue;
            }

            $all->put($code, [
                'code' => $code,
                'views' => 0,
                'adds' => 0,
                'begin_checkouts' => 0,
                'proceed_checkouts' => 0,
                'qty' => (int) $sold['qty'],
                'revenue' => round((float) $sold['revenue'], 2),
            ]);
        }

        $all = $this->applyProductListHasOrderFilter($all, $sessionFilters);
        $all = $this->applyProductListCategoryScope($all, $from, $to, $sessionFilters);

        return $this->packageProductRows($all, $limit, $from, $to, $period, $sessionFilters);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return array{rows: list<array<string, mixed>>, totals: array{
     *     views: int,
     *     add_to_cart: int,
     *     begin_checkout: int,
     *     proceed_checkout: int,
     *     paid: int,
     *     revenue: float,
     *     product_count: int,
     * }}
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
        $sortBy = self::normalizeProductListSort($sessionFilters['sort'] ?? null);
        $sortDir = self::normalizeProductListSortDir($sessionFilters['sort_dir'] ?? null);
        $sorted = $this->sortProductRows($all, $sortBy, $sortDir);
        $visible = $limit === null ? $sorted : $sorted->take($limit);

        $titles = collect();
        $codes = $visible->pluck('code')->filter()->unique()->values()->all();
        foreach (array_chunk($codes, 400) as $codeChunk) {
            $titles = $titles->merge(
                TrackerDatabase::table('tracking_product')
                    ->whereIn('code', $codeChunk)
                    ->pluck('title', 'code'),
            );
        }

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
                'add_to_cart' => (int) $all->sum('adds'),
                'begin_checkout' => (int) $all->sum('begin_checkouts'),
                'proceed_checkout' => (int) $all->sum('proceed_checkouts'),
                'paid' => (int) $all->sum('qty'),
                'revenue' => round((float) $all->sum('revenue'), 2),
                'product_count' => $all->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @param  array<string, mixed>  $sessionFilters
     */
    private function applyProductListHasOrderFilter(Collection $all, array $sessionFilters): Collection
    {
        $hasOrder = $sessionFilters['has_order'] ?? null;
        if ($hasOrder === true) {
            return $all->filter(fn (array $row) => (int) ($row['qty'] ?? 0) > 0)->values();
        }

        if ($hasOrder === false) {
            return $all->filter(fn (array $row) => (int) ($row['qty'] ?? 0) === 0)->values();
        }

        return $all;
    }

    /**
     * Product performance list only: limit rows to styles seen in these categories (metrics unchanged).
     *
     * @param  Collection<int, array<string, mixed>>  $all
     * @param  array<string, mixed>  $sessionFilters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyProductListCategoryScope(
        Collection $all,
        Carbon $from,
        Carbon $to,
        array $sessionFilters,
    ): Collection {
        $categoryIds = array_values(array_filter(
            array_map('intval', (array) ($sessionFilters['product_list_category_ids'] ?? [])),
            fn (int $id) => $id > 0,
        ));
        if ($categoryIds === []) {
            return $all;
        }

        $allowed = $this->productCodesForProductListCategoryFilter($from, $to, $categoryIds);

        return $all->filter(fn (array $row) => isset($allowed[(string) ($row['code'] ?? '')]))->values();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return array<string, true>
     */
    private function productCodesForProductListCategoryFilter(Carbon $from, Carbon $to, array $categoryIds): array
    {
        $categoryIdSet = array_fill_keys($categoryIds, true);
        $allowed = [];

        $day = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($day->lte($end)) {
            foreach ($this->primaryProductCategoryIdsByCodeForVisitDate($day->toDateString()) as $code => $categoryId) {
                if (isset($categoryIdSet[$categoryId])) {
                    $allowed[$code] = true;
                }
            }
            $day->addDay();
        }

        return $allowed;
    }

    /**
     * Same attribution as TrackerDashboardSyncService::productCategoryIdsByCodeForVisitDate (primary p_cat link count).
     *
     * @return array<string, int> product code => tracking_category_id
     */
    private function primaryProductCategoryIdsByCodeForVisitDate(string $visitDate): array
    {
        $rows = TrackerDatabase::table('tracking_session_p_cat as sp')
            ->join('tracking_product as p', 'p.id', '=', 'sp.tracking_product_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'sp.tracking_session_id')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('dv.visit_date', $visitDate)
            ->whereNotNull('sp.tracking_category_id')
            ->groupBy('p.code', 'sp.tracking_category_id')
            ->selectRaw('p.code, sp.tracking_category_id as category_id, COUNT(*) as link_count')
            ->get();

        $best = [];
        foreach ($rows as $row) {
            $code = Str::limit(trim((string) $row->code), 100, '');
            if ($code === '') {
                continue;
            }

            $count = (int) $row->link_count;
            if (! isset($best[$code]) || $count > $best[$code]['count']) {
                $best[$code] = ['category_id' => (int) $row->category_id, 'count' => $count];
            }
        }

        return array_map(fn (array $entry): int => $entry['category_id'], $best);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $all
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductRows(Collection $all, string $sortBy, string $sortDir): Collection
    {
        $sortBy = self::normalizeProductListSort($sortBy);
        $sortDir = self::normalizeProductListSortDir($sortDir);
        $keys = $this->productListSortKeysOrdered($sortBy);

        $titles = in_array('product', $keys, true)
            ? $this->productTitlesByCodes($all->pluck('code')->filter()->unique()->values()->all())
            : null;

        return $all->sort(function (array $a, array $b) use ($keys, $sortDir, $titles): int {
            foreach ($keys as $index => $key) {
                $direction = $index === 0 ? $sortDir : 'desc';
                $cmp = $this->compareProductListSortRows($a, $b, $key, $titles);
                if ($cmp === 0) {
                    continue;
                }

                return $direction === 'desc' ? -$cmp : $cmp;
            }

            return 0;
        })->values();
    }

    /**
     * Clicked column first; remaining funnel metrics keep {@see PRODUCT_LIST_SORT_SEQUENCE} order.
     *
     * @return list<string>
     */
    private function productListSortKeysOrdered(string $primary): array
    {
        $primary = self::normalizeProductListSort($primary);

        if ($primary === 'product') {
            return array_merge(['product'], self::PRODUCT_LIST_SORT_SEQUENCE);
        }

        if ($primary === 'revenue') {
            return array_merge(['revenue'], self::PRODUCT_LIST_SORT_SEQUENCE);
        }

        if (! in_array($primary, self::PRODUCT_LIST_SORT_SEQUENCE, true)) {
            $primary = self::PRODUCT_LIST_DEFAULT_SORT;
        }

        $keys = [$primary];
        foreach (self::PRODUCT_LIST_SORT_SEQUENCE as $key) {
            if ($key !== $primary) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $codes
     */
    private function productTitlesByCodes(array $codes): Collection
    {
        $titles = collect();
        foreach (array_chunk($codes, 400) as $codeChunk) {
            $titles = $titles->merge(
                TrackerDatabase::table('tracking_product')
                    ->whereIn('code', $codeChunk)
                    ->pluck('title', 'code'),
            );
        }

        return $titles;
    }

    /**
     * @param  Collection<string, string>|null  $titles
     */
    private function compareProductListSortRows(array $a, array $b, string $key, ?Collection $titles): int
    {
        if ($key === 'product') {
            $codeA = (string) ($a['code'] ?? '');
            $codeB = (string) ($b['code'] ?? '');
            $labelA = strtolower((string) ($titles?->get($codeA, $codeA))).'|'.$codeA;
            $labelB = strtolower((string) ($titles?->get($codeB, $codeB))).'|'.$codeB;

            return $labelA <=> $labelB;
        }

        return $this->productListSortMetricValue($a, $key) <=> $this->productListSortMetricValue($b, $key);
    }

    private function productListSortMetricValue(array $row, string $key): int|float
    {
        return match ($key) {
            'views' => (int) ($row['views'] ?? 0),
            'add_to_cart' => (int) ($row['adds'] ?? 0),
            'begin_checkout' => (int) ($row['begin_checkouts'] ?? 0),
            'proceed_checkout' => (int) ($row['proceed_checkouts'] ?? 0),
            'paid' => (int) ($row['qty'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? 0),
            default => 0,
        };
    }

    /**
     * Scale tracking_daily_products by filtered-session ratio per day (same pattern as categories).
     *
     * @param  array<string, mixed>  $sessionFilters
     */
    private function productsWithSessionFilters(Carbon $from, Carbon $to, string $period, array $sessionFilters, ?int $limit = self::PRODUCT_LIMIT): array
    {
        $withOrderOnly = ($sessionFilters['has_order'] ?? null) === true;
        $soldByCode = $this->orderSoldTotalsByParentCode($from, $to, $period, $sessionFilters);

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

        $all = $this->applyProductListCategoryScope(collect(array_values($byCode)), $from, $to, $sessionFilters);

        return $this->packageProductRows($all, $limit, $from, $to, $period, $sessionFilters);
    }

    /**
     * Paid units / sale amount per parent style — same session scope and order headers as dashboard Sale & conversion.
     *
     * @param  array<string, mixed>  $sessionFilters
     * @return array<string, array{qty: int, revenue: float}>
     */
    private function orderSoldTotalsByParentCode(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $ordersQuery = TrackerDatabase::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 'ts'));

        if ($sessionFilters !== []) {
            $ordersQuery->tap(fn ($query) => $this->activityList->constrainDashboardSessionsInRange($query, $range, $sessionFilters));
        }

        $orders = $ordersQuery->get(['o.id', 'o.amount_paid']);

        if ($orders->isEmpty()) {
            return [];
        }

        $details = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_product as p', 'p.id', '=', 'od.tracking_product_id')
            ->whereIn('od.tracking_order_id', $orders->pluck('id'))
            ->get(['od.tracking_order_id', 'p.code as product_code', 'od.qty', 'od.line_total']);

        $linesByOrder = [];
        foreach ($details as $line) {
            $linesByOrder[(int) $line->tracking_order_id][] = $line;
        }

        $totals = [];
        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $lines = $linesByOrder[$orderId] ?? [];
            if ($lines === []) {
                continue;
            }

            $amountPaid = (float) $order->amount_paid;
            $weightSum = 0.0;
            foreach ($lines as $line) {
                $weight = (float) $line->line_total;
                if ($weight <= 0) {
                    $weight = (float) max(1, (int) $line->qty);
                }
                $weightSum += $weight;
            }

            if ($weightSum <= 0) {
                $weightSum = (float) count($lines);
            }

            foreach ($lines as $line) {
                $code = trim((string) $line->product_code);
                if ($code === '') {
                    continue;
                }

                $weight = (float) $line->line_total;
                if ($weight <= 0) {
                    $weight = (float) max(1, (int) $line->qty);
                }
                if ($weightSum <= 0) {
                    $weight = 1.0;
                    $weightSum = (float) count($lines);
                }

                $share = $amountPaid * ($weight / $weightSum);
                $totals[$code] ??= ['qty' => 0, 'revenue' => 0.0];
                $totals[$code]['qty'] += (int) $line->qty;
                $totals[$code]['revenue'] += $share;
            }
        }

        foreach ($totals as $code => $row) {
            $totals[$code]['revenue'] = round((float) $row['revenue'], 2);
        }

        return $totals;
    }

}
