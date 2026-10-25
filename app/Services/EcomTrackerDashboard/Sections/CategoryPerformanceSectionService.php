<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Services\EcomActivityListService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;

class CategoryPerformanceSectionService
{
    /** Order lines with no resolved category roll up here so sold totals match KPI items sold. */
    private const UNCategorized_CATEGORY_ID = 0;

    use \App\Services\EcomTrackerDashboard\Concerns\InteractsWithSessionFilterRatios;

    public function categories(Carbon $from, Carbon $to, string $period, array $sessionFilters = []): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (EcomActivityListService::hasActiveFilters($sessionFilters)) {
            return $this->categoriesWithSessionFilters($from, $to, $period, $sessionFilters);
        }

        $rows = TrackerDatabase::table('tracking_daily_categories as dc')
            ->join('tracking_category as c', 'c.id', '=', 'dc.tracking_category_id')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->whereBetween('dc.metric_date', [$fromDate, $toDate])
            ->groupBy('c.id', 'c.name', 'd.id', 'd.name')
            ->selectRaw('c.id, c.name, d.id as department_id, d.name as department_name')
            ->selectRaw('SUM(dc.category_views) as category_views, SUM(dc.product_views) as product_views, SUM(dc.add_to_carts) as adds')
            ->selectRaw('SUM(dc.proceed_checkouts) as proceed_checkouts, SUM(dc.sold_qty) as sale_items, SUM(dc.sale_amount) as sale_amount')
            ->get();

        $soldByCategory = $this->scopedCategorySoldTotals($from, $to, $period, []);

        $metrics = ['category_views', 'product_views', 'adds', 'proceed_checkouts', 'sale_items', 'sale_amount'];
        $sort = fn (array $a, array $b) => [$b['sale_amount'], $b['product_views']] <=> [$a['sale_amount'], $a['product_views']];

        $departments = [];
        foreach ($rows as $row) {
            $departmentId = (int) ($row->department_id ?? 0);
            $categoryId = (int) $row->id;
            $sold = $soldByCategory[$categoryId] ?? ['sale_items' => (int) $row->sale_items, 'sale_amount' => (float) $row->sale_amount];

            $category = [
                'category_id' => $categoryId,
                'category_name' => (string) $row->name,
                'department_id' => $departmentId ?: null,
                'category_views' => (int) $row->category_views,
                'product_views' => (int) $row->product_views,
                'adds' => (int) $row->adds,
                'proceed_checkouts' => (int) $row->proceed_checkouts,
                'sale_items' => (int) $sold['sale_items'],
                'sale_amount' => (float) $sold['sale_amount'],
            ];

            $departments[$departmentId] ??= ['key' => 'department-'.$departmentId, 'name' => (string) ($row->department_name ?? 'Other'), 'categories' => []] + array_fill_keys($metrics, 0);
            $departments[$departmentId]['categories'][] = $category;
            foreach ($metrics as $metric) {
                $departments[$departmentId][$metric] += $category[$metric];
            }
        }

        $this->appendSoldOnlyCategories($departments, $soldByCategory, $metrics, $sort);

        foreach ($departments as &$department) {
            usort($department['categories'], $sort);
            $department['category_count'] = count($department['categories']);
        }
        unset($department);
        usort($departments, $sort);

