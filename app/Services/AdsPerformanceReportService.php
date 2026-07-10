<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class AdsPerformanceReportService
{
    private const VIEWS = [
        'performance' => 'Ad Performance',
        'summary'     => 'Monthly Summary',
        'platforms'   => 'Platform Engagement',
        'charts'      => 'Charts',
    ];

    private const PLAT_CHART_COLORS = [
        '#1A73E8', '#E37400', '#34A853', '#EA4335',
        '#9334E6', '#00897B', '#FF6D00', '#0097A7',
    ];

    public function __construct(
        private SaleTrackingService $trackingService,
        private AdsPerformanceExportColumns $exportColumns,
    ) {}

    public function buildPageData(Request $request): array
    {
        $view         = $this->normalizeView($request->input('view', 'performance'));
        $filterInput  = $this->extractFilterInput($request->all());
        $queryFilters = $this->normalizeQueryFilters($filterInput);
        $urlFilters   = $this->buildUrlFilters($request, $filterInput);
        $dataset          = $this->buildDataset($queryFilters);
        $exportPlatforms  = $this->buildExportPlatformList($dataset['platform_data'] ?? []);
        $exportSections   = $this->exportColumns->buildSections($exportPlatforms);

        return [
            'title'              => 'Ads Performance Report',
            'generated_at'       => now()->format('d M Y H:i'),
            'filter_input'       => $filterInput,
            'filters'            => $urlFilters,
            'view'               => $view,
            'view_tabs'          => $this->buildViewTabs($request, $urlFilters, $view, $dataset),
            'stats'              => $this->buildStats($dataset),
            'columns'            => $this->performanceColumns(),
            'performance_rows'   => $dataset['performance_rows'],
            'performance_totals' => $dataset['performance_totals'],
            'summary_rows'       => $dataset['summary_rows'],
            'summary_totals'     => $dataset['summary_totals'],
            'platform_sections'      => $dataset['platform_sections'],
            'platform_sections_all'  => $dataset['platform_sections_all'],
            'selected_engagement_slug' => $this->resolveEngagementSlug($filterInput, $dataset['platform_sections']),
            'chart_data'             => $dataset['chart_data'],
            'row_counts'         => [
                'performance' => count($dataset['performance_rows']),
                'summary'     => count($dataset['summary_rows']),
                'platforms'   => count($dataset['platform_sections']),
                'charts'      => $dataset['is_empty'] ? 0 : (4 + count($dataset['chart_data']['platforms'] ?? [])),
            ],
            'visible_count'      => match ($view) {
                'summary'   => count($dataset['summary_rows']),
                'platforms' => count($dataset['platform_sections']),
                'charts'    => $dataset['is_empty'] ? 0 : (4 + count($dataset['chart_data']['platforms'] ?? [])),
                default     => count($dataset['performance_rows']),
            },
            'is_empty'           => $dataset['is_empty'],
            'export_url'         => $this->buildExportUrl($queryFilters),
            'back_url'           => route('admin.ads-performance.index', $this->mapFiltersForIndex($filterInput)),
            'reset_filters_url'  => route('admin.ads-performance.report', ['view' => $view, 'period' => 'this_month']),
            'period_label'       => $this->buildPeriodLabel($filterInput),
            'active_filter_tags' => $this->buildActiveFilterTags($request, $filterInput, $urlFilters),
            'export_sections'         => $exportSections,
            'export_column_defaults'  => $this->exportColumns->defaultSelection($exportSections),
        ];
    }

    public function buildExportSections(array $input): array
    {
        $filterInput  = $this->extractFilterInput($input);
        $queryFilters = $this->normalizeQueryFilters($filterInput);
        $dataset      = $this->buildDataset($queryFilters);

        return $this->exportColumns->buildSections(
            $this->buildExportPlatformList($dataset['platform_data'] ?? []),
        );
    }

    public function buildExportPlatformList(array $platformData): array
    {
        $list = [];

        foreach ($platformData as $platName => $platEntry) {
            $platform = $platEntry['platform'] ?? null;
            $months   = $platEntry['months'] ?? [];
            $columns  = $this->platformMetricColumns($platform);

            if ($columns === [] || $months === []) {
                continue;
            }

            $list[] = [
                'name'         => $platName,
                'parent_name'  => $platform?->parent?->name,
                'platform_id'  => $platform?->id,
                'columns'      => $columns,
            ];
        }

        return $list;
    }

    private function extractFilterInput(array $input): array
    {
        $period = $input['period'] ?? $input['date_range'] ?? '';
        if ($period === '') {
            $period = 'this_month';
        }

        $fromYm = $input['from_year_month'] ?? '';
        $toYm   = $input['to_year_month'] ?? '';

        if ($period === 'custom' && $fromYm === '' && !empty($input['date_from'])) {
            try {
                $fromYm = Carbon::parse($input['date_from'])->format('Y-m');
            } catch (\Exception $e) {
                $fromYm = '';
            }
        }
        if ($period === 'custom' && $toYm === '' && !empty($input['date_to'])) {
            try {
                $toYm = Carbon::parse($input['date_to'])->format('Y-m');
            } catch (\Exception $e) {
                $toYm = '';
            }
        }

        if ($period === 'custom' && $fromYm === '') {
            $fromYm = now()->format('Y-m');
        }
        if ($period === 'custom' && $toYm === '') {
            $toYm = now()->format('Y-m');
        }

        return [
            'sale_platform_id'  => $input['sale_platform_id'] ?? '',
            'period'            => $period,
            'from_year_month'   => $fromYm,
            'to_year_month'     => $toYm,
        ];
    }

    private function normalizeQueryFilters(array $input): array
    {
        $filters = [];

        if (!empty($input['sale_platform_id'])) {
            $filters['sale_platform_id'] = (int) $input['sale_platform_id'];
        }

        $period = $input['period'] ?? $input['date_range'] ?? 'this_month';
        $range  = $period === 'last_1_year' ? 'last_year' : $period;

        $filters['date_range'] = $range;

        if ($range === 'custom') {
            if (!empty($input['from_year_month'])) {
                $filters['date_from'] = Carbon::parse($input['from_year_month'] . '-01')
                    ->startOfMonth()->toDateString();
            }
            if (!empty($input['to_year_month'])) {
                $filters['date_to'] = Carbon::parse($input['to_year_month'] . '-01')
                    ->endOfMonth()->toDateString();
            }
        }

        return $filters;
    }

    private function buildUrlFilters(Request $request, array $filterInput): array
    {
        $params = array_filter([
            'sale_platform_id' => $filterInput['sale_platform_id'] ?? '',
            'period'           => $filterInput['period'] ?? 'this_month',
            'from_year_month'  => $filterInput['from_year_month'] ?? '',
            'to_year_month'    => $filterInput['to_year_month'] ?? '',
        ], fn ($v) => $v !== '' && $v !== null);

        if (($filterInput['period'] ?? 'this_month') !== 'custom') {
            unset($params['from_year_month'], $params['to_year_month']);
        }

        if ($view = $request->input('view')) {
            $params['view'] = $view;
        }

        return $params;
    }

    private function mapFiltersForIndex(array $input): array
    {
        $params = [];
        if (!empty($input['sale_platform_id'])) {
            $params['sale_platform_id'] = $input['sale_platform_id'];
        }

        $period = $input['period'] ?? 'this_month';

        if ($period === 'this_month') {
            $params['date_range'] = 'custom';
            $params['date_from']  = now()->startOfMonth()->toDateString();
            $params['date_to']    = now()->endOfMonth()->toDateString();
        } elseif ($period === 'custom') {
            $params['date_range'] = 'custom';
            if (!empty($input['from_year_month'])) {
                $params['date_from'] = Carbon::parse($input['from_year_month'] . '-01')
                    ->startOfMonth()->toDateString();
            }
            if (!empty($input['to_year_month'])) {
                $params['date_to'] = Carbon::parse($input['to_year_month'] . '-01')
                    ->endOfMonth()->toDateString();
            }
        } else {
            $params['date_range'] = $period === 'last_1_year' ? 'last_year' : $period;
        }

        return $params;
    }

    private function buildPeriodLabel(array $input): string
    {
        $period = $input['period'] ?? 'this_month';

        return match ($period) {
            'this_month'    => 'This Month · ' . now()->format('F Y'),
            'last_month'    => 'Last Month · ' . now()->subMonth()->format('F Y'),
            'last_3_months' => 'Last 3 Months',
            'last_6_months' => 'Last 6 Months',
            'last_1_year', 'last_year' => 'Last 1 Year',
            'custom'        => $this->buildCustomPeriodLabel($input),
            default         => 'This Month · ' . now()->format('F Y'),
        };
    }

    private function buildCustomPeriodLabel(array $input): string
    {
        $from = $input['from_year_month'] ?? '';
        $to   = $input['to_year_month'] ?? '';

        if ($from && $to) {
            $fromLabel = Carbon::parse($from . '-01')->format('M Y');
            $toLabel   = Carbon::parse($to . '-01')->format('M Y');

            return $fromLabel === $toLabel ? $fromLabel : "{$fromLabel} → {$toLabel}";
        }

        return 'Custom Range';
    }

    private function buildDataset(array $filters): array
    {
        $records = $this->trackingService->getExportQuery($filters)->get();

        if ($records->isEmpty()) {
            return [
                'is_empty'           => true,
                'performance_rows'   => [],
                'performance_totals' => $this->emptyPerformanceTotals(),
                'summary_rows'       => [],
                'summary_totals'     => $this->emptySummaryTotals(),
                'platform_sections'     => [],
                'platform_sections_all' => null,
                'platform_data'         => [],
                'chart_data'            => $this->emptyChartData(),
            ];
        }

        $platformIds = $records->pluck('sale_platform_id')->filter()->unique()->values()->toArray();
        $monthKeys   = $records->map(fn ($r) => optional($r->month)->format('Y-m'))
            ->filter()->unique()->values()->toArray();

        $saleLookup   = $this->trackingService->getSaleDataForExport($platformIds, $monthKeys);
        $returnLookup = $this->trackingService->getReturnDataForExport($monthKeys);

        $prevMonthTotalRevenue = null;
        $sortedMonthKeys       = collect($monthKeys)->sort()->values()->toArray();
        if (!empty($sortedMonthKeys) && !empty($platformIds)) {
            $prevMk = Carbon::parse($sortedMonthKeys[0] . '-01')->subMonth()->format('Y-m');
            $prevMonthTotalRevenue = $this->trackingService->getPrevMonthRevenueForGrowth($platformIds, $prevMk);
        }

        $monthGroups  = [];
        $platformData = [];

        foreach ($records as $rec) {
            $mk         = optional($rec->month)->format('Y-m') ?? 'unknown';
            $platName   = $rec->salePlatform?->name ?? '—';
            $monthLabel = optional($rec->month)->format('M Y') ?? $mk;
            $platId     = $rec->sale_platform_id;

            $netCost = (float) ($saleLookup[$platId][$mk]['net_cost']   ?? 0);
            $revenue = (float) ($saleLookup[$platId][$mk]['revenue']    ?? 0);
            $orders  = (int)   ($saleLookup[$platId][$mk]['orders']     ?? 0);
            $prods   = (int)   ($saleLookup[$platId][$mk]['quantities'] ?? 0);
            $adsTax  = (float) ($rec->ads_tax_payments ?? 0);

            if (!isset($monthGroups[$mk])) {
                $monthGroups[$mk] = ['label' => $monthLabel, 'entries' => []];
            }
            $monthGroups[$mk]['entries'][] = [
                'rec'      => $rec,
                'platform' => $platName,
                'net_cost' => $netCost,
                'revenue'  => $revenue,
                'ads_tax'  => $adsTax,
                'orders'   => $orders,
                'products' => $prods,
            ];

            if (!isset($platformData[$platName])) {
                $platformData[$platName] = ['platform' => $rec->salePlatform, 'months' => []];
            }
            if (!isset($platformData[$platName]['months'][$mk])) {
                $platformData[$platName]['months'][$mk] = [
                    'label'            => $monthLabel,
                    'reach'            => 0,
                    'impressions'      => 0,
                    'clicks'           => 0,
                    'sessions'         => 0,
                    'engaged_sessions' => 0,
                    'users'            => 0,
                    'orders'           => 0,
                ];
            }
            $platformData[$platName]['months'][$mk]['reach']            += (int) ($rec->reach ?? 0);
            $platformData[$platName]['months'][$mk]['impressions']      += (int) ($rec->impressions ?? 0);
            $platformData[$platName]['months'][$mk]['clicks']           += (int) ($rec->clicks ?? 0);
            $platformData[$platName]['months'][$mk]['sessions']         += (int) ($rec->sessions ?? 0);
            $platformData[$platName]['months'][$mk]['engaged_sessions'] += (int) ($rec->engaged_sessions ?? 0);
            $platformData[$platName]['months'][$mk]['users']            += (int) ($rec->users ?? 0);
            $platformData[$platName]['months'][$mk]['orders']           += $orders;
        }

        $monthTotals = [];
        foreach ($monthGroups as $mk => $group) {
            $totalCost    = array_sum(array_column($group['entries'], 'ads_tax'));
            $totalRevenue = array_sum(array_column($group['entries'], 'revenue'));
            $totalReturn  = (float) ($returnLookup[$mk] ?? 0);
            $netRevenue   = $totalRevenue - $totalReturn;
            $roas         = $totalCost > 0 ? round(($totalRevenue / $totalCost) * 100, 4) : null;
            $roi          = $roas !== null ? (int) round($roas) : null;

            $monthTotals[$mk] = [
                'total_cost'    => $totalCost,
                'total_revenue' => $totalRevenue,
                'total_return'  => $totalReturn,
                'net_revenue'   => $netRevenue,
                'roas'          => $roas,
                'roi'           => $roi,
            ];
        }

        $performanceRows   = [];
        $summaryRows       = [];
        $sl                = 1;
        $monthIndex        = 0;
        $prevMonthRevenue  = $prevMonthTotalRevenue;
        $grand             = $this->emptyPerformanceAccumulator();

        foreach ($monthGroups as $mk => $group) {
            $mt           = $monthTotals[$mk];
            $monthRevenue = $mt['total_revenue'];
            $entryCount   = count($group['entries']);
            $monthBgClass = 'ap-month-bg-' . ($monthIndex % 2);

            if ($prevMonthRevenue !== null && $prevMonthRevenue > 0) {
                $salesGrowth = ($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue;
            } else {
                $salesGrowth = 0.0;
            }

            $grand['total_return'] += $mt['total_return'];

            foreach ($group['entries'] as $index => $entry) {
                $rec = $entry['rec'];
                $rowNum = $sl++;
                $performanceRows[] = [
                    'sl'                 => $rowNum,
                    'month_label'        => $group['label'],
                    'month_key'          => $mk,
                    'is_first_in_month'  => $index === 0,
                    'month_rowspan'      => $entryCount,
                    'platform'           => $entry['platform'],
                    'reach'              => $this->formatInt($rec->reach),
                    'impressions'        => $this->formatInt($rec->impressions),
                    'clicks'             => $this->formatInt($rec->clicks),
                    'sessions'           => $this->formatInt($rec->sessions),
                    'engaged_sessions'   => $this->formatInt($rec->engaged_sessions),
                    'users'              => $this->formatInt($rec->users),
                    'net_cost'           => $this->formatMoney($entry['net_cost']),
                    'ads_tax'            => $this->formatMoney($entry['ads_tax']),
                    'orders'             => $this->formatInt($entry['orders']),
                    'products'           => $this->formatInt($entry['products']),
                    'revenue'            => $this->formatMoney($entry['revenue']),
                    'total_cost'         => $index === 0 ? $this->formatMoney($mt['total_cost']) : null,
                    'sales_growth'       => $index === 0 ? $this->formatPercent($salesGrowth) : null,
                    'total_revenue'      => $index === 0 ? $this->formatMoney($mt['total_revenue']) : null,
                    'total_return'       => $index === 0 ? $this->formatMoney($mt['total_return']) : null,
                    'net_revenue'        => $index === 0 ? $this->formatMoney($mt['net_revenue']) : null,
                    'roi'                => $index === 0 ? $this->formatRoi($mt['roi']) : null,
                    'roas'               => $index === 0 ? $this->formatRoas($mt['roas']) : null,
                    'row_class'          => $monthBgClass,
                ];

                $this->accumulatePerformanceTotals($grand, $rec, $entry);
            }

            $summaryRows[] = [
                'label'       => $group['label'],
                'revenue'     => $this->formatMoney($mt['total_revenue']),
                'total_cost'  => $this->formatMoney($mt['total_cost']),
                'net_revenue' => $this->formatMoney($mt['net_revenue']),
                'orders'      => $this->formatInt(array_sum(array_column($group['entries'], 'orders'))),
                'clicks'      => $this->formatInt(array_sum(array_map(fn ($e) => (int) ($e['rec']->clicks ?? 0), $group['entries']))),
                'impressions' => $this->formatInt(array_sum(array_map(fn ($e) => (int) ($e['rec']->impressions ?? 0), $group['entries']))),
                'avg_roi'     => $this->formatPercent($mt['roi'] !== null ? $mt['roi'] / 100 : null),
                'avg_roas'    => $this->formatRoasDecimal($mt['roas']),
                'row_class'   => (count($summaryRows) % 2 === 1) ? 'ap-row-alt' : '',
            ];

            $prevMonthRevenue = $monthRevenue;
            $monthIndex++;
        }

        $performanceTotals = $this->buildPerformanceTotalsRow($grand);
        $summaryTotals     = $this->buildSummaryTotalsRow($summaryRows);
        $platformSections     = $this->buildPlatformSections($platformData);
        $platformSectionsAll  = $this->buildAggregatedPlatformSection($platformSections);
        $chartData            = $this->buildChartData($monthGroups, $monthTotals, $platformData);

        return [
            'is_empty'              => false,
            'performance_rows'      => $performanceRows,
            'performance_totals'    => $performanceTotals,
            'summary_rows'          => $summaryRows,
            'summary_totals'        => $summaryTotals,
            'platform_data'         => $platformData,
            'platform_sections'     => $platformSections,
            'platform_sections_all' => $platformSectionsAll,
            'chart_data'            => $chartData,
        ];
    }

    private function buildChartData(array $monthGroups, array $monthTotals, array $platformData): array
    {
        $overview = [
            'labels'      => [],
            'revenue'     => [],
            'total_cost'  => [],
            'net_revenue' => [],
            'orders'      => [],
            'avg_roi'     => [],
            'avg_roas'    => [],
        ];

        foreach ($monthGroups as $mk => $group) {
            $mt = $monthTotals[$mk];
            $overview['labels'][]      = $group['label'];
            $overview['revenue'][]     = round($mt['total_revenue'], 2);
            $overview['total_cost'][]  = round($mt['total_cost'], 2);
            $overview['net_revenue'][] = round($mt['net_revenue'], 2);
            $overview['orders'][]      = (int) array_sum(array_column($group['entries'], 'orders'));
            $overview['avg_roi'][]     = $mt['roi'] ?? 0;
            $overview['avg_roas'][]    = $mt['roas'] ?? 0;
        }

        $platforms = [];
        $colorIdx  = 0;

        foreach ($platformData as $platName => $platEntry) {
            $platform = $platEntry['platform'];
            $months   = $platEntry['months'];
            $columns  = $this->platformMetricColumns($platform);

            if (empty($columns)) {
                continue;
            }

            $hasEngagement = false;
            foreach ($months as $m) {
                foreach (array_keys($columns) as $key) {
                    if (($m[$key] ?? 0) > 0) {
                        $hasEngagement = true;
                        break 2;
                    }
                }
            }
            if (!$hasEngagement) {
                continue;
            }

            $labels   = [];
            $datasets = [];
            foreach (array_keys($columns) as $i => $key) {
                $datasets[] = [
                    'key'   => $key,
                    'label' => $columns[$key],
                    'data'  => [],
                    'color' => self::PLAT_CHART_COLORS[$i % count(self::PLAT_CHART_COLORS)],
                ];
            }

            foreach ($months as $m) {
                $labels[] = $m['label'];
                foreach ($datasets as $di => $ds) {
                    $datasets[$di]['data'][] = (int) ($m[$ds['key']] ?? 0);
                }
            }

            $platforms[] = [
                'slug'        => \Illuminate\Support\Str::slug($platName),
                'name'        => $platName,
                'platform_id' => $platform?->id,
                'labels'      => $labels,
                'datasets'    => $datasets,
                'month_count' => count($labels),
            ];
            $colorIdx++;
        }

        $engagementAll = $this->buildEngagementAll($platforms, $overview['labels']);

        return [
            'overview'       => $overview,
            'platforms'      => $platforms,
            'engagement_all' => $engagementAll,
            'engagement_options' => $this->buildEngagementOptions($engagementAll, $platforms),
        ];
    }

    private function buildEngagementAll(array $platforms, array $overviewLabels): ?array
    {
        if (empty($platforms)) {
            return null;
        }

        $labels = !empty($overviewLabels)
            ? $overviewLabels
            : collect($platforms)->flatMap(fn ($p) => $p['labels'])->unique()->values()->all();

        if (empty($labels)) {
            return null;
        }

        $metricMap = [];
        foreach ($platforms as $platform) {
            foreach ($platform['datasets'] as $ds) {
                $metricMap[$ds['key']] = $ds['label'];
            }
        }

        $datasets = [];
        $colorIdx = 0;
        foreach ($metricMap as $key => $label) {
            $datasets[] = [
                'key'   => $key,
                'label' => $label,
                'data'  => array_fill(0, count($labels), 0),
                'color' => self::PLAT_CHART_COLORS[$colorIdx % count(self::PLAT_CHART_COLORS)],
            ];
            $colorIdx++;
        }

        foreach ($platforms as $platform) {
            foreach ($platform['labels'] as $mi => $monthLabel) {
                $idx = array_search($monthLabel, $labels, true);
                if ($idx === false) {
                    continue;
                }
                foreach ($platform['datasets'] as $ds) {
                    foreach ($datasets as &$agg) {
                        if ($agg['key'] === $ds['key']) {
                            $agg['data'][$idx] += (int) ($ds['data'][$mi] ?? 0);
                        }
                    }
                    unset($agg);
                }
            }
        }

        return [
            'slug'        => 'all',
            'name'        => 'All Platforms',
            'labels'      => $labels,
            'datasets'    => $datasets,
            'month_count' => count($labels),
        ];
    }

    private function buildEngagementOptions(?array $engagementAll, array $platforms): array
    {
        $options = [];

        if ($engagementAll) {
            $options[] = ['value' => 'all', 'label' => 'All Platforms'];
        }

        foreach ($platforms as $platform) {
            $options[] = [
                'value' => $platform['slug'],
                'label' => $platform['name'],
            ];
        }

        return $options;
    }

    private function platformMetricColumns($platform): array
    {
        $columns = [];
        foreach ([
            'reach'            => ['Reach',            $platform ? (bool) $platform->track_reach : true],
            'impressions'      => ['Impressions',       $platform ? (bool) $platform->track_impressions : true],
            'clicks'           => ['Clicks',            $platform ? (bool) $platform->track_clicks : true],
            'sessions'         => ['Sessions',          $platform ? (bool) $platform->track_sessions : true],
            'engaged_sessions' => ['Engaged Sessions',  $platform ? (bool) $platform->track_engaged_sessions : true],
            'users'            => ['Users',             $platform ? (bool) $platform->track_users : true],
        ] as $key => [$label, $show]) {
            if ($show) {
                $columns[$key] = $label;
            }
        }

        return $columns;
    }

    private function emptyChartData(): array
    {
        return [
            'overview'  => [
                'labels' => [], 'revenue' => [], 'total_cost' => [], 'net_revenue' => [],
                'orders' => [], 'avg_roi' => [], 'avg_roas' => [],
            ],
            'platforms' => [],
            'engagement_all' => null,
            'engagement_options' => [],
        ];
    }

    private function buildPlatformSections(array $platformData): array
    {
        $sections = [];

        foreach ($platformData as $platName => $platEntry) {
            $platform = $platEntry['platform'];
            $months   = $platEntry['months'];

            $columns = $this->platformMetricColumns($platform);

            if (empty($columns)) {
                continue;
            }

            $hasEngagement = false;
            foreach ($months as $m) {
                foreach (array_keys($columns) as $key) {
                    if (($m[$key] ?? 0) > 0) {
                        $hasEngagement = true;
                        break 2;
                    }
                }
            }
            if (!$hasEngagement) {
                continue;
            }

            $rows   = [];
            $totals = array_fill_keys(array_keys($columns), 0);
            $si     = 0;

            foreach ($months as $m) {
                $cells = [];
                $raw   = [];
                foreach ($columns as $key => $label) {
                    $val = (int) ($m[$key] ?? 0);
                    $raw[$key]   = $val;
                    $cells[$key] = $this->formatInt($val);
                    $totals[$key] += $val;
                }
                $rows[] = [
                    'label'     => $m['label'],
                    'cells'     => $cells,
                    'raw'       => $raw,
                    'row_class' => ($si % 2 === 1) ? 'ap-row-alt' : '',
                ];
                $si++;
            }

            $totalCells = [];
            foreach ($columns as $key => $label) {
                $totalCells[$key] = $this->formatInt($totals[$key]);
            }

            $sections[] = [
                'slug'        => \Illuminate\Support\Str::slug($platName),
                'name'        => $platName,
                'platform_id' => $platform?->id,
                'columns'     => $columns,
                'rows'        => $rows,
                'totals'      => $totalCells,
                'totals_raw'  => $totals,
                'month_count' => count($rows),
            ];
        }

        return $sections;
    }

    private function buildAggregatedPlatformSection(array $sections): ?array
    {
        if (count($sections) < 2) {
            return null;
        }

        $columns = [];
        foreach ($sections as $section) {
            foreach ($section['columns'] as $key => $label) {
                $columns[$key] = $label;
            }
        }

        $monthData  = [];
        $monthOrder = [];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $monthLabel = $row['label'];
                if (!isset($monthData[$monthLabel])) {
                    $monthData[$monthLabel] = array_fill_keys(array_keys($columns), 0);
                    $monthOrder[] = $monthLabel;
                }
                foreach ($row['raw'] as $key => $val) {
                    if (array_key_exists($key, $monthData[$monthLabel])) {
                        $monthData[$monthLabel][$key] += $val;
                    }
                }
            }
        }

        $rows      = [];
        $totalsRaw = array_fill_keys(array_keys($columns), 0);
        $si        = 0;

        foreach ($monthOrder as $monthLabel) {
            $raw   = $monthData[$monthLabel];
            $cells = [];
            foreach ($columns as $key => $label) {
                $cells[$key] = $this->formatInt($raw[$key] ?? 0);
                $totalsRaw[$key] += $raw[$key] ?? 0;
            }
            $rows[] = [
                'label'     => $monthLabel,
                'cells'     => $cells,
                'raw'       => $raw,
                'row_class' => ($si % 2 === 1) ? 'ap-row-alt' : '',
            ];
            $si++;
        }

        $totalCells = [];
        foreach ($columns as $key => $label) {
            $totalCells[$key] = $this->formatInt($totalsRaw[$key]);
        }

        return [
            'slug'        => 'all',
            'name'        => 'All Platforms',
            'columns'     => $columns,
            'rows'        => $rows,
            'totals'      => $totalCells,
            'totals_raw'  => $totalsRaw,
            'month_count' => count($rows),
        ];
    }

    private function resolveEngagementSlug(array $filterInput, array $sections): string
    {
        if (empty($sections)) {
            return 'all';
        }

        $platformId = !empty($filterInput['sale_platform_id'])
            ? (int) $filterInput['sale_platform_id']
            : null;

        if ($platformId) {
            foreach ($sections as $section) {
                if (($section['platform_id'] ?? null) === $platformId) {
                    return $section['slug'];
                }
            }

            return count($sections) === 1 ? $sections[0]['slug'] : 'all';
        }

        return count($sections) === 1 ? $sections[0]['slug'] : 'all';
    }

    private function buildStats(array $dataset): array
    {
        if ($dataset['is_empty']) {
            return [
                ['label' => 'Total Revenue', 'value' => '£0.00', 'tone' => 'emerald'],
                ['label' => 'Total Cost', 'value' => '£0.00', 'tone' => 'amber'],
                ['label' => 'Net Revenue', 'value' => '£0.00', 'tone' => 'blue'],
                ['label' => 'Platforms', 'value' => '0', 'tone' => 'violet'],
            ];
        }

        $totals = $dataset['performance_totals'];

        return [
            ['label' => 'Total Revenue', 'value' => $totals['total_revenue'] ?? '£0.00', 'tone' => 'emerald'],
            ['label' => 'Total Cost', 'value' => $totals['total_cost'] ?? '£0.00', 'tone' => 'amber'],
            ['label' => 'Net Revenue', 'value' => $totals['net_revenue'] ?? '£0.00', 'tone' => 'blue'],
            ['label' => 'Platforms', 'value' => (string) count($dataset['platform_sections']), 'tone' => 'violet'],
        ];
    }

    private function buildViewTabs(Request $request, array $filters, string $activeView, array $dataset): array
    {
        $tabs = [];
        foreach (self::VIEWS as $key => $label) {
            $tabs[] = [
                'key'    => $key,
                'label'  => $label,
                'active' => $activeView === $key,
                'url'    => $this->buildTabUrl($request, $filters, $key),
            ];
        }

        return $tabs;
    }

    private function buildTabUrl(Request $request, array $filters, string $view): string
    {
        return route('admin.ads-performance.report', array_merge($filters, ['view' => $view]));
    }

    private function buildExportUrl(array $filters): string
    {
        return route('admin.ads-performance.export', $filters);
    }

    private function buildActiveFilterTags(Request $request, array $filterInput, array $urlFilters): array
    {
        $tags = [];
        $view = $request->input('view', 'performance');

        $without = function (array $except) use ($urlFilters, $view) {
            return route('admin.ads-performance.report', array_merge(
                collect($urlFilters)->except($except)->all(),
                ['view' => $view],
            ));
        };

        if (!empty($filterInput['sale_platform_id'])) {
            $platform = \App\Models\SalePlatform::find($filterInput['sale_platform_id']);
            $tags[] = [
                'label' => 'Platform',
                'value' => $platform?->name ?? 'Selected',
                'url'   => $without(['sale_platform_id']),
            ];
        }

        $period = $filterInput['period'] ?? 'this_month';

        if ($period === 'custom' && !empty($filterInput['from_year_month']) && !empty($filterInput['to_year_month'])) {
            $tags[] = [
                'label' => 'Period',
                'value' => $this->buildCustomPeriodLabel($filterInput),
                'url'   => $without(['period', 'from_year_month', 'to_year_month', 'date_range']),
            ];
        } elseif ($period !== '' && $period !== 'this_month') {
            $labels = [
                'last_month'    => 'Last Month',
                'last_3_months' => 'Last 3 Months',
                'last_6_months' => 'Last 6 Months',
                'last_1_year'   => 'Last 1 Year',
                'last_year'     => 'Last 1 Year',
            ];
            $tags[] = [
                'label' => 'Period',
                'value' => $labels[$period] ?? $period,
                'url'   => $without(['period', 'from_year_month', 'to_year_month', 'date_range']),
            ];
        }

        return $tags;
    }

    private function normalizeView(string $view): string
    {
        return array_key_exists($view, self::VIEWS) ? $view : 'performance';
    }

    private function performanceColumns(): array
    {
        return [
            ['key' => 'sl', 'label' => 'Sl. No', 'align' => 'left'],
            ['key' => 'month_label', 'label' => 'Month', 'align' => 'center'],
            ['key' => 'platform', 'label' => 'Platform', 'align' => 'left'],
            ['key' => 'reach', 'label' => 'Reach', 'align' => 'right'],
            ['key' => 'impressions', 'label' => 'Impressions', 'align' => 'right'],
            ['key' => 'clicks', 'label' => 'Clicks', 'align' => 'right'],
            ['key' => 'sessions', 'label' => 'Sessions', 'align' => 'right'],
            ['key' => 'engaged_sessions', 'label' => 'Engaged Sessions', 'align' => 'right'],
            ['key' => 'users', 'label' => 'Users', 'align' => 'right'],
            ['key' => 'net_cost', 'label' => 'Net Cost (£)', 'align' => 'right'],
            ['key' => 'ads_tax', 'label' => 'Ads Tax (£)', 'align' => 'right'],
            ['key' => 'total_cost', 'label' => 'Total Cost (£)', 'align' => 'right'],
            ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
            ['key' => 'products', 'label' => 'Products', 'align' => 'right'],
            ['key' => 'sales_growth', 'label' => 'Sales Growth %', 'align' => 'right'],
            ['key' => 'revenue', 'label' => 'Revenue (£)', 'align' => 'right'],
            ['key' => 'total_revenue', 'label' => 'Total Revenue (£)', 'align' => 'right'],
            ['key' => 'total_return', 'label' => 'Total Return (£)', 'align' => 'right'],
            ['key' => 'net_revenue', 'label' => 'Net Revenue (£)', 'align' => 'right'],
            ['key' => 'roi', 'label' => 'ROI', 'align' => 'right'],
            ['key' => 'roas', 'label' => 'ROAS', 'align' => 'right'],
        ];
    }

    private function emptyPerformanceTotals(): array
    {
        return [
            'label'         => 'TOTAL',
            'reach'         => '0',
            'impressions'   => '0',
            'clicks'        => '0',
            'sessions'      => '0',
            'engaged_sessions' => '0',
            'users'         => '0',
            'net_cost'      => '£0.00',
            'ads_tax'       => '£0.00',
            'total_cost'    => '£0.00',
            'orders'        => '0',
            'products'      => '0',
            'revenue'       => '£0.00',
            'total_revenue' => '£0.00',
            'total_return'  => '£0.00',
            'net_revenue'   => '£0.00',
            'roi'           => '—',
            'roas'          => '—',
            'row_class'     => 'ap-row-total',
        ];
    }

    private function emptySummaryTotals(): array
    {
        return [
            'label'       => 'TOTAL',
            'revenue'     => '£0.00',
            'total_cost'  => '£0.00',
            'net_revenue' => '£0.00',
            'orders'      => '0',
            'clicks'      => '0',
            'impressions' => '0',
            'avg_roi'     => '—',
            'avg_roas'    => '—',
            'row_class'   => 'ap-row-total',
        ];
    }

    private function emptyPerformanceAccumulator(): array
    {
        return [
            'reach' => 0, 'impressions' => 0, 'clicks' => 0, 'sessions' => 0,
            'engaged_sessions' => 0, 'users' => 0, 'net_cost' => 0.0, 'ads_tax' => 0.0,
            'orders' => 0, 'products' => 0, 'revenue' => 0.0, 'total_return' => 0.0,
        ];
    }

    private function accumulatePerformanceTotals(array &$grand, $rec, array $entry): void
    {
        $grand['reach']            += (int) ($rec->reach ?? 0);
        $grand['impressions']      += (int) ($rec->impressions ?? 0);
        $grand['clicks']           += (int) ($rec->clicks ?? 0);
        $grand['sessions']         += (int) ($rec->sessions ?? 0);
        $grand['engaged_sessions'] += (int) ($rec->engaged_sessions ?? 0);
        $grand['users']            += (int) ($rec->users ?? 0);
        $grand['net_cost']         += $entry['net_cost'];
        $grand['ads_tax']          += $entry['ads_tax'];
        $grand['orders']           += $entry['orders'];
        $grand['products']         += $entry['products'];
        $grand['revenue']          += $entry['revenue'];
    }

    private function buildPerformanceTotalsRow(array $grand): array
    {
        $totalCost    = $grand['ads_tax'];
        $totalRevenue = $grand['revenue'];
        $netRevenue   = $totalRevenue - $grand['total_return'];
        $roas         = $totalCost > 0 ? round(($totalRevenue / $totalCost) * 100, 4) : null;
        $roi          = $roas !== null ? (int) round($roas) : null;

        return [
            'label'            => 'TOTAL',
            'reach'            => $this->formatInt($grand['reach']),
            'impressions'      => $this->formatInt($grand['impressions']),
            'clicks'           => $this->formatInt($grand['clicks']),
            'sessions'         => $this->formatInt($grand['sessions']),
            'engaged_sessions' => $this->formatInt($grand['engaged_sessions']),
            'users'            => $this->formatInt($grand['users']),
            'net_cost'         => $this->formatMoney($grand['net_cost']),
            'ads_tax'          => $this->formatMoney($grand['ads_tax']),
            'total_cost'       => $this->formatMoney($totalCost),
            'orders'           => $this->formatInt($grand['orders']),
            'products'         => $this->formatInt($grand['products']),
            'revenue'          => $this->formatMoney($grand['revenue']),
            'total_revenue'    => $this->formatMoney($totalRevenue),
            'total_return'     => $this->formatMoney($grand['total_return']),
            'net_revenue'      => $this->formatMoney($netRevenue),
            'roi'              => $this->formatRoi($roi),
            'roas'             => $this->formatRoas($roas),
            'row_class'        => 'ap-row-total',
        ];
    }

    private function buildSummaryTotalsRow(array $summaryRows): array
    {
        $sumRev = $sumCost = $sumNet = $sumOrd = $sumClk = $sumImp = 0.0;

        foreach ($summaryRows as $row) {
            $sumRev  += $this->parseMoney($row['revenue']);
            $sumCost += $this->parseMoney($row['total_cost']);
            $sumNet  += $this->parseMoney($row['net_revenue']);
            $sumOrd  += $this->parseInt($row['orders']);
            $sumClk  += $this->parseInt($row['clicks']);
            $sumImp  += $this->parseInt($row['impressions']);
        }

        return [
            'label'       => 'TOTAL',
            'revenue'     => $this->formatMoney($sumRev),
            'total_cost'  => $this->formatMoney($sumCost),
            'net_revenue' => $this->formatMoney($sumNet),
            'orders'      => $this->formatInt((int) $sumOrd),
            'clicks'      => $this->formatInt((int) $sumClk),
            'impressions' => $this->formatInt((int) $sumImp),
            'avg_roi'     => '—',
            'avg_roas'    => '—',
            'row_class'   => 'ap-row-total',
        ];
    }

    private function formatMoney(?float $value): string
    {
        if ($value === null || abs($value) < 0.00001) {
            return '—';
        }

        return '£' . number_format($value, 2);
    }

    private function formatInt(?int $value): string
    {
        if ($value === null || $value === 0) {
            return '—';
        }

        return number_format($value);
    }

    private function formatPercent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value * 100, 2) . '%';
    }

    private function formatRoi(?int $value): string
    {
        if ($value === null) {
            return '—';
        }

        return (string) $value . '%';
    }

    private function formatRoas(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 2) . '%';
    }

    private function formatRoasDecimal(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 2);
    }

    private function parseMoney(string $value): float
    {
        if ($value === '—') {
            return 0.0;
        }

        return (float) str_replace(['£', ','], '', $value);
    }

    private function parseInt(string $value): int
    {
        if ($value === '—') {
            return 0;
        }

        return (int) str_replace(',', '', $value);
    }
}
