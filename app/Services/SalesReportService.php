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
        'daily'   => 'Daily Data',
        'returns' => 'Return Breakdown',
    ];

    private const PLATFORM_COLOR_COUNT = 5;

    public function __construct(
        private DashboardAnalyticsService $analytics,
        private SalesReportExportColumns $exportColumns,
    ) {}

    public function buildPageData(Request $request): array
    {
        $periodFilters = $this->normalizePeriodFilters($request->all());
        $reportFilters = $this->normalizeReportFilters($request->all());

        $range    = $this->analytics->resolveDateRange($periodFilters);
        $dateFrom = $range['from']->toDateString();
        $dateTo   = $range['to']->toDateString();
        $export   = $this->analytics->getDailyExportData($dateFrom, $dateTo, $range['months']);

        $rootPlatforms     = $this->enrichRootPlatformsWithColors($export['root_platforms']);
        $groupedColumns    = $this->enrichGroupedColumnEdges(
            $this->enrichGroupedColumnsWithColors(
                $this->buildGroupedPlatformColumns($export['column_data']['tree'] ?? []),
                $this->buildPlatformColorMap($export['column_data']['tree'] ?? []),
            ),
        );
        $tableHeaders = [
            'summary' => $this->buildSummaryTableHeaders($groupedColumns, $rootPlatforms),
            'daily'   => $this->buildDailyTableHeaders($groupedColumns, $rootPlatforms),
            'weekly'  => $this->buildWeeklyTableHeaders($rootPlatforms),
            'returns' => $this->buildReturnTableHeaders($rootPlatforms),
        ];
        $summaryRows       = $this->buildSummaryRows($export['summary_rows'], $groupedColumns);
        $dailyRowsAll      = $this->buildDailyRows($export['rows'], $groupedColumns, $rootPlatforms);
        $weeklyPayload     = $this->buildWeeklyRows($export['rows'], $export['weekly_rows'], $rootPlatforms, $dateFrom, $dateTo);
        $returnPayload     = $this->buildReturnRows($export['return_reason_data'], $rootPlatforms);

        $summaryRows = $this->filterSummaryRows($summaryRows, $reportFilters);
        $dailySansMonth = $this->filterDailyRows($dailyRowsAll, array_merge($reportFilters, ['month' => '']), $dateFrom, $dateTo);
        $dailyRows   = $this->applyDailyMonthFilter($dailySansMonth, $reportFilters['month']);
        $weeklyRows  = $this->filterWeeklyRows($weeklyPayload['rows'], $reportFilters);
        $returnRows  = $this->filterReturnRows($returnPayload['rows'], $reportFilters);

        $view = $reportFilters['view'];
        $dailyMonthTabs = $this->buildDailyMonthTabs($request, $periodFilters, $reportFilters, $range, $dailySansMonth);
        $exportSections = $this->exportColumns->buildSections($groupedColumns, $rootPlatforms);

        return [
            'filters'             => $periodFilters,
            'period_display'      => [
                'from_year_month' => $range['from']->format('Y-m'),
                'to_year_month'   => $range['to']->format('Y-m'),
            ],
            'report_filters'      => $reportFilters,
            'active_filter_count' => $this->countActiveReportFilters($reportFilters, $periodFilters),
            'filter_options'      => $this->buildFilterOptions($export, $range, $returnPayload['reason_types']),
            'range'               => $range,
            'view'                => $view,
            'view_tabs'           => $this->buildViewTabs($request, $periodFilters, $reportFilters),
            'stats'               => $this->buildPeriodStats($export['totals']),
            'root_platforms'      => $rootPlatforms,
            'grouped_columns'     => $groupedColumns,
            'table_headers'       => $tableHeaders,
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
            'reset_period_url'    => $this->buildResetPeriodUrl($request, $reportFilters, $view),
            'active_filter_tags'  => $this->buildActiveFilterTags($request, $periodFilters, $reportFilters, $rootPlatforms, $returnPayload['reason_types']),
            'daily_month_tabs'    => $dailyMonthTabs,
            'show_daily_month_tabs' => $view === 'daily' && count($dailyMonthTabs) > 0,
            'export_sections'         => $exportSections,
            'export_column_defaults'  => $this->exportColumns->defaultSelection($exportSections),
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
            'month'              => $input['month'] ?? '',
            'gender'             => $input['gender'] ?? '',
        ];
    }

    private function countActiveReportFilters(array $filters, array $periodFilters): int
    {
        $count = 0;
        foreach (['search', 'week', 'platform_id', 'return_reason_id', 'gender'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                $count++;
            }
        }
        if (($periodFilters['period'] ?? 'this_month') !== 'this_month') {
            $count++;
        }

        return $count;
    }

    // ── Section builders (mirror Excel export) ───────────────────

    private function buildSummaryRows(array $summaryRows, array $groupedColumns): array
    {
        $rows = [];
        foreach ($summaryRows as $key => $row) {
            $platformCells = $this->buildSummaryPlatformCells(
                $groupedColumns,
                $row['platform'] ?? [],
                $row['platform_formats'] ?? [],
            );

            $spendFmt = $row['col_e_format'] ?? null;

            $rows[] = array_merge([
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
            ], $this->negativeFieldClasses([
                'sales'  => $row['col_c'] ?? null,
                'spend'  => $row['col_e'] ?? null,
                'orders' => $row['total_orders'] ?? null,
                'qty'    => $row['total_qty'] ?? null,
                'kids'   => $row['kids'] ?? null,
                'female' => $row['female'] ?? null,
                'male'   => $row['male'] ?? null,
            ]));
        }

        return $rows;
    }

    private function buildDailyRows(array $rows, array $groupedColumns, array $rootPlatforms): array
    {
        $built = [];
        foreach ($rows as $row) {
            $rootOrders = [];
            $rootQty    = [];
            foreach ($row['root_groups'] ?? [] as $rid => $grp) {
                $rootOrders[$rid] = (int) ($grp['orders'] ?? 0);
                $rootQty[$rid]    = (int) ($grp['qty']    ?? 0);
            }

            $platformCells = $this->buildDailyPlatformCells($groupedColumns, $row['platform'] ?? []);

            $orderCount = count($rootPlatforms) + 1;
            $rootOrderCells = [];
            foreach ($rootPlatforms as $i => $root) {
                $rid = $root['id'];
                $rootOrderCells[] = [
                    'display'     => $this->formatNumber($rootOrders[$rid] ?? 0),
                    'edge_class'  => $this->columnEdgeClass($i, $orderCount),
                    'value_class' => $this->negativeValueClass($rootOrders[$rid] ?? 0),
                ];
            }

            $qtyCount = count($rootPlatforms) + 1;
            $rootQtyCells = [];
            foreach ($rootPlatforms as $i => $root) {
                $rid = $root['id'];
                $rootQtyCells[] = [
                    'display'     => $this->formatNumber($rootQty[$rid] ?? 0),
                    'edge_class'  => $this->columnEdgeClass($i, $qtyCount),
                    'value_class' => $this->negativeValueClass($rootQty[$rid] ?? 0),
                ];
            }

            $genderCells = [
                ['display' => $this->formatNumber($row['kids']),   'edge_class' => 'sr-col-start', 'value_class' => $this->negativeValueClass($row['kids'])],
                ['display' => $this->formatNumber($row['female']), 'edge_class' => '',             'value_class' => $this->negativeValueClass($row['female'])],
                ['display' => $this->formatNumber($row['male']),   'edge_class' => 'sr-col-end',   'value_class' => $this->negativeValueClass($row['male'])],
            ];

            $built[] = array_merge([
                'week'               => $row['week'],
                'date'               => $row['date'],
                'year_month'         => Carbon::parse($row['date'])->format('Y-m'),
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
                'gender_cells'       => $genderCells,
                'orders_total_edge'  => $this->columnEdgeClass($orderCount - 1, $orderCount),
                'qty_total_edge'     => $this->columnEdgeClass($qtyCount - 1, $qtyCount),
                'sales_display'      => $this->formatMoney($row['total_sales']),
                'spend_display'      => $this->formatMoney($row['total_spent']),
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
            ], $this->negativeFieldClasses([
                'sales'  => $row['total_sales'],
                'spend'  => $row['total_spent'],
                'orders' => $row['total_orders'],
                'qty'    => $row['total_qty'],
                'kids'   => $row['kids'],
                'female' => $row['female'],
                'male'   => $row['male'],
            ]));
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
            foreach ($rootPlatforms as $i => $root) {
                $rid = $root['id'];
                $pSales  = round((float) ($weeklySalesByRoot[$wk][$rid] ?? 0), 2);
                $pOrders = (float) ($wRow['root_orders'][$rid] ?? 0);
                $pQty    = (float) ($wRow['root_qty'][$rid]    ?? 0);
                $pRetAmt = round((float) ($weeklyReturnsByRoot[$wk][$rid]['amount']    ?? 0), 2);
                $pRetOrd = (float) ($weeklyReturnsByRoot[$wk][$rid]['order_qty'] ?? 0);
                $pRetQty = (float) ($weeklyReturnsByRoot[$wk][$rid]['item_qty']  ?? 0);
                $colorClass = $root['color_class'] ?? $this->platformColorClass($i);

                $platformMetrics[] = array_merge([
                    'id'                    => $rid,
                    'name'                  => $root['name'],
                    'color_class'           => $colorClass,
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
                ], $this->negativeFieldClasses([
                    'sales'          => $pSales,
                    'orders'         => $pOrders,
                    'qty'            => $pQty,
                    'return_amount'  => $pRetAmt,
                    'return_orders'  => $pRetOrd,
                    'return_qty'     => $pRetQty,
                ]));

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

            $built[] = array_merge([
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
            ], $this->negativeFieldClasses([
                'sales'         => $sales,
                'spend'         => $spend,
                'orders'        => $weekOrders,
                'qty'           => $weekItems,
                'returns_pcs'   => $retPcs,
                'returns_gbp'   => $retGbp,
                'return_pct_qty'=> $pctRetPcs,
                'return_pct_amt'=> $pctRetGbp,
            ]));
        }

        $weeklyPlatformTotal = [];
        foreach ($rootPlatforms as $i => $root) {
            $rid = $root['id'];
            $pt  = $platTotals[$rid];
            $weeklyPlatformTotal[] = array_merge([
                'color_class'           => $root['color_class'] ?? $this->platformColorClass($i),
                'id'                    => $rid,
                'sales_display'         => $this->formatMoney($pt['sales']),
                'orders_display'        => $this->formatNumber($pt['orders']),
                'qty_display'           => $this->formatNumber($pt['qty']),
                'return_amount_display' => $this->formatMoney($pt['return_amount']),
                'return_orders_display' => $this->formatNumber($pt['return_orders']),
                'return_qty_display'    => $this->formatNumber($pt['return_qty']),
            ], $this->negativeFieldClasses([
                'sales'         => $pt['sales'],
                'orders'        => $pt['orders'],
                'qty'           => $pt['qty'],
                'return_amount' => $pt['return_amount'],
                'return_orders' => $pt['return_orders'],
                'return_qty'    => $pt['return_qty'],
            ]));
        }

        $totalPctQty = $totalItems > 0 ? $totalRetPcs / $totalItems : 0;
        $totalPctAmt = $totalSales > 0 ? $totalRetGbp / $totalSales : 0;

        return [
            'rows'  => $built,
            'total' => array_merge([
                'label'                 => 'Total',
                'sales_display'         => $this->formatMoney($totalSales),
                'spend_display'         => $this->formatMoney($totalSpend),
                'orders_display'        => $this->formatNumber($totalOrders),
                'qty_display'           => $this->formatNumber($totalItems),
                'returns_pcs_display'   => $this->formatNumber($totalRetPcs),
                'returns_gbp_display'   => $this->formatMoney($totalRetGbp),
                'return_pct_qty_display'=> $this->formatPercent($totalPctQty),
                'return_pct_amt_display'=> $this->formatPercent($totalPctAmt),
                'platforms'             => $weeklyPlatformTotal,
            ], $this->negativeFieldClasses([
                'sales'          => $totalSales,
                'spend'          => $totalSpend,
                'orders'         => $totalOrders,
                'qty'            => $totalItems,
                'returns_pcs'    => $totalRetPcs,
                'returns_gbp'    => $totalRetGbp,
                'return_pct_qty' => $totalPctQty,
                'return_pct_amt' => $totalPctAmt,
            ])),
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
                    'color_class'   => trim(($root['color_class'] ?? '') . ' ' . $this->negativeValueClass($count)),
                    'count_edge'    => 'sr-col-start',
                    'pct_edge'      => 'sr-col-end',
                ];
            }

            $rows[] = array_merge([
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
            ], $this->negativeFieldClasses([
                'kids'   => $reason['kids'] ?? 0,
                'female' => $reason['female'] ?? 0,
                'male'   => $reason['male'] ?? 0,
                'total'  => $reasonTotal,
            ]));
        }

        $totalRootCells = [];
        foreach ($rootPlatforms as $root) {
            $rootTotal = (int) ($returnReasonData['totals_by_root'][$root['id']] ?? 0);
            $rootPct   = $grandTotal > 0 ? $rootTotal / $grandTotal : 0;
            $totalRootCells[] = [
                'count_display' => $this->formatNumber($rootTotal),
                'pct_display'   => $this->formatPercent($rootPct, 1),
                'color_class'   => trim(($root['color_class'] ?? '') . ' ' . $this->negativeValueClass($rootTotal)),
                'count_edge'    => 'sr-col-start',
                'pct_edge'      => 'sr-col-end',
            ];
        }

        return [
            'rows'         => $rows,
            'reason_types' => $reasonTypes->values()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->toArray(),
            'total'        => array_merge([
                'label'          => 'Total',
                'root_cells'     => $totalRootCells,
                'kids_display'   => $this->formatNumber($returnReasonData['totals_kids']   ?? 0),
                'female_display' => $this->formatNumber($returnReasonData['totals_female'] ?? 0),
                'male_display'   => $this->formatNumber($returnReasonData['totals_male']   ?? 0),
                'total_display'  => $this->formatNumber($grandTotal),
                'pct_display'    => $this->formatPercent($grandTotal > 0 ? 1 : 0, 1),
            ], $this->negativeFieldClasses([
                'kids'   => $returnReasonData['totals_kids']   ?? 0,
                'female' => $returnReasonData['totals_female'] ?? 0,
                'male'   => $returnReasonData['totals_male']   ?? 0,
                'total'  => $grandTotal,
            ])),
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
                if (!$has && !in_array($row['key'], ['total_sale', 'total_spend', 'average_daily', 'roi', 'forecasting', /* 'total_budget_requested', */ 'total_budget', 'balance_budget'], true)) {
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
            if ($row['date'] < $periodFrom || $row['date'] > $periodTo) {
                return false;
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
        if (($periodFilters['period'] ?? 'this_month') !== 'this_month') {
            $tags[] = [
                'label' => 'Period',
                'value' => $this->periodFilterLabel($periodFilters),
                'url'   => $this->buildUrl($request, array_merge($base, [
                    'period' => 'this_month',
                    'from_year_month' => '',
                    'to_year_month' => '',
                ])),
            ];
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
        if ($reportFilters['month'] !== '') {
            $label = Carbon::createFromFormat('Y-m', $reportFilters['month'])->format('F Y');
            $tags[] = ['label' => 'Month', 'value' => $label, 'url' => $this->buildUrl($request, array_merge($base, ['month' => '']))];
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
            'return_reason_id' => '', 'month' => '', 'gender' => '',
        ]));
    }

    private function buildResetPeriodUrl(Request $request, array $reportFilters, string $view): string
    {
        return $this->buildUrl($request, array_merge($reportFilters, [
            'view'             => $view,
            'period'           => 'this_month',
            'from_year_month'  => '',
            'to_year_month'    => '',
            'month'            => '',
        ]));
    }

    private function periodFilterLabel(array $periodFilters): string
    {
        return match ($periodFilters['period'] ?? 'this_month') {
            'last_month'     => 'Last Month',
            'last_3_months'  => 'Last 3 Months',
            'last_6_months'  => 'Last 6 Months',
            'last_1_year'    => 'Last 1 Year',
            'custom'         => Carbon::createFromFormat('Y-m', $periodFilters['from_year_month'])->format('M Y')
                . ' – ' . Carbon::createFromFormat('Y-m', $periodFilters['to_year_month'])->format('M Y'),
            default          => 'This Month',
        };
    }

    private function buildUrl(Request $request, array $params): string
    {
        $query = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $path  = $request->url();

        return $path . (empty($query) ? '' : '?' . http_build_query($query));
    }

    private function enrichRootPlatformsWithColors(array $rootPlatforms): array
    {
        return array_map(function (array $platform, int $index) {
            $platform['color_class'] = $this->platformColorClass($index);

            return $platform;
        }, $rootPlatforms, array_keys($rootPlatforms));
    }

    private function platformColorClass(int $index): string
    {
        return 'sr-plat-' . ($index % self::PLATFORM_COLOR_COUNT);
    }

    private function applyDailyMonthFilter(array $rows, string $month): array
    {
        if ($month === '') {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row) => ($row['year_month'] ?? '') === $month));
    }

    private function buildDailyMonthTabs(
        Request $request,
        array $periodFilters,
        array $reportFilters,
        array $range,
        array $dailyRows,
    ): array {
        if (count($range['months']) <= 1) {
            return [];
        }

        $tabs = [];

        $tabs[] = [
            'key'    => '',
            'label'  => 'All Months',
            'active' => $reportFilters['month'] === '',
            'count'  => count($dailyRows),
            'url'    => $this->buildUrl($request, array_merge($periodFilters, $reportFilters, ['month' => ''])),
        ];

        foreach ($range['months'] as $m) {
            $ym    = sprintf('%04d-%02d', $m['year'], $m['month']);
            $label = Carbon::createFromDate($m['year'], $m['month'], 1)->format('F Y');
            $count = count(array_filter($dailyRows, fn (array $row) => ($row['year_month'] ?? '') === $ym));

            $tabs[] = [
                'key'    => $ym,
                'label'  => $label,
                'active' => $reportFilters['month'] === $ym,
                'count'  => $count,
                'url'    => $this->buildUrl($request, array_merge($periodFilters, $reportFilters, ['month' => $ym])),
            ];
        }

        return $tabs;
    }

    private function buildPeriodStats(array $totals): array
    {
        $items = [
            ['label' => 'Total Sales', 'raw' => $totals['sales'] ?? 0,  'tone' => 'emerald', 'money' => true],
            ['label' => 'Total Spend', 'raw' => $totals['spent'] ?? 0,  'tone' => 'amber',  'money' => true],
            ['label' => 'Orders',      'raw' => $totals['orders'] ?? 0, 'tone' => 'blue',   'money' => false],
            ['label' => 'Order Qty',   'raw' => $totals['qty'] ?? 0,    'tone' => 'violet', 'money' => false],
        ];

        return array_map(fn (array $item) => [
            'label'       => $item['label'],
            'value'       => $item['money']
                ? $this->formatMoney($item['raw'])
                : $this->formatNumber($item['raw']),
            'value_class' => $this->negativeValueClass($item['raw']),
            'tone'        => $item['tone'],
        ], $items);
    }

    // ── Table layout (mirrors Excel grouped platform headers) ───────

    private function buildGroupedPlatformColumns(array $tree, int $depth = 0): array
    {
        $cols = [];

        foreach ($tree as $node) {
            if (!empty($node['children'])) {
                $leafIds = [];
                $this->collectLeafIdsFromTree($node['children'], $leafIds);

                if ($node['is_spent']) {
                    $cols[] = $this->groupedColumnMeta($node, 'summary', 'cost', $depth, $leafIds);
                }
                if ($node['is_sales']) {
                    $cols[] = $this->groupedColumnMeta($node, 'summary', 'sales', $depth, $leafIds);
                }

                $cols = array_merge($cols, $this->buildGroupedPlatformColumns($node['children'], $depth + 1));
            } else {
                if ($node['is_spent']) {
                    $cols[] = $this->groupedColumnMeta($node, 'leaf', 'cost', $depth, [$node['id']]);
                }
                if ($node['is_sales']) {
                    $cols[] = $this->groupedColumnMeta($node, 'leaf', 'sales', $depth, [$node['id']]);
                }
            }
        }

        return $cols;
    }

    private function groupedColumnMeta(array $node, string $kind, string $colType, int $level, array $leafIds): array
    {
        return [
            'kind'         => $kind,
            'platform_id'  => $node['id'],
            'col_type'     => $colType,
            'level'        => $level,
            'name'         => $node['name'],
            'leaf_ids'     => $leafIds,
            'key'          => "{$node['id']}_{$colType}",
            'type_label'   => $colType === 'cost' ? 'Spend' : 'Sales',
            'color_class'  => '',
        ];
    }

    private function collectLeafIdsFromTree(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            if (empty($node['children'])) {
                $ids[] = $node['id'];
            } else {
                $this->collectLeafIdsFromTree($node['children'], $ids);
            }
        }
    }

    private function buildPlatformColorMap(array $tree): array
    {
        $map = [];
        foreach ($tree as $index => $rootNode) {
            $this->assignPlatformColor($rootNode, $this->platformColorClass($index), $map);
        }

        return $map;
    }

    private function assignPlatformColor(array $node, string $colorClass, array &$map): void
    {
        $map[$node['id']] = $colorClass;
        foreach ($node['children'] ?? [] as $child) {
            $this->assignPlatformColor($child, $colorClass, $map);
        }
    }

    private function enrichGroupedColumnsWithColors(array $columns, array $colorMap): array
    {
        return array_map(function (array $col) use ($colorMap) {
            $col['color_class'] = $colorMap[$col['platform_id']] ?? '';

            return $col;
        }, $columns);
    }

    private function enrichGroupedColumnEdges(array $columns): array
    {
        $count = count($columns);
        $index = 0;

        while ($index < $count) {
            $platId = $columns[$index]['platform_id'];
            $cursor = $index;
            while ($cursor < $count && $columns[$cursor]['platform_id'] === $platId) {
                $cursor++;
            }

            for ($i = $index; $i < $cursor; $i++) {
                $columns[$i]['edge_class'] = $this->columnEdgeClass($i - $index, $cursor - $index);
            }

            $index = $cursor;
        }

        return $columns;
    }

    private function columnEdgeClass(int $index, int $total): string
    {
        $parts = [];
        if ($index === 0) {
            $parts[] = 'sr-col-start';
        }
        if ($index === $total - 1) {
            $parts[] = 'sr-col-end';
        }

        return implode(' ', $parts);
    }

    private function buildSummaryTableHeaders(array $groupedColumns, array $rootPlatforms): array
    {
        $row1 = [
            $this->fixedHeaderCell('Summary', 'left', 'sr-sticky-head-label sr-th-zone-label', false, true),
            $this->fixedHeaderCell('Daily Sales', 'right', 'sr-th-zone-core'),
            $this->fixedHeaderCell('Daily Spend', 'right', 'sr-th-zone-core', true),
        ];
        $row2 = [];

        $this->appendPlatformHeaderCells($row1, $row2, $groupedColumns);
        $metricLabels = ['Orders', 'Qty', 'Kids', 'Female', 'Male'];
        foreach ($metricLabels as $i => $label) {
            $row1[] = $this->headerCell(
                $label,
                'right',
                'sr-th-zone-metrics',
                2,
                1,
                $i === count($metricLabels) - 1,
            );
        }

        return [
            'rows'      => [$row1, $row2],
            'col_count' => $this->countHeaderColumns($row1),
        ];
    }

    private function buildDailyTableHeaders(array $groupedColumns, array $rootPlatforms): array
    {
        $row1 = [
            $this->fixedHeaderCell('Week', 'center', 'sr-sticky-head-week sr-th-zone-label', false, true),
            $this->fixedHeaderCell('Date', 'left', 'sr-sticky-head-date sr-th-zone-label'),
            $this->fixedHeaderCell('Daily Sales', 'right', 'sr-th-zone-core'),
            $this->fixedHeaderCell('Daily Spend', 'right', 'sr-th-zone-core', true),
        ];
        $row2 = [];

        $this->appendPlatformHeaderCells($row1, $row2, $groupedColumns);
        $this->appendRootMetricHeaders($row1, $row2, 'Order QTY', $rootPlatforms, true);
        $this->appendRootMetricHeaders($row1, $row2, 'Order Item QTY', $rootPlatforms, true);
        $this->appendGenderHeaders($row1, $row2);

        return [
            'rows'      => [$row1, $row2],
            'col_count' => $this->countHeaderColumns($row1),
        ];
    }

    private function buildWeeklyTableHeaders(array $rootPlatforms): array
    {
        $row1 = [];
        $row2 = [];

        $fixedLabels = ['Week', 'Sales (£)', 'Spend (£)', 'Order', 'Order Qty', 'Return Qty', 'Return Qty %', 'Return Amt (£)', 'Return Amt %'];
        foreach ($fixedLabels as $i => $label) {
            $row1[] = $this->headerCell(
                $label,
                'right',
                'sr-th-zone-core',
                2,
                1,
                $i === count($fixedLabels) - 1,
            );
        }

        $childLabels = ['Sales', 'Orders', 'Qty', 'Return (£)', 'Ret Orders', 'Ret Qty'];
        foreach ($rootPlatforms as $root) {
            $row1[] = $this->headerCell(
                $this->shortPlatformName($root['name']),
                'center',
                'sr-plat-hdr ' . ($root['color_class'] ?? ''),
                1,
                count($childLabels),
                true,
                true,
            );
            foreach ($childLabels as $j => $label) {
                $row2[] = $this->headerCell(
                    $label,
                    'right',
                    'sr-plat-subhdr ' . ($root['color_class'] ?? ''),
                    1,
                    1,
                    $j === count($childLabels) - 1,
                    $j === 0,
                );
            }
        }

        return [
            'rows'      => [$row1, $row2],
            'col_count' => count($fixedLabels) + (count($rootPlatforms) * count($childLabels)),
        ];
    }

    private function buildReturnTableHeaders(array $rootPlatforms): array
    {
        $row1 = [
            $this->headerCell('Reason', 'left', 'sr-sticky-head-label sr-th-zone-label', 2, 1, true, false),
        ];
        $row2 = [];

        foreach ($rootPlatforms as $root) {
            $row1[] = $this->headerCell(
                $this->shortPlatformName($root['name']),
                'center',
                'sr-plat-hdr ' . ($root['color_class'] ?? ''),
                1,
                2,
                true,
                true,
            );
            $row2[] = $this->headerCell('Qty', 'right', 'sr-plat-subhdr ' . ($root['color_class'] ?? ''), 1, 1, false, true);
            $row2[] = $this->headerCell('%', 'right', 'sr-plat-subhdr ' . ($root['color_class'] ?? ''), 1, 1, true, false);
        }

        $row1[] = $this->headerCell('Gender', 'center', 'sr-th-group', 1, 3, true, true);
        $row2[] = $this->headerCell('Kids', 'right', 'sr-th-sub', 1, 1, false, true);
        $row2[] = $this->headerCell('Female', 'right', 'sr-th-sub');
        $row2[] = $this->headerCell('Male', 'right', 'sr-th-sub', 1, 1, true, false);

        $row1[] = $this->headerCell('Total', 'center', 'sr-th-group', 1, 2, true, true);
        $row2[] = $this->headerCell('Qty', 'right', 'sr-th-sub', 1, 1, false, true);
        $row2[] = $this->headerCell('%', 'right', 'sr-th-sub', 1, 1, true, false);

        return [
            'rows'      => [$row1, $row2],
            'col_count' => 1 + (count($rootPlatforms) * 2) + 5,
        ];
    }

    private function appendPlatformHeaderCells(array &$row1, array &$row2, array $groupedColumns): void
    {
        if (count($groupedColumns) === 0) {
            return;
        }

        $count = count($groupedColumns);
        $index = 0;

        while ($index < $count) {
            $col    = $groupedColumns[$index];
            $platId = $col['platform_id'];
            $span   = 0;
            $cursor = $index;

            while ($cursor < $count && $groupedColumns[$cursor]['platform_id'] === $platId) {
                $span++;
                $cursor++;
            }

            $summaryClass = $col['kind'] === 'summary' ? ' sr-th-plat-summary' : '';
            $row1[] = $this->headerCell(
                $col['name'],
                'center',
                'sr-plat-hdr ' . ($col['color_class'] ?? '') . $summaryClass,
                1,
                $span,
                true,
                true,
            );

            for ($i = $index; $i < $cursor; $i++) {
                $sub = $groupedColumns[$i];
                $row2[] = $this->headerCell(
                    $sub['type_label'],
                    'center',
                    'sr-plat-subhdr ' . ($sub['color_class'] ?? ''),
                    1,
                    1,
                    $i === $cursor - 1,
                    $i === $index,
                );
            }

            $index = $cursor;
        }
    }

    private function appendRootMetricHeaders(array &$row1, array &$row2, string $groupLabel, array $rootPlatforms, bool $withTotal): void
    {
        $span = count($rootPlatforms) + ($withTotal ? 1 : 0);
        $row1[] = $this->headerCell($groupLabel, 'center', 'sr-th-group', 1, $span, true, true);

        foreach ($rootPlatforms as $i => $root) {
            $row2[] = $this->headerCell(
                $this->shortPlatformName($root['name']),
                'right',
                'sr-th-sub',
                1,
                1,
                false,
                $i === 0,
            );
        }

        if ($withTotal) {
            $row2[] = $this->headerCell('Total', 'right', 'sr-th-sub sr-th-total', 1, 1, true, false);
        } elseif (count($rootPlatforms) > 0) {
            $last = array_key_last($row2);
            $row2[$last]['class'] = trim(($row2[$last]['class'] ?? '') . ' sr-th-sec-after');
        }
    }

    private function appendGenderHeaders(array &$row1, array &$row2): void
    {
        $row1[] = $this->headerCell('Gender Order QTY', 'center', 'sr-th-group', 1, 3, true, true);
        $row2[] = $this->headerCell('Kids', 'right', 'sr-th-sub', 1, 1, false, true);
        $row2[] = $this->headerCell('Female', 'right', 'sr-th-sub');
        $row2[] = $this->headerCell('Male', 'right', 'sr-th-sub', 1, 1, true, false);
    }

    private function fixedHeaderCell(
        string $label,
        string $align,
        string $class,
        bool $sectionAfter = false,
        bool $colStart = false,
    ): array {
        return $this->headerCell($label, $align, $class, 2, 1, $sectionAfter, $colStart);
    }

    private function headerCell(
        string $label,
        string $align = 'left',
        string $class = '',
        int $rowspan = 1,
        int $colspan = 1,
        bool $sectionAfter = false,
        bool $colStart = false,
    ): array {
        if ($colStart) {
            $class = trim($class . ' sr-th-col-start');
        }
        if ($sectionAfter) {
            $class = trim($class . ' sr-th-sec-after');
        }

        return compact('label', 'align', 'class', 'rowspan', 'colspan');
    }

    private function countHeaderColumns(array $row1): int
    {
        return array_sum(array_map(fn (array $cell) => $cell['colspan'] ?? 1, $row1));
    }

    private function shortPlatformName(string $name): string
    {
        $name = trim(preg_replace('/\s*(platform|marketplace|store)\s*/i', '', $name) ?? $name);

        return mb_strlen($name) > 10 ? mb_substr($name, 0, 9) . '.' : $name;
    }

    private function buildSummaryPlatformCells(array $groupedColumns, array $platformRow, array $formats): array
    {
        $cells = [];
        foreach ($groupedColumns as $col) {
            $val = $this->resolveSummaryGroupedValue($col, $platformRow);
            $fmt = $this->resolveSummaryGroupedFormat($col, $formats);
            $cells[] = [
                'display'     => $this->formatValue($val, $fmt),
                'raw'         => $val,
                'color_class' => trim(($col['color_class'] ?? '') . ' sr-plat-cell ' . $this->negativeValueClass($val)),
                'edge_class'  => $col['edge_class'] ?? '',
                'kind'        => $col['kind'],
            ];
        }

        return $cells;
    }

    private function buildDailyPlatformCells(array $groupedColumns, array $platformData): array
    {
        $cells = [];
        foreach ($groupedColumns as $col) {
            $val = $this->resolveDailyGroupedValue($col, $platformData);
            $cells[] = [
                'display'     => $this->formatMoney($val),
                'color_class' => trim(($col['color_class'] ?? '') . ' sr-plat-cell ' . $this->negativeValueClass($val)),
                'edge_class'  => $col['edge_class'] ?? '',
                'kind'        => $col['kind'],
            ];
        }

        return $cells;
    }

    private function resolveSummaryGroupedValue(array $col, array $platformRow): mixed
    {
        if ($col['kind'] === 'leaf') {
            return $platformRow[$col['key']] ?? null;
        }

        $sum = 0.0;
        $has = false;
        foreach ($col['leaf_ids'] as $leafId) {
            $key = "{$leafId}_{$col['col_type']}";
            if (array_key_exists($key, $platformRow)) {
                $has = true;
                $sum += (float) $platformRow[$key];
            }
        }

        return $has ? $sum : null;
    }

    private function resolveSummaryGroupedFormat(array $col, array $formats): ?string
    {
        if ($col['kind'] === 'leaf') {
            return $formats[$col['key']] ?? null;
        }

        foreach ($col['leaf_ids'] as $leafId) {
            $key = "{$leafId}_{$col['col_type']}";
            if (!empty($formats[$key])) {
                return $formats[$key];
            }
        }

        return null;
    }

    private function resolveDailyGroupedValue(array $col, array $platformData): ?float
    {
        if ($col['kind'] === 'leaf') {
            $plat = $platformData[$col['platform_id']] ?? $platformData[(string) $col['platform_id']] ?? null;

            return $col['col_type'] === 'cost'
                ? ($plat['cost'] ?? null)
                : ($plat['sales'] ?? null);
        }

        $sum = 0.0;
        $has = false;
        foreach ($col['leaf_ids'] as $leafId) {
            $plat = $platformData[$leafId] ?? $platformData[(string) $leafId] ?? null;
            if ($plat !== null) {
                $has = true;
                $sum += $col['col_type'] === 'cost'
                    ? (float) ($plat['cost'] ?? 0)
                    : (float) ($plat['sales'] ?? 0);
            }
        }

        return $has ? $sum : null;
    }

    // ── Formatting & utilities ─────────────────────────────────────

    private function negativeValueClass(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (float) $value < 0 ? 'sr-negative' : '';
    }

    /** @param array<string, mixed> $values */
    private function negativeFieldClasses(array $values): array
    {
        $classes = [];
        foreach ($values as $field => $value) {
            $classes["{$field}_class"] = $this->negativeValueClass($value);
        }

        return $classes;
    }

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
            // 'total_budget_requested',
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