        return [
            'departments' => $departments,
            'totals' => [
                'category_views' => (int) $rows->sum('category_views'),
                'product_views' => (int) $rows->sum('product_views'),
                'category_count' => $rows->count(),
            ],
        ];
    }

    private function categoriesWithSessionFilters(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $ratios = $this->sessionFilterRatiosByDate($from, $to, $period, $sessionFilters);
        $dailyRows = TrackerDatabase::table('tracking_daily_categories as dc')
            ->join('tracking_category as c', 'c.id', '=', 'dc.tracking_category_id')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->whereBetween('dc.metric_date', [$from->toDateString(), $to->toDateString()])
            ->get([
                'dc.metric_date',
                'c.id',
                'c.name',
                'd.id as department_id',
                'd.name as department_name',
                'dc.category_views',
                'dc.product_views',
                'dc.add_to_carts',
                'dc.proceed_checkouts',
            ]);

        $soldByCategory = $this->scopedCategorySoldTotals($from, $to, $period, $sessionFilters);
        $metrics = ['category_views', 'product_views', 'adds', 'proceed_checkouts', 'sale_items', 'sale_amount'];
        $sort = fn (array $a, array $b) => [$b['sale_amount'], $b['product_views']] <=> [$a['sale_amount'], $a['product_views']];

        $categoryMap = [];

        foreach ($dailyRows as $row) {
            $ratio = $ratios[(string) $row->metric_date] ?? 0.0;
            if ($ratio <= 0) {
                continue;
            }

            $categoryId = (int) $row->id;
            $departmentId = (int) ($row->department_id ?? 0);

            $categoryMap[$categoryId] ??= [
                'category_id' => $categoryId,
                'category_name' => (string) $row->name,
                'department_id' => $departmentId ?: null,
                'department_name' => (string) ($row->department_name ?? 'Other'),
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0.0,
            ];

            $categoryMap[$categoryId]['category_views'] += (int) round((int) $row->category_views * $ratio);
            $categoryMap[$categoryId]['product_views'] += (int) round((int) $row->product_views * $ratio);
            $categoryMap[$categoryId]['adds'] += (int) round((int) $row->add_to_carts * $ratio);
            $categoryMap[$categoryId]['proceed_checkouts'] += (int) round((int) $row->proceed_checkouts * $ratio);
        }

        foreach ($soldByCategory as $categoryId => $sold) {
            $categoryId = (int) $categoryId;
            [$categoryName, $departmentId, $departmentName] = $this->categoryMetaForPerformance($categoryId);
            $categoryMap[$categoryId] ??= [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'department_id' => $departmentId ?: null,
                'department_name' => $departmentName,
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0.0,
            ];
            $categoryMap[$categoryId]['sale_items'] = (int) $sold['sale_items'];
            $categoryMap[$categoryId]['sale_amount'] = (float) $sold['sale_amount'];
        }

        $departments = [];
        foreach ($categoryMap as $category) {
            $departmentId = (int) ($category['department_id'] ?? 0);
            $departments[$departmentId] ??= [
                'key' => 'department-'.$departmentId,
                'name' => (string) $category['department_name'],
                'categories' => [],
            ] + array_fill_keys($metrics, 0);
            unset($category['department_name']);
            $departments[$departmentId]['categories'][] = $category;
            foreach ($metrics as $metric) {
                $departments[$departmentId][$metric] += $category[$metric];
            }
        }

        $categoryTotals = ['category_views' => 0, 'product_views' => 0, 'category_count' => count($categoryMap)];
        foreach ($departments as &$department) {
            usort($department['categories'], $sort);
            $department['category_count'] = count($department['categories']);
            $categoryTotals['category_views'] += (int) $department['category_views'];
            $categoryTotals['product_views'] += (int) $department['product_views'];
        }
        unset($department);
        usort($departments, $sort);

        return ['departments' => array_values($departments), 'totals' => $categoryTotals];
    }

    private function scopedCategorySoldTotals(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        [$fromBound, $toBound] = TrackerTime::storageRange($from, $to);
        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $rows = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->whereBetween('o.ordered_at', [$fromBound, $toBound])
            ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 'ts'))
            ->when(
                EcomActivityListService::hasActiveFilters($sessionFilters),
                fn ($query) => $this->activityList->constrainDashboardSessionsInRange($query, $range, $sessionFilters),
            )
            ->groupBy('category_id')
            ->selectRaw('COALESCE(od.tracking_category_id, ?) as category_id', [self::UNCategorized_CATEGORY_ID])
            ->selectRaw('SUM(od.qty) as sale_items, SUM(od.line_total) as sale_amount')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->category_id] = [
                'sale_items' => (int) $row->sale_items,
                'sale_amount' => round((float) $row->sale_amount, 2),
            ];
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $departments
     * @param  array<int, array{sale_items: int, sale_amount: float}>  $soldByCategory
     * @param  list<string>  $metrics
     */
    private function appendSoldOnlyCategories(array &$departments, array $soldByCategory, array $metrics, callable $sort): void
    {
        $present = [];
        foreach ($departments as $department) {
            foreach ($department['categories'] as $category) {
                $present[(int) ($category['category_id'] ?? 0)] = true;
            }
        }

        foreach ($soldByCategory as $categoryId => $sold) {
            $categoryId = (int) $categoryId;
            if (isset($present[$categoryId])) {
                continue;
            }

            if ((int) ($sold['sale_items'] ?? 0) === 0 && (float) ($sold['sale_amount'] ?? 0.0) === 0.0) {
                continue;
            }

            [$categoryName, $departmentId, $departmentName] = $this->categoryMetaForPerformance($categoryId);
            $category = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'department_id' => $departmentId ?: null,
                'category_views' => 0,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => (int) $sold['sale_items'],
                'sale_amount' => (float) $sold['sale_amount'],
            ];

            $departments[$departmentId] ??= [
                'key' => 'department-'.$departmentId,
                'name' => $departmentName,
                'categories' => [],
            ] + array_fill_keys($metrics, 0);
            $departments[$departmentId]['categories'][] = $category;
            foreach ($metrics as $metric) {
                $departments[$departmentId][$metric] += $category[$metric];
            }
            $present[$categoryId] = true;
        }
    }

    /**
     * @return array{0: string, 1: int, 2: string}
     */
    private function categoryMetaForPerformance(int $categoryId): array
    {
        if ($categoryId === self::UNCategorized_CATEGORY_ID) {
            return ['Uncategorized', 0, 'Other'];
        }

        $row = TrackerDatabase::table('tracking_category as c')
            ->leftJoin('tracking_category as d', 'd.id', '=', 'c.parent_id')
            ->where('c.id', $categoryId)
            ->first(['c.name as category_name', 'd.id as department_id', 'd.name as department_name']);

        if ($row === null) {
            return ['Category #'.$categoryId, 0, 'Other'];
        }

        return [
            (string) $row->category_name,
            (int) ($row->department_id ?? 0),
            (string) ($row->department_name ?? 'Other'),
        ];
    }

}
