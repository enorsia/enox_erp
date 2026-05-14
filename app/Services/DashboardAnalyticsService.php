<?php

namespace App\Services;

use App\Models\DailyReturn;
use App\Models\DailySale;
use App\Models\MonthlyBudget;
use App\Models\ReturnReasonType;
use App\Models\SalePlatform;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    // ── Date range resolution ──────────────────────────────────────

    /**
     * Convert filter inputs into a concrete date range.
     * Returns ['from' => Carbon, 'to' => Carbon, 'label' => string, 'months' => array of ['year'=>int,'month'=>int]]
     */
    public function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';
        $now    = Carbon::now();

        switch ($period) {
            case 'last_month':
                $from  = $now->copy()->subMonth()->startOfMonth();
                $to    = $now->copy()->subMonth()->endOfMonth();
                $label = 'Last Month (' . $from->format('M Y') . ')';
                break;

            case 'last_3_months':
                $from  = $now->copy()->subMonths(2)->startOfMonth();
                $to    = $now->copy()->endOfMonth();
                $label = 'Last 3 Months';
                break;

            case 'last_6_months':
                $from  = $now->copy()->subMonths(5)->startOfMonth();
                $to    = $now->copy()->endOfMonth();
                $label = 'Last 6 Months';
                break;

            case 'last_1_year':
                $from  = $now->copy()->subMonths(11)->startOfMonth();
                $to    = $now->copy()->endOfMonth();
                $label = 'Last 12 Months';
                break;

            case 'custom':
                $fromRaw = $filters['from_year_month'] ?? $now->format('Y-m');
                $toRaw   = $filters['to_year_month']   ?? $now->format('Y-m');
                // Ensure from <= to
                if ($fromRaw > $toRaw) {
                    [$fromRaw, $toRaw] = [$toRaw, $fromRaw];
                }
                $from  = Carbon::createFromFormat('Y-m', $fromRaw)->startOfMonth();
                $to    = Carbon::createFromFormat('Y-m', $toRaw)->endOfMonth();
                $label = 'Custom: ' . $from->format('M Y') . ' – ' . $to->format('M Y');
                break;

            default: // this_month
                $from  = $now->copy()->startOfMonth();
                $to    = $now->copy()->endOfMonth();
                $label = 'This Month (' . $from->format('M Y') . ')';
                break;
        }

        // Generate sorted list of year-month pairs covered by the range
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $end    = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor->addMonth();
        }

        return [
            'from'   => $from,
            'to'     => $to,
            'label'  => $label,
            'period' => $period,
            'months' => $months,
        ];
    }

    // ── KPI Summary Cards ─────────────────────────────────────────

    public function getSummaryCards(string $dateFrom, string $dateTo, array $months): array
    {
        $sales = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COALESCE(SUM(sales), 0)                AS total_sales,
                COALESCE(SUM(spent), 0)                AS total_spent,
                COALESCE(SUM(number_of_orders), 0)     AS total_orders,
                COALESCE(SUM(number_of_quantities), 0) AS total_quantities
            ')
            ->first();

        $returns = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COALESCE(SUM(number_of_returns), 0)           AS total_returns,
                COALESCE(SUM(number_of_return_quantities), 0) AS total_return_quantities
            ')
            ->first();

        $budgetTotal = 0;
        if (!empty($months)) {
            $conditions = [];
            foreach ($months as $m) {
                $conditions[] = "(year = {$m['year']} AND month = {$m['month']})";
            }
            $budgetTotal = MonthlyBudget::whereRaw('(' . implode(' OR ', $conditions) . ')')->sum('budget');
        }

        $totalSales   = (float) ($sales->total_sales   ?? 0);
        $totalSpent   = (float) ($sales->total_spent   ?? 0);
        $totalOrders  = (int)   ($sales->total_orders  ?? 0);
        $totalReturns = (int)   ($returns->total_returns ?? 0);
        $netProfit    = $totalSales - $totalSpent;
        $returnRate   = $totalOrders > 0 ? round(($totalReturns / $totalOrders) * 100, 2) : 0;
        $roi          = $totalSpent  > 0 ? round(($netProfit / $totalSpent) * 100, 2) : 0;
        $avgOrderVal  = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;
        $budgetUtil   = ($budgetTotal > 0) ? round(($totalSales / $budgetTotal) * 100, 2) : null;

        return [
            'total_sales'        => $totalSales,
            'total_spent'        => $totalSpent,
            'total_orders'       => $totalOrders,
            'total_quantities'   => (int) ($sales->total_quantities ?? 0),
            'total_returns'      => $totalReturns,
            'total_return_qty'   => (int) ($returns->total_return_quantities ?? 0),
            'total_budget'       => (float) $budgetTotal,
            'net_profit'         => $netProfit,
            'return_rate'        => $returnRate,
            'roi'                => $roi,
            'avg_order_value'    => $avgOrderVal,
            'budget_utilisation' => $budgetUtil,
        ];
    }

    // ── Monthly Trend (Sales, Spent, Orders, Returns, Budget) ─────

    public function getMonthlySalesTrend(string $dateFrom, string $dateTo): array
    {
        // Group by the raw expression — alias grouping is rejected by MySQL strict mode
        $sales = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS ym, SUM(sales) AS total_sales, SUM(spent) AS total_spent, SUM(number_of_orders) AS total_orders")
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->orderByRaw("ym")
            ->get()
            ->keyBy('ym');

        $returns = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS ym, SUM(number_of_returns) AS total_returns")
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->orderByRaw("ym")
            ->get()
            ->keyBy('ym');

        // Budget: group by year + month (integer columns — safe in strict mode)
        $budgetsByMonth = MonthlyBudget::selectRaw("year, month, SUM(budget) AS total_budget")
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->year, $r->month));

        $allYms = $sales->keys()->merge($returns->keys())->unique()->sort()->values();

        $labels = $salesData = $spentData = $ordersData = $returnsData = $budgetData = [];

        foreach ($allYms as $ym) {
            $labels[]      = Carbon::createFromFormat('Y-m', $ym)->format('M Y');
            $salesData[]   = (float) ($sales[$ym]->total_sales    ?? 0);
            $spentData[]   = (float) ($sales[$ym]->total_spent    ?? 0);
            $ordersData[]  = (int)   ($sales[$ym]->total_orders   ?? 0);
            $returnsData[] = (int)   ($returns[$ym]->total_returns ?? 0);
            $budgetData[]  = (float) ($budgetsByMonth[$ym]->total_budget ?? 0);
        }

        return compact('labels', 'salesData', 'spentData', 'ordersData', 'returnsData', 'budgetData');
    }

    // ── Platform Sales Breakdown ──────────────────────────────────

    public function getPlatformSalesBreakdown(string $dateFrom, string $dateTo): array
    {
        // Include sale_platforms.id in GROUP BY so COALESCE(..., sale_platforms.id) is valid,
        // OR simply omit the COALESCE column from SELECT (it isn't used further).
        $rows = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->join('sale_platforms', 'sale_platforms.id', '=', 'daily_sales.sale_platform_id')
            ->selectRaw('
                sale_platforms.id   AS platform_id,
                sale_platforms.name AS platform_name,
                SUM(daily_sales.sales)            AS total_sales,
                SUM(daily_sales.number_of_orders) AS total_orders
            ')
            ->groupBy('sale_platforms.id', 'sale_platforms.name')
            ->orderByDesc('total_sales')
            ->get();

        $top = $rows->take(10);

        return [
            'labels' => $top->pluck('platform_name')->toArray(),
            'sales'  => $top->pluck('total_sales')->map(fn ($v) => (float) $v)->toArray(),
            'orders' => $top->pluck('total_orders')->map(fn ($v) => (int)   $v)->toArray(),
        ];
    }

    // ── Return Reasons Breakdown ──────────────────────────────────

    public function getReturnReasonsBreakdown(string $dateFrom, string $dateTo): array
    {
        $rows = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->join('return_reason_types', 'return_reason_types.id', '=', 'daily_returns.return_reason_type_id')
            ->selectRaw('return_reason_types.id, return_reason_types.name AS reason, SUM(daily_returns.number_of_returns) AS total')
            ->groupBy('return_reason_types.id', 'return_reason_types.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('reason')->toArray(),
            'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    // ── Gender Breakdown ──────────────────────────────────────────

    public function getGenderBreakdown(string $dateFrom, string $dateTo): array
    {
        $sales = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COALESCE(SUM(number_of_male_orders),   0) AS male,
                COALESCE(SUM(number_of_female_orders), 0) AS female,
                COALESCE(SUM(number_of_kids_orders),   0) AS kids
            ')
            ->first();

        $returns = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COALESCE(SUM(number_of_male_returns),   0) AS male,
                COALESCE(SUM(number_of_female_returns), 0) AS female,
                COALESCE(SUM(number_of_kids_returns),   0) AS kids
            ')
            ->first();

        return [
            'orders'  => [
                'male'   => (int) ($sales->male   ?? 0),
                'female' => (int) ($sales->female ?? 0),
                'kids'   => (int) ($sales->kids   ?? 0),
            ],
            'returns' => [
                'male'   => (int) ($returns->male   ?? 0),
                'female' => (int) ($returns->female ?? 0),
                'kids'   => (int) ($returns->kids   ?? 0),
            ],
        ];
    }

    // ── Platform Returns Breakdown ────────────────────────────────

    public function getPlatformReturnsBreakdown(string $dateFrom, string $dateTo): array
    {
        $rows = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->join('sale_platforms', 'sale_platforms.id', '=', 'daily_returns.sale_platform_id')
            ->selectRaw('sale_platforms.id, sale_platforms.name AS platform_name, SUM(daily_returns.number_of_returns) AS total_returns')
            ->groupBy('sale_platforms.id', 'sale_platforms.name')
            ->orderByDesc('total_returns')
            ->limit(10)
            ->get();

        return [
            'labels'  => $rows->pluck('platform_name')->toArray(),
            'returns' => $rows->pluck('total_returns')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    // ── Budget vs Actual (Monthly) ────────────────────────────────

    public function getBudgetVsActual(array $months): array
    {
        if (empty($months)) {
            return ['labels' => [], 'budget' => [], 'actual' => []];
        }

        $conditions = [];
        foreach ($months as $m) {
            $conditions[] = "(year = {$m['year']} AND month = {$m['month']})";
        }

        // Group by integer columns year + month — always safe in strict mode
        $budgets = MonthlyBudget::whereRaw('(' . implode(' OR ', $conditions) . ')')
            ->selectRaw('year, month, SUM(budget) AS total_budget')
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->year, $r->month));

        $firstMonth = $months[0];
        $lastMonth  = $months[count($months) - 1];
        $dateFrom   = Carbon::createFromDate($firstMonth['year'], $firstMonth['month'], 1)->startOfMonth()->toDateString();
        $dateTo     = Carbon::createFromDate($lastMonth['year'],  $lastMonth['month'],  1)->endOfMonth()->toDateString();

        $salesByMonth = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS ym, SUM(sales) AS total_sales")
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->get()
            ->keyBy('ym');

        $labels = $budget = $actual = [];
        foreach ($months as $m) {
            $ym       = sprintf('%04d-%02d', $m['year'], $m['month']);
            $labels[] = Carbon::createFromDate($m['year'], $m['month'], 1)->format('M Y');
            $budget[] = (float) ($budgets[$ym]->total_budget  ?? 0);
            $actual[] = (float) ($salesByMonth[$ym]->total_sales ?? 0);
        }

        return compact('labels', 'budget', 'actual');
    }

    // ── Platform Cost vs Sales (with ROAS per platform) ──────────

    public function getPlatformCostVsSales(string $dateFrom, string $dateTo): array
    {
        $rows = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->join('sale_platforms', 'sale_platforms.id', '=', 'daily_sales.sale_platform_id')
            ->selectRaw('sale_platforms.id, sale_platforms.name,
                SUM(daily_sales.spent) AS total_cost,
                SUM(daily_sales.sales) AS total_sales,
                SUM(daily_sales.number_of_orders) AS total_orders')
            ->groupBy('sale_platforms.id', 'sale_platforms.name')
            ->orderByDesc('total_sales')
            ->get();

        return [
            'labels' => $rows->pluck('name')->toArray(),
            'cost'   => $rows->pluck('total_cost')->map(fn ($v) => (float) $v)->toArray(),
            'sales'  => $rows->pluck('total_sales')->map(fn ($v) => (float) $v)->toArray(),
            'roas'   => $rows->map(fn ($r) => $r->total_cost > 0
                ? round((float) $r->total_sales / (float) $r->total_cost, 2) : 0
            )->toArray(),
        ];
    }

    // ── Weekly Trend ──────────────────────────────────────────────

    public function getWeeklyTrend(string $dateFrom, string $dateTo): array
    {
        $sales = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("YEARWEEK(date, 1) AS yw, MIN(date) AS week_start,
                SUM(sales) AS total_sales, SUM(spent) AS total_spent,
                SUM(number_of_orders) AS total_orders")
            ->groupByRaw('YEARWEEK(date, 1)')
            ->orderByRaw('YEARWEEK(date, 1)')
            ->get();

        $returns = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('YEARWEEK(date, 1) AS yw, SUM(number_of_returns) AS total_returns')
            ->groupByRaw('YEARWEEK(date, 1)')
            ->get()
            ->keyBy('yw');

        $labels      = [];
        $salesData   = [];
        $spentData   = [];
        $ordersData  = [];
        $returnsData = [];

        foreach ($sales as $idx => $row) {
            $wLabel        = 'W' . ($idx + 1) . ' (' . Carbon::parse($row->week_start)->format('d M') . ')';
            $labels[]      = $wLabel;
            $salesData[]   = (float) $row->total_sales;
            $spentData[]   = (float) $row->total_spent;
            $ordersData[]  = (int) $row->total_orders;
            $returnsData[] = (int) ($returns[$row->yw]->total_returns ?? 0);
        }

        return compact('labels', 'salesData', 'spentData', 'ordersData', 'returnsData');
    }

    // ── Forecasting (daily avg × 30) ──────────────────────────────

    public function getForecasting(string $dateFrom, string $dateTo): array
    {
        $days = max(1, Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1);

        $totals = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(SUM(sales),0) AS s, COALESCE(SUM(spent),0) AS sp,
                COALESCE(SUM(number_of_orders),0) AS o, COALESCE(SUM(number_of_quantities),0) AS q')
            ->first();

        $s  = (float) ($totals->s  ?? 0);
        $sp = (float) ($totals->sp ?? 0);
        $o  = (int)   ($totals->o  ?? 0);
        $q  = (int)   ($totals->q  ?? 0);

        return [
            'actual_days'        => $days,
            'avg_daily_sales'    => round($s  / $days, 2),
            'avg_daily_spent'    => round($sp / $days, 2),
            'avg_daily_orders'   => round($o  / $days, 2),
            'forecast_30_sales'  => round(($s  / $days) * 30, 2),
            'forecast_30_spent'  => round(($sp / $days) * 30, 2),
            'forecast_30_orders' => (int) round(($o / $days) * 30),
            'total_roas'         => $sp > 0 ? round(($s / $sp) * 100, 2) : 0,
        ];
    }

    // ── Platform Budget Balance ───────────────────────────────────

    public function getPlatformBudgets(array $months): array
    {
        if (empty($months)) {
            return ['labels' => [], 'budget' => [], 'spent' => [], 'balance' => []];
        }

        $conditions = [];
        foreach ($months as $m) {
            $conditions[] = "(year = {$m['year']} AND month = {$m['month']})";
        }
        $whereClause = '(' . implode(' OR ', $conditions) . ')';

        $budgets = MonthlyBudget::whereRaw($whereClause)
            ->join('sale_platforms', 'sale_platforms.id', '=', 'monthly_budgets.sale_platform_id')
            ->selectRaw('sale_platforms.id, sale_platforms.name, SUM(monthly_budgets.budget) AS total_budget')
            ->groupBy('sale_platforms.id', 'sale_platforms.name')
            ->get()
            ->keyBy('id');

        $firstMonth = $months[0];
        $lastMonth  = $months[count($months) - 1];
        $dateFrom   = Carbon::createFromDate($firstMonth['year'], $firstMonth['month'], 1)->startOfMonth()->toDateString();
        $dateTo     = Carbon::createFromDate($lastMonth['year'], $lastMonth['month'], 1)->endOfMonth()->toDateString();

        $spent = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->join('sale_platforms', 'sale_platforms.id', '=', 'daily_sales.sale_platform_id')
            ->selectRaw('sale_platforms.id, sale_platforms.name, SUM(daily_sales.spent) AS total_spent')
            ->groupBy('sale_platforms.id', 'sale_platforms.name')
            ->get()
            ->keyBy('id');

        $all = $budgets->keys()->merge($spent->keys())->unique();

        $labels  = [];
        $budget  = [];
        $spentArr = [];
        $balance = [];

        foreach ($all as $id) {
            $b  = (float) ($budgets[$id]->total_budget ?? 0);
            $s  = (float) ($spent[$id]->total_spent    ?? 0);
            $name = $budgets[$id]->name ?? ($spent[$id]->name ?? "Platform $id");
            $labels[]   = $name;
            $budget[]   = $b;
            $spentArr[] = $s;
            $balance[]  = $b - $s;
        }

        return ['labels' => $labels, 'budget' => $budget, 'spent' => $spentArr, 'balance' => $balance];
    }

    // ── Full daily pivot data for Excel export ─────────────────────

    public function getDailyExportData(string $dateFrom, string $dateTo, array $months): array
    {
        // 1. All platforms with hierarchy flags
        $allPlatforms = SalePlatform::orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'parent_id', 'is_spent', 'is_sales']);

        // 2. Platform IDs that have data in the range
        $dataIds = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->distinct()->pluck('sale_platform_id')->toArray();

        // 3. Build hierarchical column layout
        $columnData = $this->buildPlatformColumnData($allPlatforms, $dataIds);

        // 4. Root platforms for order / qty grouping
        $rootPlatforms   = $allPlatforms->whereNull('parent_id')->values();
        $childrenByRoot  = $this->buildChildMap($allPlatforms, $rootPlatforms);

        // 5. Daily sales rows
        $dailyRows = DailySale::whereBetween('date', [$dateFrom, $dateTo])
            ->get(['sale_platform_id', 'date', 'spent', 'sales',
                   'number_of_orders', 'number_of_quantities',
                   'number_of_male_orders', 'number_of_female_orders', 'number_of_kids_orders',
                   'number_of_male_quantities', 'number_of_female_quantities', 'number_of_kids_quantities']);

        $byDatePlatform = [];
        foreach ($dailyRows as $row) {
            $d = $row->date->toDateString();
            $byDatePlatform[$d][$row->sale_platform_id] = $row;
        }

        // 6. Daily returns by date
        $dailyReturns  = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->get(['date', 'number_of_returns']);
        $returnsByDate = [];
        foreach ($dailyReturns as $r) {
            $d = $r->date->toDateString();
            $returnsByDate[$d] = ($returnsByDate[$d] ?? 0) + $r->number_of_returns;
        }

        // 7. Budget per platform
        $budgetMap = [];
        if (!empty($months)) {
            $conditions = [];
            foreach ($months as $m) {
                $conditions[] = "(year = {$m['year']} AND month = {$m['month']})";
            }
            $budgetRows = MonthlyBudget::whereRaw('(' . implode(' OR ', $conditions) . ')')
                ->selectRaw('sale_platform_id, SUM(budget) AS total_budget')
                ->groupBy('sale_platform_id')
                ->pluck('total_budget', 'sale_platform_id');
            $budgetMap = $budgetRows->toArray();
        }

        // 8. Build ordered date list
        $dates   = [];
        $cursor  = Carbon::parse($dateFrom);
        $end     = Carbon::parse($dateTo);
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // 9. Build daily data rows
        $weekNumber     = 0;
        $currentWeekNum = null;
        $rows           = [];

        // Collect unique platform IDs from leaf columns for fast lookup
        $leafPlatformIds = array_unique(array_column($columnData['columns'], 'platform_id'));

        foreach ($dates as $date) {
            $isoWeek = Carbon::parse($date)->isoWeek();
            if ($isoWeek !== $currentWeekNum) {
                $weekNumber++;
                $currentWeekNum = $isoWeek;
            }

            $platformData = $byDatePlatform[$date] ?? [];

            $totalSales  = 0;
            $totalSpent  = 0;
            $totalOrders = 0;
            $totalQty    = 0;
            $totalKids   = 0;
            $totalFemale = 0;
            $totalMale   = 0;

            foreach ($platformData as $row) {
                $totalSales  += (float) $row->sales;
                $totalSpent  += (float) $row->spent;
                $totalOrders += (int)   $row->number_of_orders;
                $totalQty    += (int)   $row->number_of_quantities;
                $totalKids   += (int)   ($row->number_of_kids_orders   ?? 0);
                $totalFemale += (int)   ($row->number_of_female_orders ?? 0);
                $totalMale   += (int)   ($row->number_of_male_orders   ?? 0);
            }

            $roas = $totalSpent > 0 ? ($totalSales / $totalSpent) : 0;

            // Per leaf-platform values
            $perPlatform = [];
            foreach ($leafPlatformIds as $pid) {
                $r = $platformData[$pid] ?? null;
                $perPlatform[$pid] = [
                    'cost'   => $r ? (float) $r->spent              : 0,
                    'sales'  => $r ? (float) $r->sales              : 0,
                    'orders' => $r ? (int)   $r->number_of_orders   : 0,
                    'qty'    => $r ? (int)   $r->number_of_quantities : 0,
                ];
            }

            // Per-root order/qty totals
            $perRoot = [];
            foreach ($rootPlatforms as $root) {
                $ids    = $childrenByRoot[$root->id] ?? [$root->id];
                $orders = 0;
                $qty    = 0;
                foreach ($ids as $pid) {
                    $r = $platformData[$pid] ?? null;
                    if ($r) {
                        $orders += (int) $r->number_of_orders;
                        $qty    += (int) $r->number_of_quantities;
                    }
                }
                $perRoot[$root->id] = ['name' => $root->name, 'orders' => $orders, 'qty' => $qty];
            }

            $rows[] = [
                'date'         => $date,
                'week'         => $weekNumber,
                'total_sales'  => $totalSales,
                'total_spent'  => $totalSpent,
                'roas'         => $roas,          // decimal fraction
                'total_orders' => $totalOrders,
                'total_qty'    => $totalQty,
                'kids'         => $totalKids,
                'female'       => $totalFemale,
                'male'         => $totalMale,
                'returns'      => $returnsByDate[$date] ?? 0,
                'platform'     => $perPlatform,
                'root_groups'  => $perRoot,
            ];
        }

        // 10. Column totals
        $totals = ['sales' => 0, 'spent' => 0, 'orders' => 0, 'qty' => 0,
                   'kids'  => 0, 'female' => 0, 'male'  => 0];
        $platformTotals = [];
        foreach ($leafPlatformIds as $pid) {
            $platformTotals[$pid] = ['cost' => 0, 'sales' => 0, 'orders' => 0, 'qty' => 0];
        }
        foreach ($rows as $row) {
            $totals['sales']  += $row['total_sales'];
            $totals['spent']  += $row['total_spent'];
            $totals['orders'] += $row['total_orders'];
            $totals['qty']    += $row['total_qty'];
            $totals['kids']   += $row['kids'];
            $totals['female'] += $row['female'];
            $totals['male']   += $row['male'];
            foreach ($leafPlatformIds as $pid) {
                $platformTotals[$pid]['cost']   += $row['platform'][$pid]['cost'];
                $platformTotals[$pid]['sales']  += $row['platform'][$pid]['sales'];
                $platformTotals[$pid]['orders'] += $row['platform'][$pid]['orders'];
                $platformTotals[$pid]['qty']    += $row['platform'][$pid]['qty'];
            }
        }

        $dayCount      = max(1, count(array_unique(array_column($rows, 'date'))));
        $avgDailySales = $totals['sales'] / $dayCount;
        $avgDailySpent = $totals['spent'] / $dayCount;

        // 11. Root-grouped order/qty totals for summary
        $rootOrderTotals = [];
        $rootQtyTotals   = [];
        foreach ($rootPlatforms as $root) {
            $rootOrderTotals[$root->id] = 0;
            $rootQtyTotals[$root->id]   = 0;
            foreach ($rows as $row) {
                $rootOrderTotals[$root->id] += ($row['root_groups'][$root->id]['orders'] ?? 0);
                $rootQtyTotals[$root->id]   += ($row['root_groups'][$root->id]['qty']    ?? 0);
            }
        }

        // 12. Build summary rows (all values pre-computed)
        $summaryRows = $this->buildExportSummaryRows(
            $totals,
            $platformTotals,
            $budgetMap,
            $dayCount,
            $rootOrderTotals,
            $rootQtyTotals,
            $columnData['columns']
        );

        // 13. Weekly aggregates for bottom section
        $rootPlatformsArray = $rootPlatforms->values()->toArray();
        $weeklyRows = $this->buildWeeklyAggregates($rows, $rootPlatformsArray);

        // 14. Return-reason breakdown for bottom section
        $returnReasonData = $this->buildReturnReasonData(
            $dateFrom, $dateTo,
            $rootPlatformsArray,
            $childrenByRoot
        );

        return [
            'column_data'        => $columnData,
            'root_platforms'     => $rootPlatforms->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
            'rows'               => $rows,
            'totals'             => $totals,
            'platform_totals'    => $platformTotals,
            'budgets'            => $budgetMap,
            'avg_daily'          => ['sales' => round($avgDailySales, 2), 'spent' => round($avgDailySpent, 2)],
            'forecast'           => ['sales' => round($avgDailySales * 30, 2), 'spent' => round($avgDailySpent * 30, 2)],
            'summary_rows'       => $summaryRows,
            'weekly_rows'        => $weeklyRows,
            'return_reason_data' => $returnReasonData,
            // Legacy alias
            'platforms'          => $allPlatforms->whereIn('id', $dataIds)->values()->toArray(),
        ];
    }

    // ── Platform column data builder ──────────────────────────────

    /**
     * Build hierarchical platform column data for the export.
     *
     * Returns:
     *  columns       – ordered leaf columns [{platform_id, type, name}]
     *  header_levels – array of header level rows for the platform section
     *  max_depth     – number of platform-name header rows (does NOT include the Cost/Sales label row)
     */
    private function buildPlatformColumnData($allPlatforms, array $dataIds): array
    {
        $map = $allPlatforms->keyBy('id');

        // Build tree from roots down, pruning branches with no visible data
        $tree = [];
        foreach ($allPlatforms->whereNull('parent_id') as $root) {
            $node = $this->makePlatformNode($root, $map, $dataIds);
            if ($node !== null) {
                $tree[] = $node;
            }
        }

        if (empty($tree)) {
            return ['columns' => [], 'header_levels' => [], 'max_depth' => 0];
        }

        // Compute how many leaf columns each node spans (bottom-up)
        $this->computeLeafColCounts($tree);

        // Collect ordered leaf columns
        $columns = [];
        $this->collectExportLeafCols($tree, $columns);

        // Max depth of the tree (depth 1 = only root platforms)
        $maxDepth = $this->calcTreeMaxDepth($tree);

        // Build header levels (one array per depth level)
        $headerLevels = [];
        for ($level = 0; $level < $maxDepth; $level++) {
            $colOffset = 0;
            $cells     = [];
            $this->collectHeaderLevelCells($tree, $level, 0, $maxDepth, $colOffset, $cells);
            $headerLevels[] = $cells;
        }

        return [
            'columns'       => $columns,
            'header_levels' => $headerLevels,
            'max_depth'     => $maxDepth,
        ];
    }

    /**
     * Recursively build a tree node. Returns null if the branch has no visible leaf columns.
     */
    private function makePlatformNode($platform, $map, array $dataIds): ?array
    {
        $id = $platform->id;

        // Build child nodes
        $childNodes = [];
        $children   = $map->values()
            ->filter(fn ($p) => $p->parent_id == $id)
            ->sortBy('sort_order')
            ->sortBy('id');
        foreach ($children as $child) {
            $node = $this->makePlatformNode($child, $map, $dataIds);
            if ($node !== null) {
                $childNodes[] = $node;
            }
        }

        $hasData        = in_array($id, $dataIds);
        $hasVisibleCols = $hasData && ((bool) $platform->is_spent || (bool) $platform->is_sales);

        if (empty($childNodes) && !$hasVisibleCols) {
            return null; // prune branch
        }

        return [
            'id'             => $id,
            'name'           => $platform->name,
            'is_spent'       => (bool) $platform->is_spent,
            'is_sales'       => (bool) $platform->is_sales,
            'has_data'       => $hasData,
            'children'       => $childNodes,
            'leaf_col_count' => 0, // set by computeLeafColCounts
        ];
    }

    /**
     * Compute leaf_col_count for every node (bottom-up).
     */
    private function computeLeafColCounts(array &$nodes): int
    {
        $total = 0;
        foreach ($nodes as &$node) {
            if (empty($node['children'])) {
                $node['leaf_col_count'] = ($node['is_spent'] ? 1 : 0) + ($node['is_sales'] ? 1 : 0);
            } else {
                $node['leaf_col_count'] = $this->computeLeafColCounts($node['children']);
            }
            $total += $node['leaf_col_count'];
        }
        return $total;
    }

    /**
     * Collect ordered leaf columns from the tree.
     */
    private function collectExportLeafCols(array $tree, array &$columns): void
    {
        foreach ($tree as $node) {
            if (empty($node['children'])) {
                if ($node['is_spent']) {
                    $columns[] = ['platform_id' => $node['id'], 'type' => 'cost',  'name' => $node['name']];
                }
                if ($node['is_sales']) {
                    $columns[] = ['platform_id' => $node['id'], 'type' => 'sales', 'name' => $node['name']];
                }
            } else {
                $this->collectExportLeafCols($node['children'], $columns);
            }
        }
    }

    /**
     * Get the maximum depth of the tree (depth 1 = roots only, depth 2 = roots+children, …).
     */
    private function calcTreeMaxDepth(array $tree, int $current = 0): int
    {
        $max = $current + 1;
        foreach ($tree as $node) {
            if (!empty($node['children'])) {
                $depth = $this->calcTreeMaxDepth($node['children'], $current + 1);
                $max   = max($max, $depth);
            }
        }
        return $max;
    }

    /**
     * Collect cells for a single header level.
     *
     * @param int $colOffset  passed by reference, advanced as cells are added
     */
    private function collectHeaderLevelCells(
        array  $tree,
        int    $targetLevel,
        int    $currentLevel,
        int    $maxDepth,
        int   &$colOffset,
        array &$cells
    ): void {
        foreach ($tree as $node) {
            if ($node['leaf_col_count'] === 0) {
                continue;
            }

            if ($currentLevel === $targetLevel) {
                // This node belongs in this header row
                $isLeafHere = empty($node['children']);
                // If leaf here, its cell spans down to (but not including) the Cost/Sales label row
                $rowSpan = $isLeafHere ? ($maxDepth - $currentLevel) : 1;

                $cells[] = [
                    'label'      => $node['name'],
                    'col_offset' => $colOffset,
                    'col_span'   => $node['leaf_col_count'],
                    'row_span'   => $rowSpan,
                ];
                $colOffset += $node['leaf_col_count'];

            } elseif ($currentLevel < $targetLevel) {
                if (empty($node['children'])) {
                    // Leaf at a shallower level – its columns are already handled via rowSpan,
                    // just advance the offset so siblings after it land at the right position.
                    $colOffset += $node['leaf_col_count'];
                } else {
                    // Descend into children
                    $this->collectHeaderLevelCells(
                        $node['children'],
                        $targetLevel,
                        $currentLevel + 1,
                        $maxDepth,
                        $colOffset,
                        $cells
                    );
                }
            }
        }
    }

    // ── Export summary rows builder ───────────────────────────────

    /**
     * Pre-compute all summary rows so the export class has nothing to calculate.
     *
     * Each row is a keyed array of:
     *   label           string
     *   col_c           value for "Daily Sales" column (C)
     *   col_e           value for "Daily Spend" column (E)
     *   col_d           value for "Daily ROAS" column (D) – null unless a specific row needs it
     *   platform        [ "{pid}_{type}" => value, … ]  keyed by platform_id + type
     *   total_orders    int|null
     *   root_orders     [ root_id => int, … ]|null
     *   total_qty       int|null
     *   root_qty        [ root_id => int, … ]|null
     *   kids            int|null
     *   female          int|null
     *   male            int|null
     *   format_d        number format for col_d (null = default money)
     *   format_platform [ "{pid}_{type}" => format_code, … ]|null  (e.g. for % rows)
     */
    private function buildExportSummaryRows(
        array $totals,
        array $platformTotals,
        array $budgetMap,
        int   $dayCount,
        array $rootOrderTotals,
        array $rootQtyTotals,
        array $columns
    ): array {
        $rows = [];

        // ── Total Spend ────────────────────────────────────────
        $platRow = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            $platRow[$key] = $type === 'cost'
                ? ($platformTotals[$pid]['cost']  ?? 0)
                : ($platformTotals[$pid]['sales'] ?? 0);
        }
        $rows['total_spend'] = [
            'label'        => 'Total Spend',
            'col_c'        => $totals['sales'],
            'col_e'        => $totals['spent'],
            'col_d'        => null,
            'platform'     => $platRow,
            'total_orders' => $totals['orders'],
            'root_orders'  => $rootOrderTotals,
            'total_qty'    => $totals['qty'],
            'root_qty'     => $rootQtyTotals,
            'kids'         => $totals['kids'],
            'female'       => $totals['female'],
            'male'         => $totals['male'],
        ];

        // ── ROI (ROAS) ─────────────────────────────────────────
        $roiRow = [];
        $roiFmt = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            if ($type === 'cost') {
                $cost  = $platformTotals[$pid]['cost']  ?? 0;
                $sales = $platformTotals[$pid]['sales'] ?? 0;
                if ($cost > 0) {
                    $roiRow[$key] = $sales / $cost;
                    $roiFmt[$key] = '0.00%';
                }
            }
        }
        $overallRoi = $totals['spent'] > 0 ? ($totals['sales'] / $totals['spent']) : null;
        $rows['roi'] = [
            'label'            => 'ROI %',
            'col_c'            => null,
            'col_e'            => $overallRoi,
            'col_d'            => null,
            'col_e_format'     => '0.00%',
            'platform'         => $roiRow,
            'platform_formats' => $roiFmt,
            'total_orders'     => null,
            'root_orders'      => null,
            'total_qty'        => null,
            'root_qty'         => null,
            'kids'             => null,
            'female'           => null,
            'male'             => null,
        ];

        // ── Total Budget ───────────────────────────────────────
        $budgetPlatRow = [];
        $totalBudget   = array_sum($budgetMap);
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            if ($type === 'cost') {
                $b = $budgetMap[$pid] ?? 0;
                if ($b > 0) {
                    $budgetPlatRow[$key] = $b;
                }
            }
        }
        $rows['total_budget'] = [
            'label'        => 'Total Budget',
            'col_c'        => null,
            'col_e'        => $totalBudget,
            'col_d'        => null,
            'platform'     => $budgetPlatRow,
            'total_orders' => null,
            'root_orders'  => null,
            'total_qty'    => null,
            'root_qty'     => null,
            'kids'         => null,
            'female'       => null,
            'male'         => null,
        ];

        // ── Balance Budget ─────────────────────────────────────
        $balancePlatRow = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            if ($type === 'cost') {
                $b    = $budgetMap[$pid] ?? 0;
                $cost = $platformTotals[$pid]['cost'] ?? 0;
                if ($b > 0 || $cost > 0) {
                    $balancePlatRow[$key] = $b - $cost;
                }
            }
        }
        $rows['balance_budget'] = [
            'label'        => 'Balance Budget',
            'col_c'        => null,
            'col_e'        => $totalBudget - $totals['spent'],
            'col_d'        => null,
            'platform'     => $balancePlatRow,
            'total_orders' => null,
            'root_orders'  => null,
            'total_qty'    => null,
            'root_qty'     => null,
            'kids'         => null,
            'female'       => null,
            'male'         => null,
        ];

        // ── Average Daily Sales ────────────────────────────────
        $avgPlatRow = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            $avgPlatRow[$key] = $type === 'cost'
                ? ($platformTotals[$pid]['cost']  ?? 0) / $dayCount
                : ($platformTotals[$pid]['sales'] ?? 0) / $dayCount;
        }
        $rows['average_daily'] = [
            'label'        => 'Average Sales Daily',
            'col_c'        => $totals['sales'] / $dayCount,
            'col_e'        => $totals['spent'] / $dayCount,
            'col_d'        => null,
            'platform'     => $avgPlatRow,
            'total_orders' => $totals['orders'] / $dayCount,
            'root_orders'  => null,
            'total_qty'    => null,
            'root_qty'     => null,
            'kids'         => null,
            'female'       => null,
            'male'         => null,
        ];

        // ── Total Sale ─────────────────────────────────────────
        $totalSalePlatRow = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            if ($type === 'sales') {
                $totalSalePlatRow[$key] = $platformTotals[$pid]['sales'] ?? 0;
            }
        }
        $rows['total_sale'] = [
            'label'        => 'Total Sale',
            'col_c'        => $totals['sales'],
            'col_e'        => null,
            'col_d'        => null,
            'platform'     => $totalSalePlatRow,
            'total_orders' => null,
            'root_orders'  => null,
            'total_qty'    => null,
            'root_qty'     => null,
            'kids'         => null,
            'female'       => null,
            'male'         => null,
        ];

        // ── Forecasting (30 days) ──────────────────────────────
        $forecastPlatRow = [];
        foreach ($columns as $col) {
            $pid  = $col['platform_id'];
            $type = $col['type'];
            $key  = "{$pid}_{$type}";
            $avg  = $type === 'cost'
                ? ($platformTotals[$pid]['cost']  ?? 0) / $dayCount
                : ($platformTotals[$pid]['sales'] ?? 0) / $dayCount;
            $forecastPlatRow[$key] = $avg * 30;
        }
        $rows['forecasting'] = [
            'label'        => 'Forecasting (30 days)',
            'col_c'        => ($totals['sales'] / $dayCount) * 30,
            'col_e'        => ($totals['spent'] / $dayCount) * 30,
            'col_d'        => null,
            'platform'     => $forecastPlatRow,
            'total_orders' => ($totals['orders'] / $dayCount) * 30,
            'root_orders'  => null,
            'total_qty'    => null,
            'root_qty'     => null,
            'kids'         => null,
            'female'       => null,
            'male'         => null,
        ];

        return $rows;
    }

    // ── Weekly aggregate rows for the bottom breakdown section ────

    /**
     * Aggregate daily rows into per-week summaries.
     * Each item: week, label, spend, sales, orders, qty, kids, female, male,
     *            returns_pcs, root_orders[root_id], root_qty[root_id]
     */
    private function buildWeeklyAggregates(array $dailyRows, array $rootPlatforms): array
    {
        $rootIds = array_column($rootPlatforms, 'id');
        $weeks   = [];

        foreach ($dailyRows as $row) {
            $wk = $row['week'];
            if (!isset($weeks[$wk])) {
                $weeks[$wk] = [
                    'week'        => $wk,
                    'label'       => 'week ' . $wk,
                    'spend'       => 0,
                    'sales'       => 0,
                    'orders'      => 0,
                    'qty'         => 0,
                    'kids'        => 0,
                    'female'      => 0,
                    'male'        => 0,
                    'returns_pcs' => 0,
                    'root_orders' => array_fill_keys($rootIds, 0),
                    'root_qty'    => array_fill_keys($rootIds, 0),
                ];
            }
            $weeks[$wk]['spend']       += $row['total_spent'];
            $weeks[$wk]['sales']       += $row['total_sales'];
            $weeks[$wk]['orders']      += $row['total_orders'];
            $weeks[$wk]['qty']         += $row['total_qty'];
            $weeks[$wk]['kids']        += $row['kids'];
            $weeks[$wk]['female']      += $row['female'];
            $weeks[$wk]['male']        += $row['male'];
            $weeks[$wk]['returns_pcs'] += ($row['returns'] ?? 0);
            foreach ($rootPlatforms as $root) {
                $rid = $root['id'];
                $weeks[$wk]['root_orders'][$rid] += ($row['root_groups'][$rid]['orders'] ?? 0);
                $weeks[$wk]['root_qty'][$rid]    += ($row['root_groups'][$rid]['qty']    ?? 0);
            }
        }

        return array_values($weeks);
    }

    // ── Return-reason breakdown for the bottom section ─────────────

    /**
     * Query DailyReturn records and group by reason type and root platform.
     *
     * Returns:
     *   reasons       – [{ id, name, by_root[root_id=>count], kids, female, male }]
     *   totals_by_root  – [root_id => total_count]
     *   totals_kids / totals_female / totals_male  – int
     */
    private function buildReturnReasonData(
        string $dateFrom,
        string $dateTo,
        array  $rootPlatforms,
        array  $childrenByRoot
    ): array {
        $reasonTypes = ReturnReasonType::orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        $returnRows = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotNull('return_reason_type_id')
            ->get([
                'sale_platform_id', 'return_reason_type_id',
                'number_of_returns',
                'number_of_male_returns', 'number_of_female_returns', 'number_of_kids_returns',
            ]);

        // platform_id => root_id map
        $platformToRoot = [];
        foreach ($rootPlatforms as $root) {
            foreach (($childrenByRoot[$root['id']] ?? [$root['id']]) as $pid) {
                $platformToRoot[$pid] = $root['id'];
            }
        }

        $rootIds = array_column($rootPlatforms, 'id');

        // Build empty per-reason buckets
        $reasons      = [];
        $reasonIndex  = [];   // reason_type_id => index in $reasons
        foreach ($reasonTypes as $i => $rt) {
            $reasons[]          = [
                'id'      => $rt->id,
                'name'    => $rt->name,
                'by_root' => array_fill_keys($rootIds, 0),
                'kids'    => 0,
                'female'  => 0,
                'male'    => 0,
            ];
            $reasonIndex[$rt->id] = $i;
        }

        $totalsByRoot = array_fill_keys($rootIds, 0);
        $totalKids    = 0;
        $totalFemale  = 0;
        $totalMale    = 0;

        foreach ($returnRows as $ret) {
            $rid = $ret->return_reason_type_id;
            if (!isset($reasonIndex[$rid])) {
                continue;
            }
            $idx    = $reasonIndex[$rid];
            $rootId = $platformToRoot[$ret->sale_platform_id] ?? null;
            $count  = (int) $ret->number_of_returns;
            $male   = (int) ($ret->number_of_male_returns   ?? 0);
            $female = (int) ($ret->number_of_female_returns ?? 0);
            $kids   = (int) ($ret->number_of_kids_returns   ?? 0);

            if ($rootId !== null && isset($totalsByRoot[$rootId])) {
                $reasons[$idx]['by_root'][$rootId] += $count;
                $totalsByRoot[$rootId]             += $count;
            }
            $reasons[$idx]['male']   += $male;
            $reasons[$idx]['female'] += $female;
            $reasons[$idx]['kids']   += $kids;
            $totalMale   += $male;
            $totalFemale += $female;
            $totalKids   += $kids;
        }

        return [
            'reasons'        => $reasons,
            'totals_by_root' => $totalsByRoot,
            'totals_kids'    => $totalKids,
            'totals_female'  => $totalFemale,
            'totals_male'    => $totalMale,
        ];
    }

    // ── Private: build child-id map for root platforms ────────────

    private function buildChildMap($allPlatforms, $roots): array
    {
        $childrenByParent = $allPlatforms->groupBy('parent_id');
        $map = [];
        foreach ($roots as $root) {
            $ids   = [$root->id];
            $queue = [$root->id];
            while (!empty($queue)) {
                $pid      = array_shift($queue);
                $children = $childrenByParent->get($pid) ?? collect();
                foreach ($children as $child) {
                    $ids[]   = $child->id;
                    $queue[] = $child->id;
                }
            }
            $map[$root->id] = $ids;
        }
        return $map;
    }

    // ── Main entry point ──────────────────────────────────────────

    public function getDashboardData(array $filters): array
    {
        $range    = $this->resolveDateRange($filters);
        $dateFrom = $range['from']->toDateString();
        $dateTo   = $range['to']->toDateString();
        $months   = $range['months'];

        return [
            'range'              => $range,
            'summary'            => $this->getSummaryCards($dateFrom, $dateTo, $months),
            'monthlySales'       => $this->getMonthlySalesTrend($dateFrom, $dateTo),
            'platformSales'      => $this->getPlatformSalesBreakdown($dateFrom, $dateTo),
            'platformReturns'    => $this->getPlatformReturnsBreakdown($dateFrom, $dateTo),
            'returnReasons'      => $this->getReturnReasonsBreakdown($dateFrom, $dateTo),
            'genderBreakdown'    => $this->getGenderBreakdown($dateFrom, $dateTo),
            'budgetVsActual'     => $this->getBudgetVsActual($months),
            'platformCostVsSales'=> $this->getPlatformCostVsSales($dateFrom, $dateTo),
            'weeklyTrend'        => $this->getWeeklyTrend($dateFrom, $dateTo),
            'forecasting'        => $this->getForecasting($dateFrom, $dateTo),
            'platformBudgets'    => $this->getPlatformBudgets($months),
        ];
    }
}

