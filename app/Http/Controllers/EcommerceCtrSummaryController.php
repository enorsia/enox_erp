<?php

namespace App\Http\Controllers;

use App\Http\Clients\EnoxApiClient;
use App\Models\TrackingCtrCategory;
use App\Services\EcomActivityListService;
use App\Services\EcommerceCtrSummaryExportService;
use App\Services\EcommerceListingScopeCtrResolver;
use App\Services\EcommerceRankingListingContextService;
use App\Services\TrackingCtrSyncSiteSettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceCtrSummaryController extends Controller
{
    public const DEFAULT_PERIOD = '7d';

    /** @var list<string> */
    public const PERIODS = ['yesterday', '7d', '30d', 'custom'];

    /** @var array<string, string> Header preset buttons (period key => label). */
    public const PERIOD_PRESETS = [
        'yesterday' => 'Yesterday',
        '7d' => '7d',
        '30d' => '30d',
    ];

    private const PER_PAGE = 20;

    private const CATEGORY_FILTER_DEPARTMENT_CUSTOM_LINK = '__custom_link__';

    private const CATEGORY_FILTER_DEPARTMENT_STATIC = '__static__';

    /** @var list<string> Category filter group order (store department labels). */
    private const CATEGORY_FILTER_DEPARTMENT_ORDER = ['Women', 'Men', 'Girls', 'Boys'];

    /** @var list<string> Tie-break order after the column the user clicked */
    private const SORT_SEQUENCE = [
        'click',
        'impression',
        'ctr',
        'ctr_average',
    ];

    /** @var array<string, string> Sort key => SQL order expression */
    private const SORT_COLUMNS = [
        'ctr' => 'ctr',
        'ctr_average' => 'ctr_average',
        'click' => 'total_click',
        'impression' => 'total_impression',
    ];

    public function index(Request $request, TrackingCtrSyncSiteSettingsService $syncSiteSettings): View
    {
        Gate::authorize('ecommerce.ctr_summary.index');

        $context = $this->listContext($request, $syncSiteSettings);

        $summaries = (clone $context['query'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $productCodes = $summaries->getCollection()
            ->map(fn (object $row): string => trim((string) $row->product_code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $salesByProductCode = $this->fetchSalesByProductCode(
            $productCodes,
            $context['data']['dateFrom'],
            $context['data']['dateTo'],
        );

        $summaries->setCollection(
            $summaries->getCollection()->map(
                fn (object $row): array => $this->mapRowWithSales($row, $salesByProductCode),
            ),
        );

        return view('ecommerce.ctr_summary.index', [
            'summaries' => $summaries,
            'sortBy' => $context['sortKey'],
            'sortDir' => $context['sortDir'],
            'search' => $context['search'],
            'filterCatalog' => $context['filterCatalog'],
            'data' => $context['data'],
        ]);
    }

    public function rankingProducts(
        Request $request,
        EcommerceListingScopeCtrResolver $ctrResolver,
        EcommerceRankingListingContextService $listingContextService,
    ): View {
        Gate::authorize('ecommerce.ranking_products.index');

        $validated = Validator::make($request->all(), [
            'listing_scope' => 'sometimes|string|max:32',
            'listing_scope_id' => 'sometimes|integer|min:0',
            'context' => 'sometimes|string|max:64',
            'page' => 'sometimes|integer|min:1',
            'search' => 'sometimes|string|max:255',
        ])->validate();

        [$contextKey, $listingScope, $listingScopeId] = $this->resolveRankingContext($validated, $request);
        $search = trim((string) ($validated['search'] ?? $request->query('search', '')));
        $page = max(1, (int) ($validated['page'] ?? $request->integer('page', 1)));
        $paginationQuery = $this->rankingProductsPaginationQuery($request, $contextKey, $search);
        $listingContexts = $listingContextService->contexts();
        $labelsByContextKey = collect($listingContexts)->mapWithKeys(
            fn (array $ctx): array => [(string) $ctx['key'] => (string) $ctx['label']],
        )->all();

        $allowedScopes = [
            'global',
            'department',
            'category',
            'micro-category',
            'new-in',
            'special-department',
        ];

        $apiError = null;
        $sortingSalesDays = 30;
        $rows = collect();
        $paginator = new PaginationLengthAwarePaginator(
            [],
            0,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $paginationQuery],
        );

        if ($listingScope !== null && ! in_array($listingScope, $allowedScopes, true)) {
            $apiError = 'Invalid listing scope.';
        } else {
            [$apiPayload, $apiError] = $this->fetchRankingProductsPage(
                $contextKey !== '' ? $contextKey : null,
                $listingScope,
                $listingScopeId,
                $page,
                self::PER_PAGE,
                $search,
            );

            if ($apiError === null && is_array($apiPayload)) {
                $sortingSalesDays = max(1, (int) ($apiPayload['sorting_sales_days'] ?? 30));
                $apiRows = collect($apiPayload['data'] ?? []);
                $rows = $this->transformRankingApiRows($apiRows, $labelsByContextKey, $ctrResolver);

                $pagination = is_array($apiPayload['pagination'] ?? null) ? $apiPayload['pagination'] : [];
                $paginator = new PaginationLengthAwarePaginator(
                    $rows->all(),
                    (int) ($pagination['total'] ?? $rows->count()),
                    (int) ($pagination['per_page'] ?? self::PER_PAGE),
                    (int) ($pagination['current_page'] ?? $page),
                    ['path' => $request->url(), 'query' => $paginationQuery],
                );
            }
        }

        $listingScopeLabel = $listingContextService->labelForKey(
            $contextKey,
            $listingContexts,
            $listingScope,
            $listingScopeId,
        );

        return view('ecommerce.ranking_products.index', [
            'rows' => $paginator,
            'contextKey' => $contextKey,
            'listingContexts' => $listingContexts,
            'listingScope' => $listingScope,
            'listingScopeId' => $listingScopeId,
            'listingScopeLabel' => $listingScopeLabel,
            'search' => $search,
            'apiError' => $apiError,
            'contextsLoaded' => $listingContexts !== [],
            'resetUrl' => route('admin.ecommerce.ranking-products.index'),
            'filterFormAction' => route('admin.ecommerce.ranking-products.index'),
            'paginationQuery' => $paginationQuery,
            'sortingSalesDays' => $sortingSalesDays,
        ]);
    }

    public function export(Request $request, EcommerceCtrSummaryExportService $exportService, TrackingCtrSyncSiteSettingsService $syncSiteSettings): BinaryFileResponse
    {
        Gate::authorize('ecommerce.ctr_summary.export');

        $context = $this->listContext($request, $syncSiteSettings);

        $collection = (clone $context['query'])->get();

        $productCodes = $collection
            ->map(fn (object $row): string => trim((string) $row->product_code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $salesByProductCode = $this->fetchSalesByProductCode(
            $productCodes,
            $context['data']['dateFrom'],
            $context['data']['dateTo'],
        );

        $rows = $collection
            ->map(fn (object $row): array => $this->mapRowWithSales($row, $salesByProductCode))
            ->all();

        return $exportService->download($rows, $context['filterSummary']);
    }

    /**
     * @return array{
     *     query: Builder,
     *     sortKey: string,
     *     sortDir: 'asc'|'desc',
     *     search: string,
     *     period: string,
     *     filterSummary: string,
     *     filterCatalog: array{departments: array<string, string>, categories: list<array{value: string, label: string, department: string}>},
     *     data: array<string, mixed>
     * }
     */
    private function listContext(Request $request, TrackingCtrSyncSiteSettingsService $syncSiteSettings): array
    {
        [$sortKey, $sortDir] = $this->resolveSort($request);
        $search = trim((string) $request->query('search', ''));
        $catalogFilters = $this->catalogFiltersFromRequest($request);
        $period = $this->resolvePeriod($request);
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );
        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();

        $query = $this->summariesQuery($request, $sortKey, $sortDir, $fromDate, $toDate);

        $filterQuery = $this->ctrFilterQueryParams($sortKey, $sortDir, $search, $catalogFilters, $period, $range);
        $catalogQuery = array_diff_key($filterQuery, array_flip(['period', 'date_from', 'date_to']));
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $nextQuery = EcomActivityListService::shiftedPeriodQuery($range, 1);

        $resetUrl = route('admin.ecommerce.ctr-summary.index');
        $drawerResetUrl = route('admin.ecommerce.ctr-summary.index', array_filter([
            'period' => $period !== self::DEFAULT_PERIOD ? $period : null,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ], fn ($value) => $value !== null && $value !== []));

        $data = [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'periodPresets' => self::PERIOD_PRESETS,
            'dateFrom' => $fromDate,
            'dateTo' => $toDate,
            'rangeLabel' => TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
            'filterQuery' => $filterQuery,
            'filterFormAction' => route('admin.ecommerce.ctr-summary.index'),
            'resetUrl' => $resetUrl,
            'drawerResetUrl' => $drawerResetUrl,
            'prevUrl' => $prevQuery ? route('admin.ecommerce.ctr-summary.index', $prevQuery + $catalogQuery) : null,
            'nextUrl' => $nextQuery ? route('admin.ecommerce.ctr-summary.index', $nextQuery + $catalogQuery) : null,
            'exportUrl' => route('admin.ecommerce.ctr-summary.export', $filterQuery),
            'periodPresetUrl' => fn (string $periodKey) => route(
                'admin.ecommerce.ctr-summary.index',
                ['period' => $periodKey] + $catalogQuery,
            ),
        ];

        return [
            'query' => $query,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'search' => $search,
            'period' => $period,
            'filterCatalog' => $this->filterCatalog($fromDate, $toDate),
            'filterSummary' => $this->buildFilterSummary($search, $catalogFilters, $range),
            'data' => $data,
        ];
    }

    /**
     * @param  array{departments: list<string>, categories: list<string>}  $catalogFilters
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon, period: string}  $range
     * @return array<string, mixed>
     */
    private function ctrFilterQueryParams(
        string $sortKey,
        string $sortDir,
        string $search,
        array $catalogFilters,
        string $period,
        array $range,
    ): array {
        return array_filter([
            'sort' => $sortKey !== 'ctr' ? $sortKey : null,
            'dir' => $sortDir !== 'desc' ? $sortDir : null,
            'search' => $search !== '' ? $search : null,
            'department' => $catalogFilters['departments'] !== [] ? $catalogFilters['departments'] : null,
            'category' => $catalogFilters['categories'] !== [] ? $catalogFilters['categories'] : null,
            'period' => $period !== self::DEFAULT_PERIOD ? $period : null,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function resolvePeriod(Request $request): string
    {
        $period = $request->query('period');

        return is_string($period) && in_array($period, self::PERIODS, true)
            ? $period
            : self::DEFAULT_PERIOD;
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'yesterday' => 'Yesterday',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            'custom' => 'Custom range',
            default => 'Last 7 days',
        };
    }

    /**
     * @return array{departments: list<string>, categories: list<string>}
     */
    private function catalogFiltersFromRequest(Request $request): array
    {
        $departments = [];
        $departmentQuery = $request->query('department');
        foreach ((array) $departmentQuery as $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === '__all__' || ! ctype_digit($value)) {
                continue;
            }
            $departments[] = $value;
        }
        $departments = array_values(array_unique($departments));

        $categories = [];
        foreach ((array) $request->query('category', []) as $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === '__all__') {
                continue;
            }

            if (str_starts_with($value, 'custom:')) {
                $rest = substr($value, 7);
                if ($rest !== '' && preg_match('/^\d+(:\d+)?$/', $rest)) {
                    $categories[] = 'custom:'.$rest;
                }

                continue;
            }

            if (str_starts_with($value, 'static:')) {
                $rest = substr($value, 7);
                if ($rest !== '' && preg_match('/^\d+(:\d+)?$/', $rest)) {
                    $categories[] = 'static:'.$rest;
                }

                continue;
            }

            if (! str_contains($value, ':')) {
                continue;
            }

            [$departmentId, $categoryId] = explode(':', $value, 2);
            if (ctype_digit($departmentId) && ctype_digit($categoryId)) {
                $categories[] = $departmentId.':'.$categoryId;
            }
        }
        $categories = array_values(array_unique($categories));

        return [
            'departments' => $departments,
            'categories' => $categories,
        ];
    }

    /**
     * @return array{departments: array<string, string>, categories: list<array{value: string, label: string, department: string}>}
     */
    private function filterCatalog(string $fromDate, string $toDate): array
    {
        $rows = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->select('department_id', 'department_slug', 'category_id', 'category_slug', 'type')
            ->distinct()
            ->orderBy('department_slug')
            ->orderBy('category_slug')
            ->get();

        $departments = [];
        $categories = [];
        $seenCategoryValues = [];

        foreach ($rows as $row) {
            $type = trim((string) ($row->type ?? ''));
            $categoryId = (int) $row->category_id;

            if ($categoryId <= 0) {
                continue;
            }

            if ($type === TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK) {
                $categoryLabel = $this->formatLabel($row->category_slug, $categoryId);
                $departmentId = (int) ($row->department_id ?? 0);

                if ($departmentId > 0) {
                    $departmentKey = (string) $departmentId;
                    $departmentLabel = $this->formatLabel($row->department_slug, $departmentId, true);
                    if ($departmentLabel === '—') {
                        continue;
                    }

                    $value = 'custom:'.$departmentKey.':'.$categoryId;
                    if (isset($seenCategoryValues[$value])) {
                        continue;
                    }
                    $seenCategoryValues[$value] = true;
                    $departments[$departmentKey] = $departmentLabel;
                    $categories[] = [
                        'value' => $value,
                        'label' => $departmentLabel.' -> Custom link -> '.$categoryLabel,
                        'department' => $departmentKey,
                    ];

                    continue;
                }

                $value = 'custom:'.$categoryId;
                if (isset($seenCategoryValues[$value])) {
                    continue;
                }
                $seenCategoryValues[$value] = true;
                $categories[] = [
                    'value' => $value,
                    'label' => 'Custom link -> '.$categoryLabel,
                    'department' => self::CATEGORY_FILTER_DEPARTMENT_CUSTOM_LINK,
                ];

                continue;
            }

            if ($type === TrackingCtrCategory::TYPE_STATIC) {
                $categoryLabel = $this->formatLabel($row->category_slug, $categoryId);
                $departmentId = (int) ($row->department_id ?? 0);

                if ($departmentId > 0) {
                    $departmentKey = (string) $departmentId;
                    $departmentLabel = $this->formatLabel($row->department_slug, $departmentId, true);
                    if ($departmentLabel === '—') {
                        continue;
                    }

                    $value = 'static:'.$departmentKey.':'.$categoryId;
                    if (isset($seenCategoryValues[$value])) {
                        continue;
                    }
                    $seenCategoryValues[$value] = true;
                    $departments[$departmentKey] = $departmentLabel;
                    $categories[] = [
                        'value' => $value,
                        'label' => $departmentLabel.' -> Static -> '.$categoryLabel,
                        'department' => $departmentKey,
                    ];

                    continue;
                }

                $value = 'static:'.$categoryId;
                if (isset($seenCategoryValues[$value])) {
                    continue;
                }
                $seenCategoryValues[$value] = true;
                $categories[] = [
                    'value' => $value,
                    'label' => 'Static -> '.$categoryLabel,
                    'department' => self::CATEGORY_FILTER_DEPARTMENT_STATIC,
                ];

                continue;
            }

            $departmentId = (int) ($row->department_id ?? 0);
            if ($departmentId <= 0) {
                continue;
            }

            $departmentKey = (string) $departmentId;
            $departmentLabel = $this->formatLabel($row->department_slug, $departmentId, true);
            if ($departmentLabel === '—') {
                continue;
            }

            $categoryLabel = $this->formatLabel($row->category_slug, $categoryId);
            $value = $departmentKey.':'.$categoryId;
            if (isset($seenCategoryValues[$value])) {
                continue;
            }
            $seenCategoryValues[$value] = true;

            $departments[$departmentKey] = $departmentLabel;
            $categories[] = [
                'value' => $value,
                'label' => $departmentLabel.' -> '.$categoryLabel,
                'department' => $departmentKey,
            ];
        }

        natcasesort($departments);

        usort(
            $categories,
            fn (array $a, array $b): int => $this->compareCategoryFilterOptions($a, $b, $departments),
        );

        return [
            'departments' => $departments,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array{value: string, label: string, department: string}  $a
     * @param  array{value: string, label: string, department: string}  $b
     * @param  array<string, string>  $departments
     */
    private function compareCategoryFilterOptions(array $a, array $b, array $departments): int
    {
        return $this->categoryFilterOptionSortTuple($a, $departments)
            <=> $this->categoryFilterOptionSortTuple($b, $departments);
    }

    /**
     * @param  array{value: string, label: string, department: string}  $option
     * @param  array<string, string>  $departments
     * @return array{int, int, string}
     */
    private function categoryFilterOptionSortTuple(array $option, array $departments): array
    {
        $departmentKey = $option['department'];
        $label = $option['label'];

        if ($departmentKey === self::CATEGORY_FILTER_DEPARTMENT_CUSTOM_LINK) {
            return [100, 0, $label];
        }

        if ($departmentKey === self::CATEGORY_FILTER_DEPARTMENT_STATIC) {
            return [101, 0, $label];
        }

        $departmentLabel = $departments[$departmentKey] ?? (string) $departmentKey;

        return [$this->categoryFilterDepartmentGroupRank($departmentLabel), 0, $label];
    }

    private function categoryFilterDepartmentGroupRank(string $departmentLabel): int
    {
        foreach (self::CATEGORY_FILTER_DEPARTMENT_ORDER as $index => $name) {
            if (strcasecmp($departmentLabel, $name) === 0) {
                return $index;
            }
        }

        return 50;
    }

    /**
     * @param  array{departments: list<string>, categories: list<string>}  $catalogFilters
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon, period: string}  $range
     */
    private function buildFilterSummary(string $search, array $catalogFilters, array $range): string
    {
        $parts = [
            'Dates: '.TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
        ];

        if ($search !== '') {
            $parts[] = 'Search: '.$search;
        }

        if ($catalogFilters['departments'] !== []) {
            $catalog = $this->filterCatalog(
                $range['from']->toDateString(),
                $range['to']->toDateString(),
            );
            $labels = [];
            foreach ($catalogFilters['departments'] as $departmentId) {
                $labels[] = $catalog['departments'][$departmentId] ?? $departmentId;
            }
            $parts[] = 'Departments: '.implode(', ', $labels);
        }

        if ($catalogFilters['categories'] !== []) {
            $catalog ??= $this->filterCatalog(
                $range['from']->toDateString(),
                $range['to']->toDateString(),
            );
            $labelsByValue = collect($catalog['categories'])->pluck('label', 'value');
            $labels = [];
            foreach ($catalogFilters['categories'] as $value) {
                $labels[] = $labelsByValue[$value] ?? $value;
            }
            $parts[] = 'Categories: '.implode(', ', $labels);
        }

        return implode(' · ', $parts);
    }

    private function summariesQuery(
        Request $request,
        string $sortKey,
        string $sortDir,
        string $fromDate,
        string $toDate,
    ): Builder {
        $search = trim((string) $request->query('search', ''));

        $inner = $this->filteredDailySummariesQuery($request, $fromDate, $toDate, $search)
            ->select([
                'department_id',
                DB::raw('MAX(department_slug) as department_slug'),
                'category_id',
                DB::raw('MAX(category_slug) as category_slug'),
                'micro_category_id',
                DB::raw('MAX(micro_category_slug) as micro_category_slug'),
                DB::raw('MAX(type) as type'),
                'product_code',
                DB::raw('COALESCE(SUM(total_click), 0) as total_click'),
                DB::raw('COALESCE(SUM(total_impression), 0) as total_impression'),
                DB::raw(
                    'CASE WHEN SUM(total_impression) > 0 '
                    .'THEN ROUND(SUM(total_click) / SUM(total_impression), 6) ELSE 0 END as ctr'
                ),
                DB::raw('ROUND(COALESCE(MAX(ctr_average), 0), 6) as ctr_average'),
            ])
            ->groupBy('department_id', 'category_id', 'micro_category_id', 'product_code');

        $query = TrackerDatabase::query()
            ->fromSub($inner, 'tracking_ctr_summaries')
            ->select('tracking_ctr_summaries.*');

        $this->applySortOrder($query, $sortKey, $sortDir);

        return $query;
    }

    private function filteredDailySummariesQuery(
        Request $request,
        string $fromDate,
        string $toDate,
        string $search,
    ): Builder {
        $query = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate]);

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        $this->applyCatalogFilters($query, $request);

        return $query;
    }

    /**
     * @param  Builder  $query
     */
    private function applyCatalogFilters($query, Request $request): void
    {
        $catalogFilters = $this->catalogFiltersFromRequest($request);
        [$departmentCategoryPairs, $customOrphanCategoryIds, $customDepartmentCategoryPairs, $staticOrphanCategoryIds, $staticDepartmentCategoryPairs] = $this->parseCategoryFilterTokens($catalogFilters['categories']);

        $departments = $catalogFilters['departments'];
        $hasCategoryFilter = $departmentCategoryPairs !== []
            || $customOrphanCategoryIds !== []
            || $customDepartmentCategoryPairs !== []
            || $staticOrphanCategoryIds !== []
            || $staticDepartmentCategoryPairs !== [];

        if ($departments === [] && ! $hasCategoryFilter) {
            return;
        }

        if (! $hasCategoryFilter) {
            $query->whereIn('department_id', array_map(intval(...), $departments));

            return;
        }

        $query->where(function ($root) use ($departments, $departmentCategoryPairs, $customOrphanCategoryIds, $customDepartmentCategoryPairs, $staticOrphanCategoryIds, $staticDepartmentCategoryPairs) {
            $root->where(function ($inner) use ($departments, $departmentCategoryPairs, $customOrphanCategoryIds, $customDepartmentCategoryPairs, $staticOrphanCategoryIds, $staticDepartmentCategoryPairs) {
                $departmentIds = $departments !== [] ? array_map(intval(...), $departments) : [];

                foreach ($departmentCategoryPairs as [$departmentId, $categoryId]) {
                    $inner->orWhere(function ($pair) use ($departmentId, $categoryId, $departmentIds) {
                        $pair->where('department_id', $departmentId)
                            ->where('category_id', $categoryId);

                        if ($departmentIds !== []) {
                            $pair->whereIn('department_id', $departmentIds);
                        }
                    });
                }

                foreach ($customOrphanCategoryIds as $categoryId) {
                    $inner->orWhere(function ($custom) use ($categoryId) {
                        $custom->where('type', TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK)
                            ->where('category_id', $categoryId)
                            ->where('department_id', 0);
                    });
                }

                foreach ($customDepartmentCategoryPairs as [$departmentId, $categoryId]) {
                    $inner->orWhere(function ($custom) use ($departmentId, $categoryId, $departmentIds) {
                        $custom->where('type', TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK)
                            ->where('department_id', $departmentId)
                            ->where('category_id', $categoryId);

                        if ($departmentIds !== []) {
                            $custom->whereIn('department_id', $departmentIds);
                        }
                    });
                }

                foreach ($staticOrphanCategoryIds as $categoryId) {
                    $inner->orWhere(function ($static) use ($categoryId) {
                        $static->where('type', TrackingCtrCategory::TYPE_STATIC)
                            ->where('category_id', $categoryId)
                            ->where('department_id', 0);
                    });
                }

                foreach ($staticDepartmentCategoryPairs as [$departmentId, $categoryId]) {
                    $inner->orWhere(function ($static) use ($departmentId, $categoryId, $departmentIds) {
                        $static->where('type', TrackingCtrCategory::TYPE_STATIC)
                            ->where('department_id', $departmentId)
                            ->where('category_id', $categoryId);

                        if ($departmentIds !== []) {
                            $static->whereIn('department_id', $departmentIds);
                        }
                    });
                }
            });
        });
    }

    /**
     * @param  list<string>  $tokens
     * @return array{0: list<array{0: int, 1: int}>, 1: list<int>, 2: list<array{0: int, 1: int}>, 3: list<int>, 4: list<array{0: int, 1: int}>}
     */
    private function parseCategoryFilterTokens(array $tokens): array
    {
        $pairs = [];
        $customOrphanCategoryIds = [];
        $customDepartmentCategoryPairs = [];
        $staticOrphanCategoryIds = [];
        $staticDepartmentCategoryPairs = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, 'custom:')) {
                $rest = substr($token, 7);
                if (str_contains($rest, ':')) {
                    [$departmentId, $categoryId] = explode(':', $rest, 2);
                    if (ctype_digit($departmentId) && ctype_digit($categoryId)) {
                        $customDepartmentCategoryPairs[] = [(int) $departmentId, (int) $categoryId];
                    }

                    continue;
                }

                $categoryId = (int) $rest;
                if ($categoryId > 0) {
                    $customOrphanCategoryIds[] = $categoryId;
                }

                continue;
            }

            if (str_starts_with($token, 'static:')) {
                $rest = substr($token, 7);
                if (str_contains($rest, ':')) {
                    [$departmentId, $categoryId] = explode(':', $rest, 2);
                    if (ctype_digit($departmentId) && ctype_digit($categoryId)) {
                        $staticDepartmentCategoryPairs[] = [(int) $departmentId, (int) $categoryId];
                    }

                    continue;
                }

                $categoryId = (int) $rest;
                if ($categoryId > 0) {
                    $staticOrphanCategoryIds[] = $categoryId;
                }

                continue;
            }

            if (! str_contains($token, ':')) {
                continue;
            }

            [$departmentId, $categoryId] = explode(':', $token, 2);
            if (ctype_digit($departmentId) && ctype_digit($categoryId)) {
                $pairs[] = [(int) $departmentId, (int) $categoryId];
            }
        }

        return [
            $pairs,
            array_values(array_unique($customOrphanCategoryIds)),
            $this->uniqueDepartmentCategoryPairs($customDepartmentCategoryPairs),
            array_values(array_unique($staticOrphanCategoryIds)),
            $this->uniqueDepartmentCategoryPairs($staticDepartmentCategoryPairs),
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     * @return list<array{0: int, 1: int}>
     */
    private function uniqueDepartmentCategoryPairs(array $pairs): array
    {
        $seen = [];
        $unique = [];

        foreach ($pairs as [$departmentId, $categoryId]) {
            $key = $departmentId.':'.$categoryId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = [$departmentId, $categoryId];
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(object $row): array
    {
        $code = trim((string) $row->product_code);

        return [
            'department' => $this->formatDepartmentColumn($row),
            'category_display' => $this->formatCategoryColumn($row),
            'category' => $this->formatLabel($row->category_slug, $row->category_id),
            'micro_category' => $this->formatMicroCategoryColumn($row),
            'product_code' => $code,
            'ctr' => (float) $row->ctr,
            'ctr_average' => (float) $row->ctr_average,
            'click' => (int) $row->total_click,
            'impression' => (int) $row->total_impression,
        ];
    }

    /**
     * @param  array<string, array{total_sale: float, total_qty: int}>  $salesByProductCode
     * @return array<string, mixed>
     */
    private function mapRowWithSales(object $row, array $salesByProductCode): array
    {
        $mapped = $this->mapRow($row);
        $sales = $salesByProductCode[$mapped['product_code']] ?? null;
        $mapped['total_sold'] = round((float) ($sales['total_sale'] ?? 0), 2);
        $mapped['total_qty'] = (int) ($sales['total_qty'] ?? 0);

        return $mapped;
    }

    /**
     * @param  list<string>  $productCodes
     * @return array<string, array{total_sale: float, total_qty: int}>
     */
    private function fetchSalesByProductCode(
        array $productCodes,
        string $dateFrom,
        string $dateTo,
    ): array {
        $salesByProductCode = [];
        $logContext = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'product_code_count' => count($productCodes),
        ];

        if ($productCodes === []) {
            return $salesByProductCode;
        }

        if (rtrim((string) config('enox.base_url'), '/') === '') {
            Log::warning('CTR summary: ENOX_API_BASE_URL is empty; sold columns will show zero.', $logContext);

            return $salesByProductCode;
        }

        $endpoint = (string) config('enox.endpoints.tracking_products_total_sold');
        $chunks = array_chunk($productCodes, 100);
        $failedChunks = 0;

        try {
            $client = app(EnoxApiClient::class);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkContext = $logContext + [
                    'endpoint' => $endpoint,
                    'chunk' => $chunkIndex + 1,
                    'chunk_count' => count($chunks),
                    'chunk_size' => count($chunk),
                ];

                try {
                    $response = $client->post($endpoint, [
                        'product_code' => $chunk,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ]);
                } catch (\Throwable $chunkException) {
                    $failedChunks++;
                    Log::error('CTR summary: tracking/products/total-sold request threw an exception.', $chunkContext + [
                        'error' => $chunkException->getMessage(),
                    ]);

                    continue;
                }

                if (! $response->successful()) {
                    $failedChunks++;
                    Log::warning('CTR summary: tracking/products/total-sold HTTP request failed.', $chunkContext + [
                        'http_status' => $response->status(),
                        'response_body' => mb_substr(trim((string) $response->body()), 0, 500),
                    ]);

                    continue;
                }

                $payload = $response->json();

                if (! is_array($payload) || ! ($payload['status'] ?? false)) {
                    $failedChunks++;
                    Log::warning('CTR summary: tracking/products/total-sold returned status=false.', $chunkContext + [
                        'message' => is_array($payload) ? ($payload['message'] ?? null) : null,
                        'error' => is_array($payload) ? ($payload['error'] ?? null) : null,
                    ]);

                    continue;
                }

                foreach ($payload['data'] ?? [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $code = trim((string) ($item['product_code'] ?? ''));

                    if ($code === '') {
                        continue;
                    }

                    $salesByProductCode[$code] = [
                        'total_sale' => (float) ($item['total_sale'] ?? 0),
                        'total_qty' => (int) ($item['total_qty'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $exception) {
            Log::error('CTR summary: tracking/products/total-sold fetch failed unexpectedly.', $logContext + [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return $salesByProductCode;
        }

        if ($failedChunks > 0) {
            Log::warning('CTR summary: tracking/products/total-sold completed with failed chunk(s); some sold values may be zero.', $logContext + [
                'endpoint' => $endpoint,
                'failed_chunks' => $failedChunks,
                'total_chunks' => count($chunks),
                'products_with_sales' => count($salesByProductCode),
            ]);
        } elseif (count($salesByProductCode) === 0) {
            Log::info('CTR summary: tracking/products/total-sold returned no sales rows for requested product codes.', $logContext + [
                'endpoint' => $endpoint,
            ]);
        }

        return $salesByProductCode;
    }

    /**
     * @param  Builder  $query
     */
    private function applySearchFilter($query, string $search): void
    {
        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $query->where(function ($inner) use ($token) {
                $like = '%'.$this->escapeLike(mb_strtolower($token)).'%';
                $slugFlexible = '%'.$this->escapeLike(
                    str_replace([' ', '-', '_'], '%', mb_strtolower($token))
                ).'%';

                $inner->whereRaw('LOWER(product_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(department_slug) LIKE ?', [$slugFlexible])
                    ->orWhereRaw('LOWER(category_slug) LIKE ?', [$slugFlexible])
                    ->orWhereRaw(
                        "LOWER(REPLACE(REPLACE(REPLACE(department_slug, '-', ' '), '_', ' '), '  ', ' ')) LIKE ?",
                        [$like],
                    )
                    ->orWhereRaw(
                        "LOWER(REPLACE(REPLACE(REPLACE(category_slug, '-', ' '), '_', ' '), '  ', ' ')) LIKE ?",
                        [$like],
                    );

                if (ctype_digit($token)) {
                    $id = (int) $token;
                    $inner->orWhere('department_id', $id)
                        ->orWhere('category_id', $id);
                }
            });
        }
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveSort(Request $request): array
    {
        $sortKey = (string) $request->query('sort', 'ctr');
        if (! array_key_exists($sortKey, self::SORT_COLUMNS)) {
            $sortKey = 'ctr';
        }

        $dir = strtolower((string) $request->query('dir', 'desc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [$sortKey, $dir];
    }

    /**
     * @param  Builder  $query
     */
    private function applySortOrder($query, string $primaryKey, string $dir): void
    {
        if (! array_key_exists($primaryKey, self::SORT_COLUMNS)) {
            $primaryKey = 'ctr';
        }

        $keys = [$primaryKey];
        foreach (self::SORT_SEQUENCE as $key) {
            if ($key !== $primaryKey) {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            $query->orderBy(self::SORT_COLUMNS[$key], $dir);
        }

        $query->orderBy('tracking_ctr_summaries.product_code');
    }

    private function formatDepartmentColumn(object $row): string
    {
        $label = $this->formatLabel($row->department_slug, $row->department_id, true);

        if ($label !== '—') {
            return $label;
        }

        return $this->formatCtrTypeLabel($row->type);
    }

    private function formatCategoryColumn(object $row): string
    {
        $type = trim((string) ($row->type ?? ''));
        $categoryLabel = $this->formatLabel($row->category_slug, $row->category_id);
        $departmentId = (int) ($row->department_id ?? 0);

        if ($type === TrackingCtrCategory::TYPE_STATIC && $departmentId > 0) {
            return 'Static -> '.$categoryLabel;
        }

        if ($type === TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK && $departmentId > 0) {
            return 'Custom link -> '.$categoryLabel;
        }

        $microLabel = $this->formatMicroCategoryColumn($row);

        if ($microLabel === '—') {
            return $categoryLabel;
        }

        return $categoryLabel.' -> '.$microLabel;
    }

    private function formatMicroCategoryColumn(object $row): string
    {
        $microId = (int) ($row->micro_category_id ?? 0);
        $microSlug = trim((string) ($row->micro_category_slug ?? ''));

        if ($microId <= 0 && $microSlug === '') {
            return '—';
        }

        $microLabel = $this->formatLabel(
            $microSlug !== '' ? $microSlug : null,
            $microId > 0 ? $microId : null,
        );

        return $microLabel === '—' ? '—' : $microLabel;
    }

    private function formatCtrTypeLabel(?string $type): string
    {
        return match (trim((string) ($type ?? ''))) {
            TrackingCtrCategory::TYPE_STATIC => 'Static',
            TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK => 'Custom link',
            default => '—',
        };
    }

    private function formatLabel(?string $slug, mixed $id, bool $treatZeroAsEmpty = false): string
    {
        if ($treatZeroAsEmpty && ($id === null || $id === '' || (int) $id === 0)) {
            $id = null;
        }

        $slug = trim((string) ($slug ?? ''));
        if ($slug !== '') {
            return Str::headline(str_replace(['-', '_'], ' ', $slug));
        }

        if ($id !== null && $id !== '') {
            return (string) $id;
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function resolveRankingContext(array $validated, Request $request): array
    {
        $contextInRequest = array_key_exists('context', $validated) || $request->query->has('context');

        if ($contextInRequest) {
            $contextKey = trim((string) ($validated['context'] ?? $request->query('context', '')));
            if ($contextKey === '') {
                return ['', null, null];
            }

            if (str_contains($contextKey, ':')) {
                [$scopeFromKey, $scopeIdFromKey] = explode(':', $contextKey, 2);

                return [
                    $contextKey,
                    (string) $scopeFromKey,
                    max(0, (int) $scopeIdFromKey),
                ];
            }
        }

        [$listingScope, $listingScopeId] = $this->resolveRankingListingScope($validated, $request);

        if ($listingScope !== null && $listingScope !== '') {
            $scopeId = $listingScopeId ?? 0;

            return [$listingScope.':'.$scopeId, $listingScope, $scopeId];
        }

        return ['', null, null];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function rankingProductsPaginationQuery(Request $request, string $contextKey, string $search): array
    {
        $query = [];

        if ($contextKey !== '') {
            $query['context'] = $contextKey;
        }

        if ($search !== '') {
            $query['search'] = $search;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveRankingListingScope(array $validated, Request $request): array
    {
        $scopeProvided = array_key_exists('listing_scope', $validated)
            && ($validated['listing_scope'] ?? '') !== '';
        $scopeIdProvided = array_key_exists('listing_scope_id', $validated)
            && $validated['listing_scope_id'] !== null;

        if (array_key_exists('context', $validated) || $request->query->has('context')) {
            return [null, null];
        }

        $context = trim((string) ($validated['context'] ?? $request->query('context', '')));
        if (! $scopeProvided && ! $scopeIdProvided && $context !== '' && str_contains($context, ':')) {
            [$scope, $scopeId] = explode(':', $context, 2);

            return [(string) $scope, max(0, (int) $scopeId)];
        }

        if ($scopeProvided) {
            $scope = (string) $validated['listing_scope'];

            return [
                $scope,
                $scopeIdProvided ? max(0, (int) $validated['listing_scope_id']) : null,
            ];
        }

        if ($scopeIdProvided) {
            return [null, max(0, (int) $validated['listing_scope_id'])];
        }

        if ($request->query->has('listing_scope') || $request->query->has('listing_scope_id')) {
            $scope = trim((string) $request->query('listing_scope', ''));
            if ($scope === '') {
                return [null, null];
            }

            return [
                $scope,
                $request->query->has('listing_scope_id')
                    ? max(0, (int) $request->query('listing_scope_id'))
                    : null,
            ];
        }

        return [null, null];
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function fetchRankingProductsPage(
        ?string $contextKey,
        ?string $listingScope,
        ?int $listingScopeId,
        int $page,
        int $perPage,
        string $search = '',
    ): array {
        if (rtrim((string) config('enox.base_url'), '/') === '') {
            return [null, 'ENOX_API_BASE_URL is not configured.'];
        }

        $endpoint = (string) config('enox.endpoints.tracking_ranking_products', 'tracking/ranking-products');

        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($contextKey !== null && $contextKey !== '') {
            $params['context'] = $contextKey;
        } else {
            if ($listingScope !== null && $listingScope !== '') {
                $params['listing_scope'] = $listingScope;
            }

            if ($listingScopeId !== null) {
                $params['listing_scope_id'] = $listingScopeId;
            }
        }

        if ($search !== '') {
            $params['search'] = $search;
        }

        try {
            $client = app(EnoxApiClient::class);
            $response = $client->get($endpoint, $params);
        } catch (\Throwable $exception) {
            Log::error('Ranking products: tracking/ranking-products request failed.', [
                'listing_scope' => $listingScope,
                'listing_scope_id' => $listingScopeId,
                'error' => $exception->getMessage(),
            ]);

            return [null, 'Could not load ranking products from Enorsia.'];
        }

        if (! $response->successful()) {
            Log::warning('Ranking products: tracking/ranking-products HTTP error.', [
                'listing_scope' => $listingScope,
                'listing_scope_id' => $listingScopeId,
                'http_status' => $response->status(),
                'body' => mb_substr(trim((string) $response->body()), 0, 500),
            ]);

            return [null, 'Enorsia returned HTTP '.$response->status().' for ranking products.'];
        }

        $payload = $response->json();
        if (! is_array($payload) || ! ($payload['status'] ?? false)) {
            $message = is_array($payload) ? (string) ($payload['message'] ?? 'Ranking products request failed.') : 'Invalid response.';

            return [null, $message];
        }

        return [$payload, null];
    }

    /**
     * @param  array<string, string>  $labelsByContextKey
     * @return Collection<int, array<string, mixed>>
     */
    private function transformRankingApiRows(
        Collection $apiRows,
        array $labelsByContextKey,
        EcommerceListingScopeCtrResolver $ctrResolver,
    ): Collection {
        $ctrByContextCode = [];

        foreach ($apiRows->groupBy(fn (array $item): string => ($item['listing_scope'] ?? '').':'.($item['listing_scope_id'] ?? 0)) as $rowContextKey => $group) {
            [$scope, $scopeId] = array_pad(explode(':', (string) $rowContextKey, 2), 2, '0');
            $codes = $group
                ->map(fn (array $item): string => trim((string) ($item['product_code'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($codes === []) {
                continue;
            }

            $metrics = $ctrResolver->metricsByProductCode((string) $scope, (int) $scopeId, $codes);
            foreach ($metrics as $code => $metric) {
                $ctrByContextCode[$rowContextKey][$code] = $metric;
            }
        }

        return $apiRows->map(function (array $item) use ($ctrByContextCode, $labelsByContextKey): array {
            $code = trim((string) ($item['product_code'] ?? ''));
            $scope = (string) ($item['listing_scope'] ?? '');
            $scopeId = (int) ($item['listing_scope_id'] ?? 0);
            $rowContextKey = $scope.':'.$scopeId;
            $settings = is_array($item['product_ranking_settings'] ?? null)
                ? $item['product_ranking_settings']
                : [];
            $pin = is_array($item['pin'] ?? null) ? $item['pin'] : [];
            $ctr = $ctrByContextCode[$rowContextKey][$code] ?? null;
            $pinSerial = isset($pin['pin_serial']) ? (int) $pin['pin_serial'] : 0;

            return [
                'context_key' => $rowContextKey,
                'context_label' => $labelsByContextKey[$rowContextKey] ?? $rowContextKey,
                'listing_scope' => $scope,
                'listing_scope_id' => $scopeId,
                'serial_number' => (int) ($item['serial_number'] ?? 0),
                'product_code' => $code,
                'total_sold' => (int) ($settings['total_sold'] ?? 0),
                'total_sold_rolling' => (int) ($settings['total_sold_rolling'] ?? 0),
                'is_stock_out' => (int) ($settings['is_stock_out'] ?? 0),
                'is_new_in' => (int) ($settings['is_new_in'] ?? 0),
                'is_pinned' => (int) ($pin['is_pinned'] ?? ($pinSerial > 0 ? 1 : 0)),
                'pin_serial' => $pinSerial > 0 ? $pinSerial : null,
                'is_selling_fast' => (int) ($settings['is_selling_fast'] ?? 0),
                'click' => (int) ($ctr['total_click'] ?? 0),
                'ctr' => (float) ($ctr['ctr'] ?? 0),
                'ctr_average' => (float) ($ctr['ctr_average'] ?? 0),
            ];
        });
    }
}
