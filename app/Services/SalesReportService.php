<?php

namespace App\Services;

use App\Models\DailyReturn;
use App\Models\ReturnReasonType;
use App\Models\SalePlatform;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalesReportService
{
    private const VIEWS = [
        'total'   => 'Totals',
        'weekly'  => 'Weekly',
        'daily'   => 'All Data',
        'returns' => 'Return Breakdown',
    ];

    public function __construct(
        private DashboardAnalyticsService $analytics,
    ) {}

    public function buildPageData(Request $request): array
    {
        $periodFilters = $this->normalizePeriodFilters($request->all());
        $reportFilters = $this->normalizeReportFilters($request->all());

        $range    = $this->analytics->resolveDateRange($periodFilters);
        $dateFrom = $range['from']->toDateString();
        $dateTo   = $range['to']->toDateString();
        $export   = $this->analytics->getDailyExportData($dateFrom, $dateTo, $range['months']);

        $rootPlatforms     = $export['root_platforms'];
        $platformColumns   = $this->buildPlatformColumnMeta($export['column_data']['columns'] ?? []);
        $summaryRows       = $this->buildSummaryRows($export['summary_rows'], $platformColumns);
        $dailyRows         = $this->buildDailyRows($export['rows'], $platformColumns, $rootPlatforms);
        $weeklyPayload     = $this->buildWeeklyRows($export['rows'], $export['weekly_rows'], $rootPlatforms, $dateFrom, $dateTo);
        $returnPayload     = $this->buildReturnRows($export['return_reason_data'], $rootPlatforms);

        $summaryRows = $this->filterSummaryRows($summaryRows, $reportFilters);
        $dailyRows   = $this->filterDailyRows($dailyRows, $reportFilters, $dateFrom, $dateTo);
        $weeklyRows  = $this->filterWeeklyRows($weeklyPayload['rows'], $reportFilters);
        $returnRows  = $this->filterReturnRows($returnPayload['rows'], $reportFilters);

        $view = $reportFilters['view'];

        return [
            'filters'             => $periodFilters,
            'report_filters'      => $reportFilters,
            'active_filter_count' => $this->countActiveReportFilters($reportFilters),
            'filter_options'      => $this->buildFilterOptions($export, $range, $returnPayload['reason_types']),
            'range'               => $range,
            'view'                => $view,
            'view_tabs'           => $this->buildViewTabs($request, $periodFilters, $reportFilters),
            'stats'               => $this->buildPeriodStats($export['totals']),
            'root_platforms'      => $rootPlatforms,
            'platform_columns'    => $platformColumns,
            'summary_rows'        => $summaryRows,
            'weekly_rows'         => $weeklyRows,
            'weekly_total'        => $weeklyPayload['total'],
            'daily_rows'          => $dailyRows,
            'return_rows'         => $returnRows,
            'return_total'        => $returnPayload['total'],
            'row_counts'          => [
                'total'   => count($summaryRows),
                'weekly'  => count($weeklyRows),
                'daily'   => count($dailyRows),
                'returns' => count($returnRows),
            ],
            'visible_count'       => match ($view) {
                'weekly'  => count($weeklyRows),
                'daily'   => count($dailyRows),
                'returns' => count($returnRows),
                default   => count($summaryRows),
            },
            'reset_report_url'    => $this->buildResetUrl($request, $periodFilters, $view),
            'active_filter_tags'  => $this->buildActiveFilterTags($request, $periodFilters, $reportFilters, $rootPlatforms, $returnPayload['reason_types']),
        ];
    }

    // ── Period & report filter normalization ───────────────────────

    private function normalizePeriodFilters(array $input): array
    {
        $period = $input['period'] ?? 'this_month';
        if (!in_array($period, ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'last_1_year', 'custom'], true)) {
            $period = 'this_month';
        }

        return [
            'period'           => $period,
            'from_year_month'  => $input['from_year_month'] ?? now()->format('Y-m'),
            'to_year_month'    => $input['to_year_month']   ?? now()->format('Y-m'),
        ];
    }

    private function normalizeReportFilters(array $input): array
    {
        $view = $input['view'] ?? 'total';
        if (!array_key_exists($view, self::VIEWS)) {
            $view = 'total';
        }

        return [
            'view'               => $view,
            'search'             => trim((string) ($input['search'] ?? '')),
            'week'               => $input['week'] ?? '',
            'platform_id'        => $input['platform_id'] ?? '',
            'return_reason_id'   => $input['return_reason_id'] ?? '',
            'date_from'          => $input['date_from'] ?? '',
            'date_to'            => $input['date_to'] ?? '',
            'gender'             => $input['gender'] ?? '',
        ];
    }

    private function countActiveReportFilters(array $filters): int
    {
        $count = 0;
        foreach (['search', 'week', 'platform_id', 'return_reason_id', 'date_from', 'date_to', 'gender'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                $count++;
            }
        }

        return $count;
    }

    // ── Section builders (mirror Excel export) ───────────────────

    private function buildPlatformColumnMeta(array $columns): array
    {
        return array_map(fn ($col) => [
            'key'         => "{$col['platform_id']}_{$col['type']}",
            'platform_id' => $col['platform_id'],
            'name'        => $col['name'],
            'type'        => $col['type'],
            'type_label'  => $col['type'] === 'cost' ? 'Spend' : 'Sales',
        ], $columns);
    }

    private function buildSummaryRows(array $summaryRows, array $platformColumns): array
    {
        $rows = [];
        foreach ($summaryRows as $key => $row) {
            $platformCells = [];
            foreach ($platformColumns as $col) {
                $val  = $row['platform'][$col['key']] ?? null;
                $fmt  = $row['platform_formats'][$col['key']] ?? null;
                $platformCells[] = [
                    'key'     => $col['key'],
                    'display' => $this->formatValue($val, $fmt),
                    'raw'     => $val,
                ];
            }

            $spendFmt = $row['col_e_format'] ?? null;

            $rows[] = [
                'key'            => $key,
                'label'          => $row['label'],
                'row_class'      => $this->summaryRowClass($key),
                'sales_display'  => $this->formatMoney($row['col_c'] ?? null),
                'spend_display'  => $this->formatValue($row['col_e'] ?? null, $spendFmt),
                'orders_display' => $this->formatNumber($row['total_orders'] ?? null),
                'qty_display'    => $this->formatNumber($row['total_qty'] ?? null),
                'kids_display'   => $this->formatNumber($row['kids'] ?? null),
                'female_display' => $this->formatNumber($row['female'] ?? null),
                'male_display'   => $this->formatNumber($row['male'] ?? null),
                'platform_cells' => $platformCells,
                'search_blob'    => $this->searchBlob($row['label'], $row['col_c'] ?? null, $row['col_e'] ?? null, ...array_column($platformCells, 'raw')),
            ];
        }

        return $rows;
    }

    private function buildDailyRows(array $rows, array $platformColumns, array $rootPlatforms): array
    {
        $built = [];
        foreach ($rows as $row) {
            $rootOrders = [];
            $rootQty    = [];
            foreach ($row['root_groups'] ?? [] as $rid => $grp) {
                $rootOrders[$rid] = (int) ($grp['orders'] ?? 0);
                $rootQty[$rid]    = (int) ($grp['qty']    ?? 0);
            }

            $platformCells = [];
            foreach ($platformColumns as $col) {
                $pid = $col['platform_id'];
                $plat = $row['platform'][$pid] ?? $row['platform'][(string) $pid] ?? null;
                $val  = $col['type'] === 'cost' ? ($plat['cost'] ?? null) : ($plat['sales'] ?? null);
                $platformCells[] = ['display' => $this->formatMoney($val)];
            }

            $rootOrderCells = [];
            $rootQtyCells   = [];
            foreach ($rootPlatforms as $root) {
                $rid = $root['id'];
                $rootOrderCells[] = ['display' => $this->formatNumber($rootOrders[$rid] ?? 0)];
                $rootQtyCells[]   = ['display' => $this->formatNumber($rootQty[$rid] ?? 0)];
            }

            $built[] = [
                'week'               => $row['week'],
                'date'               => $row['date'],
                'date_label'         => Carbon::parse($row['date'])->format('d-M-Y'),
                'sales'              => round((float) $row['total_sales'], 2),
                'spend'              => round((float) $row['total_spent'], 2),
                'roas'               => (float) $row['roas'],
                'orders'             => (int) $row['total_orders'],
                'qty'                => (int) $row['total_qty'],
                'kids'               => (int) $row['kids'],
                'female'             => (int) $row['female'],
                'male'               => (int) $row['male'],
                'platform'           => $row['platform'],
                'root_orders'        => $rootOrders,
                'root_qty'           => $rootQty,
                'platform_cells'     => $platformCells,
                'root_order_cells'   => $rootOrderCells,
                'root_qty_cells'     => $rootQtyCells,
                'sales_display'      => $this->formatMoney($row['total_sales']),
                'spend_display'      => $this->formatMoney($row['total_spent']),
                'roas_display'       => $this->formatPercent($row['roas']),
                'orders_display'     => $this->formatNumber($row['total_orders']),
                'qty_display'        => $this->formatNumber($row['total_qty']),
                'kids_display'       => $this->formatNumber($row['kids']),
                'female_display'     => $this->formatNumber($row['female']),
                'male_display'       => $this->formatNumber($row['male']),
                'search_blob'        => $this->searchBlob(
                    $row['date'],
                    Carbon::parse($row['date'])->format('d-M-Y'),
                    $row['week'],
                    $row['total_sales'],
                    $row['total_spent'],
                    $row['total_orders'],
                ),
            ];
        }

        return $built;
    }

    private function buildWeeklyRows(
        array $dailyRows,
        array $weeklyRows,
        array $rootPlatforms,
        string $dateFrom,
        string $dateTo,
    ): array {
        $returnsByDatePlatform = DailyReturn::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('DATE(date) as dt, sale_platform_id, SUM(return_amount) as amount, SUM(number_of_returns) as order_qty, SUM(number_of_return_quantities) as item_qty')
            ->groupByRaw('DATE(date), sale_platform_id')
            ->get()
            ->groupBy('dt')
            ->map(fn ($group) => $group->mapWithKeys(fn ($r) => [
                (int) $r->sale_platform_id => [
                    'amount'    => (float) ($r->amount    ?? 0),
                    'order_qty' => (float) ($r->order_qty ?? 0),
                    'item_qty'  => (float) ($r->item_qty  ?? 0),
                ],
            ])->toArray())
            ->toArray();

        $returnAmountByDate = [];
        foreach ($returnsByDatePlatform as $dt => $platforms) {
            $returnAmountByDate[$dt] = array_sum(array_column($platforms, 'amount'));
        }

        $allPlatforms   = SalePlatform::where('show_in_analytics', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id']);
        $childrenByRoot = $this->analyticsChildMap($allPlatforms);

        $leafSalesByRoot = [];
        foreach ($rootPlatforms as $root) {
            $leafSalesByRoot[$root['id']] = $childrenByRoot[$root['id']] ?? [$root['id']];
        }

        $weeklySalesByRoot   = [];
        $weeklyReturnsByRoot = [];

        foreach ($dailyRows as $row) {
            $wk = $row['week'];
            $dt = $row['date'];

            if (!isset($weeklySalesByRoot[$wk])) {
                foreach ($rootPlatforms as $root) {
                    $weeklySalesByRoot[$wk][$root['id']]   = 0.0;
                    $weeklyReturnsByRoot[$wk][$root['id']] = ['amount' => 0.0, 'order_qty' => 0.0, 'item_qty' => 0.0];
                }
            }

            foreach ($rootPlatforms as $root) {
                $rid     = $root['id'];
                $leafIds = $leafSalesByRoot[$rid] ?? [$rid];
                foreach ($leafIds as $leafId) {
                    $weeklySalesByRoot[$wk][$rid] += (float) ($row['platform'][$leafId]['sales'] ?? 0);
                    $ret = $returnsByDatePlatform[$dt][$leafId] ?? null;
                    if ($ret) {
                        $weeklyReturnsByRoot[$wk][$rid]['amount']    += $ret['amount'];
                        $weeklyReturnsByRoot[$wk][$rid]['order_qty'] += $ret['order_qty'];
                        $weeklyReturnsByRoot[$wk][$rid]['item_qty']  += $ret['item_qty'];
                    }
                }
            }
        }

        $built       = [];
        $totalSales  = 0.0;
        $totalSpend  = 0.0;
        $totalOrders = 0.0;
        $totalItems  = 0.0;
        $totalRetPcs = 0.0;
        $totalRetGbp = 0.0;
        $platTotals  = array_fill_keys(array_column($rootPlatforms, 'id'), [
            'sales' => 0.0, 'orders' => 0.0, 'qty' => 0.0,
            'return_amount' => 0.0, 'return_orders' => 0.0, 'return_qty' => 0.0,
        ]);

        foreach ($weeklyRows as $wRow) {
            $wk     = $wRow['week'];
            $sales  = (float) ($wRow['sales'] ?? 0);
            $spend  = (float) ($wRow['spend'] ?? 0);
            $retPcs = (float) ($wRow['returns_pcs'] ?? 0);
            $retGbp = 0.0;
            foreach ($dailyRows as $day) {
                if ($day['week'] === $wk) {
                    $retGbp += (float) ($returnAmountByDate[$day['date']] ?? 0);
                }
            }
            $weekOrders = (float) array_sum($wRow['root_orders'] ?? []);
            $weekItems  = (float) array_sum($wRow['root_qty']    ?? []);
            $pctRetPcs  = $weekItems > 0 ? $retPcs / $weekItems : 0;
            $pctRetGbp  = $sales     > 0 ? $retGbp / $sales     : 0;

            $platformMetrics = [];
            foreach ($rootPlatforms as $root) {
                $rid = $root['id'];
                $pSales  = round((float) ($weeklySalesByRoot[$wk][$rid] ?? 0), 2);
                $pOrders = (float) ($wRow['root_orders'][$rid] ?? 0);
                $pQty    = (float) ($wRow['root_qty'][$rid]    ?? 0);
                $pRetAmt = round((float) ($weeklyReturnsByRoot[$wk][$rid]['amount']    ?? 0), 2);
                $pRetOrd = (float) ($weeklyReturnsByRoot[$wk][$rid]['order_qty'] ?? 0);
                $pRetQty = (float) ($weeklyReturnsByRoot[$wk][$rid]['item_qty']  ?? 0);

                $platformMetrics[] = [
                    'id'                    => $rid,
                    'name'                  => $root['name'],
                    'sales'                 => $pSales,
                    'orders'                => $pOrders,
                    'qty'                   => $pQty,
                    'return_amount'         => $pRetAmt,
                    'return_orders'         => $pRetOrd,
                    'return_qty'            => $pRetQty,
                    'sales_display'         => $this->formatMoney($pSales),
                    'orders_display'        => $this->formatNumber($pOrders),
                    'qty_display'           => $this->formatNumber($pQty),
                    'return_amount_display' => $this->formatMoney($pRetAmt),
                    'return_orders_display' => $this->formatNumber($pRetOrd),
                    'return_qty_display'    => $this->formatNumber($pRetQty),
                ];

                $platTotals[$rid]['sales']          += $pSales;
                $platTotals[$rid]['orders']         += $pOrders;
                $platTotals[$rid]['qty']            += $pQty;
                $platTotals[$rid]['return_amount']  += $pRetAmt;
                $platTotals[$rid]['return_orders']  += $pRetOrd;
                $platTotals[$rid]['return_qty']     += $pRetQty;
            }

            $totalSales  += $sales;
            $totalSpend  += $spend;
            $totalOrders += $weekOrders;
            $totalItems  += $weekItems;
            $totalRetPcs += $retPcs;
            $totalRetGbp += $retGbp;

            $built[] = [
                'week'                  => $wk,
                'label'                 => $wRow['label'] ?? ('week ' . $wk),
                'sales'                 => round($sales, 2),
                'spend'                 => round($spend, 2),
                'orders'                => $weekOrders,
                'qty'                   => $weekItems,
                'returns_pcs'           => $retPcs,
                'returns_gbp'           => round($retGbp, 2),
                'return_pct_qty'        => $pctRetPcs,
                'return_pct_amt'        => $pctRetGbp,
                'platforms'             => $platformMetrics,
                'sales_display'         => $this->formatMoney($sales),
                'spend_display'         => $this->formatMoney($spend),
                'orders_display'        => $this->formatNumber($weekOrders),
                'qty_display'           => $this->formatNumber($weekItems),
                'returns_pcs_display'   => $this->formatNumber($retPcs),
                'returns_gbp_display'   => $this->formatMoney($retGbp),
                'return_pct_qty_display'=> $this->formatPercent($pctRetPcs),
                'return_pct_amt_display'=> $this->formatPercent($pctRetGbp),
                'search_blob'           => $this->searchBlob($wRow['label'] ?? ('week ' . $wk), $sales, $spend, $weekOrders, $retGbp),
            ];
        }

        $weeklyPlatformTotal = [];
        foreach ($rootPlatforms as $root) {
            $rid = $root['id'];
            $pt  = $platTotals[$rid];
            $weeklyPlatformTotal[] = [
                'id'                    => $rid,
                'sales_display'         => $this->formatMoney($pt['sales']),
                'orders_display'        => $this->formatNumber($pt['orders']),
                'qty_display'           => $this->formatNumber($pt['qty']),
                'return_amount_display' => $this->formatMoney($pt['return_amount']),
                'return_orders_display' => $this->formatNumber($pt['return_orders']),
                'return_qty_display'    => $this->formatNumber($pt['return_qty']),
            ];
        }

        return [
            'rows'  => $built,
            'total' => [
                'label'                 => 'Total',
                'sales_display'         => $this->formatMoney($totalSales),
                'spend_display'         => $this->formatMoney($totalSpend),
                'orders_display'        => $this->formatNumber($totalOrders),
                'qty_display'           => $this->formatNumber($totalItems),
                'returns_pcs_display'   => $this->formatNumber($totalRetPcs),
                'returns_gbp_display'   => $this->formatMoney($totalRetGbp),
                'return_pct_qty_display'=> $this->formatPercent($totalItems > 0 ? $totalRetPcs / $totalItems : 0),
                'return_pct_amt_display'=> $this->formatPercent($totalSales > 0 ? $totalRetGbp / $totalSales : 0),
                'platforms'             => $weeklyPlatformTotal,
            ],
        ];
    }

    private function buildReturnRows(array $returnReasonData, array $rootPlatforms): array
    {
        $grandTotal = array_sum($returnReasonData['totals_by_root'] ?? []);
        $reasonTypes = ReturnReasonType::orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->keyBy('id');

        $rows = [];
        foreach ($returnReasonData['reasons'] ?? [] as $reason) {
            $reasonTotal = array_sum($reason['by_root'] ?? []);
            $pctTotal    = $grandTotal > 0 ? $reasonTotal / $grandTotal : 0;

            $rootCells = [];
            foreach ($rootPlatforms as $root) {
                $count   = (int) ($reason['by_root'][$root['id']] ?? 0);
                $rootPct = $grandTotal > 0 ? $count / $grandTotal : 0;
                $rootCells[] = [
                    'id'            => $root['id'],
                    'count'         => $count,
                    'count_display' => $this->formatNumber($count),
                    'pct_display'   => $this->formatPercent($rootPct, 1),
                ];
            }

            $rows[] = [
                'id'             => $reason['id'],
                'name'           => $reason['name'],
                'root_cells'     => $rootCells,
                'kids'           => (int) ($reason['kids'] ?? 0),
                'female'         => (int) ($reason['female'] ?? 0),
                'male'           => (int) ($reason['male'] ?? 0),
                'kids_display'   => $this->formatNumber($reason['kids'] ?? 0),
                'female_display' => $this->formatNumber($reason['female'] ?? 0),
                'male_display'   => $this->formatNumber($reason['male'] ?? 0),
                'total'          => $reasonTotal,
                'total_display'  => $this->formatNumber($reasonTotal),
                'pct_display'    => $this->formatPercent($pctTotal, 1),
                'search_blob'    => $this->searchBlob($reason['name'], $reasonTotal, $reason['kids'] ?? 0),
            ];
        }

        $totalRootCells = [];
        foreach ($rootPlatforms as $root) {
            $rootTotal = (int) ($returnReasonData['totals_by_root'][$root['id']] ?? 0);
            $rootPct   = $grandTotal > 0 ? $rootTotal / $grandTotal : 0;
            $totalRootCells[] = [
                'count_display' => $this->formatNumber($rootTotal),
                'pct_display'   => $this->formatPercent($rootPct, 1),
            ];
        }

        return [
            'rows'         => $rows,
            'reason_types' => $reasonTypes->values()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->toArray(),
            'total'        => [
                'label'          => 'Total',
                'root_cells'     => $totalRootCells,
                'kids_display'   => $this->formatNumber($returnReasonData['totals_kids']   ?? 0),
                'female_display' => $this->formatNumber($returnReasonData['totals_female'] ?? 0),
                'male_display'   => $this->formatNumber($returnReasonData['totals_male']   ?? 0),
                'total_display'  => $this->formatNumber($grandTotal),
                'pct_display'    => $this->formatPercent($grandTotal > 0 ? 1 : 0, 1),
            ],
        ];
    }

    // ── Server-side filters ────────────────────────────────────────

    private function filterSummaryRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters) {
            if (!$this->matchesSearch($row['search_blob'], $filters['search'])) {
                return false;
            }
            if ($filters['platform_id'] !== '') {
                $pid = (int) $filters['platform_id'];
                $has = collect($row['platform_cells'])->contains(
                    fn ($c) => str_starts_with($c['key'], $pid . '_') && ($c['raw'] ?? 0) != 0
                );
                if (!$has && !in_array($row['key'], ['total_sale', 'total_spend', 'average_daily', 'roi', 'forecasting', 'total_budget', 'balance_budget'], true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function filterDailyRows(array $rows, array $filters, string $periodFrom, string $periodTo): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters, $periodFrom, $periodTo) {
            if ($filters['week'] !== '' && (string) $row['week'] !== (string) $filters['week']) {
                return false;
            }
            if ($filters['date_from'] !== '' && $row['date'] < $filters['date_from']) {
                return false;
            }
            if ($filters['date_to'] !== '' && $row['date'] > $filters['date_to']) {
                return false;
            }
            if ($filters['date_from'] === '' && $filters['date_to'] === '') {
                if ($row['date'] < $periodFrom || $row['date'] > $periodTo) {
                    return false;
                }
            }
            if ($filters['platform_id'] !== '') {
                $pid  = (int) $filters['platform_id'];
                $plat = $row['platform'][$pid] ?? $row['platform'][(string) $pid] ?? null;
                if (!$plat || ((float) ($plat['sales'] ?? 0) === 0.0 && (float) ($plat['cost'] ?? 0) === 0.0)) {
                    return false;
                }
            }
            if ($filters['gender'] === 'kids' && $row['kids'] <= 0) {
                return false;
            }
            if ($filters['gender'] === 'female' && $row['female'] <= 0) {
                return false;
            }
            if ($filters['gender'] === 'male' && $row['male'] <= 0) {
                return false;
            }
            if (!$this->matchesSearch($row['search_blob'], $filters['search'])) {
                return false;
            }

            return true;
        }));
    }

    private function filterWeeklyRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters) {
            if ($filters['week'] !== '' && (string) $row['week'] !== (string) $filters['week']) {
                return false;
            }
            if ($filters['platform_id'] !== '') {
                $pid = (int) $filters['platform_id'];
                $p   = collect($row['platforms'])->firstWhere('id', $pid);
                if (!$p || ($p['sales'] == 0 && $p['orders'] == 0 && $p['qty'] == 0)) {
                    return false;
                }
            }
            if (!$this->matchesSearch($row['search_blob'], $filters['search'])) {
                return false;
            }

            return true;
        }));
    }

    private function filterReturnRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters) {
            if ($filters['return_reason_id'] !== '' && (string) $row['id'] !== (string) $filters['return_reason_id']) {
                return false;
            }
            if ($filters['platform_id'] !== '') {
                $pid  = (int) $filters['platform_id'];
                $cell = collect($row['root_cells'])->firstWhere('id', $pid);
                if (!$cell || ($cell['count'] ?? 0) <= 0) {
                    return false;
                }
            }
            if ($filters['gender'] === 'kids' && ($row['kids'] ?? 0) <= 0) {
                return false;
            }
            if ($filters['gender'] === 'female' && ($row['female'] ?? 0) <= 0) {
                return false;
            }
            if ($filters['gender'] === 'male' && ($row['male'] ?? 0) <= 0) {
                return false;
            }
            if (!$this->matchesSearch($row['search_blob'], $filters['search'])) {
                return false;
            }

            return true;
        }));
    }

    // ── Filter UI helpers ─────────────────────────────────────────

    private function buildFilterOptions(array $export, array $range, array $reasonTypes): array
    {
        $weeks = collect($export['rows'] ?? [])->pluck('week')->unique()->sort()->values()
            ->map(fn ($w) => ['value' => $w, 'label' => 'Week ' . $w])->toArray();

        return [
            'weeks'          => $weeks,
            'platforms'      => $export['root_platforms'],
            'return_reasons' => $reasonTypes,
            'genders'        => [
                ['value' => '', 'label' => 'All Genders'],
                ['value' => 'kids', 'label' => 'Kids'],
                ['value' => 'female', 'label' => 'Female'],
                ['value' => 'male', 'label' => 'Male'],
            ],
            'views'          => collect(self::VIEWS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray(),
            'period_from'    => $range['from']->toDateString(),
            'period_to'      => $range['to']->toDateString(),
        ];
    }

    private function buildViewTabs(Request $request, array $periodFilters, array $reportFilters): array
    {
        $tabs = [];
        foreach (self::VIEWS as $key => $label) {
            $tabs[] = [
                'key'    => $key,
                'label'  => $label,
                'active' => $reportFilters['view'] === $key,
                'url'    => $this->buildUrl($request, array_merge($periodFilters, $reportFilters, ['view' => $key])),
            ];
        }

        return $tabs;
    }

    private function buildActiveFilterTags(
        Request $request,
        array $periodFilters,
        array $reportFilters,
        array $rootPlatforms,
        array $reasonTypes,
    ): array {
        $tags = [];
        $base = array_merge($periodFilters, $reportFilters);

        if ($reportFilters['search'] !== '') {
            $tags[] = ['label' => 'Search', 'value' => $reportFilters['search'], 'url' => $this->buildUrl($request, array_merge($base, ['search' => '']))];
        }
        if ($reportFilters['week'] !== '') {
            $tags[] = ['label' => 'Week', 'value' => 'Week ' . $reportFilters['week'], 'url' => $this->buildUrl($request, array_merge($base, ['week' => '']))];
        }
        if ($reportFilters['platform_id'] !== '') {
            $name = collect($rootPlatforms)->firstWhere('id', (int) $reportFilters['platform_id'])['name'] ?? $reportFilters['platform_id'];
            $tags[] = ['label' => 'Platform', 'value' => $name, 'url' => $this->buildUrl($request, array_merge($base, ['platform_id' => '']))];
        }
        if ($reportFilters['return_reason_id'] !== '') {
            $name = collect($reasonTypes)->firstWhere('id', (int) $reportFilters['return_reason_id'])['name'] ?? $reportFilters['return_reason_id'];
            $tags[] = ['label' => 'Reason', 'value' => $name, 'url' => $this->buildUrl($request, array_merge($base, ['return_reason_id' => '']))];
        }
        if ($reportFilters['date_from'] !== '') {
            $tags[] = ['label' => 'From', 'value' => $reportFilters['date_from'], 'url' => $this->buildUrl($request, array_merge($base, ['date_from' => '']))];
        }
        if ($reportFilters['date_to'] !== '') {
            $tags[] = ['label' => 'To', 'value' => $reportFilters['date_to'], 'url' => $this->buildUrl($request, array_merge($base, ['date_to' => '']))];
        }
        if ($reportFilters['gender'] !== '') {
            $tags[] = ['label' => 'Gender', 'value' => ucfirst($reportFilters['gender']), 'url' => $this->buildUrl($request, array_merge($base, ['gender' => '']))];
        }

        return $tags;
    }

    private function buildResetUrl(Request $request, array $periodFilters, string $view): string
    {
        return $this->buildUrl($request, array_merge($periodFilters, [
            'view' => $view,
            'search' => '', 'week' => '', 'platform_id' => '',
            'return_reason_id' => '', 'date_from' => '', 'date_to' => '', 'gender' => '',
        ]));
    }

    private function buildUrl(Request $request, array $params): string
    {
        $query = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $path  = $request->url();

        return $path . (empty($query) ? '' : '?' . http_build_query($query));
    }

    private function buildPeriodStats(array $totals): array
    {
        return [
            ['label' => 'Total Sales', 'value' => $this->formatMoney($totals['sales'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Total Spend', 'value' => $this->formatMoney($totals['spent'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Orders', 'value' => $this->formatNumber($totals['orders'] ?? 0), 'tone' => 'blue'],
            ['label' => 'Order Qty', 'value' => $this->formatNumber($totals['qty'] ?? 0), 'tone' => 'violet'],
        ];
    }

    // ── Formatting & utilities ─────────────────────────────────────

    private function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return '£' . number_format((float) $value, 2);
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 0);
    }

    private function formatPercent(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value * 100, $decimals) . '%';
    }

    private function formatValue(mixed $value, ?string $format): string
    {
        if ($format && str_contains($format, '%')) {
            return $this->formatPercent($value);
        }

        return $this->formatMoney($value);
    }

    private function summaryRowClass(string $key): string
    {
        return match ($key) {
            'average_daily'  => 'sr-row-average',
            'total_sale', 'total_spend' => 'sr-row-total',
            'total_budget', 'balance_budget' => 'sr-row-budget',
            'roi'            => 'sr-row-roi',
            'forecasting'    => 'sr-row-forecast',
            default          => '',
        };
    }

    private function matchesSearch(string $blob, string $query): bool
    {
        if ($query === '') {
            return true;
        }

        return str_contains(mb_strtolower($blob), mb_strtolower($query));
    }

    private function searchBlob(mixed ...$parts): string
    {
        return mb_strtolower(implode(' ', array_filter(array_map(
            fn ($p) => $p === null ? '' : (string) $p,
            $parts
        ))));
    }

    private function analyticsChildMap($allPlatforms): array
    {
        $childrenByParent = $allPlatforms->groupBy('parent_id');
        $map              = [];

        foreach ($allPlatforms->whereNull('parent_id') as $root) {
            $ids   = [$root->id];
            $queue = [$root->id];
            while (!empty($queue)) {
                $pid = array_shift($queue);
                foreach ($childrenByParent->get($pid) ?? [] as $child) {
                    $ids[]   = $child->id;
                    $queue[] = $child->id;
                }
            }
            $map[$root->id] = $ids;
        }

        return $map;
    }
}
