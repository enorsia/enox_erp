<?php

namespace App\Http\Controllers;

use App\Models\TrackingCtrCategory;
use App\Services\EcomActivityListService;
use App\Services\EcommerceCtrSummaryExportService;
use App\Services\TrackingCtrSyncSiteSettingsService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceCtrSummaryController extends Controller
{
    private const PER_PAGE = 20;

    private const CATEGORY_FILTER_DEPARTMENT_CUSTOM_LINK = '__custom_link__';

    private const CATEGORY_FILTER_DEPARTMENT_STATIC = '__static__';

    /** @var list<string> Category filter group order (store department labels). */
    private const CATEGORY_FILTER_DEPARTMENT_ORDER = ['Women', 'Men', 'Girls', 'Boys'];

    /** @var list<string> Tie-break order after the column the user clicked */
    private const SORT_SEQUENCE = [
        'ctr',
        'ctr_average',
        'click',
        'impression',
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

        $summaries->setCollection(
            $summaries->getCollection()->map(fn (object $row) => $this->mapRow($row)),
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

    public function export(Request $request, EcommerceCtrSummaryExportService $exportService, TrackingCtrSyncSiteSettingsService $syncSiteSettings): BinaryFileResponse
    {
        Gate::authorize('ecommerce.ctr_summary.export');

        $context = $this->listContext($request, $syncSiteSettings);

        $rows = (clone $context['query'])
            ->get()
            ->map(fn (object $row) => $this->mapRow($row))
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
        $period = EcomActivityListService::normalizePeriod($request->query('period'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );
        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();
        $productSorting = $syncSiteSettings->fetchForSync();

        $query = $this->summariesQuery($request, $sortKey, $sortDir, $fromDate, $toDate);

        $filterQuery = $this->ctrFilterQueryParams($sortKey, $sortDir, $search, $catalogFilters, $period, $range);
        $catalogQuery = array_diff_key($filterQuery, array_flip(['period', 'date_from', 'date_to']));
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $nextQuery = EcomActivityListService::shiftedPeriodQuery($range, 1);

        $resetUrl = route('admin.ecommerce.ctr-summary.index');
        $drawerResetUrl = route('admin.ecommerce.ctr-summary.index', array_filter([
            'period' => $period !== EcomActivityListService::DEFAULT_PERIOD ? $period : null,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ], fn ($value) => $value !== null && $value !== []));

        $data = [
            'period' => $period,
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
            'productSorting' => [
                'ctrDays' => $productSorting['ctr_days'],
                'minClicks' => $productSorting['min_clicks'],
                'fromStoreApi' => $productSorting['source'] === 'ecom_api',
            ],
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
            'period' => $period !== EcomActivityListService::DEFAULT_PERIOD ? $period : null,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ], fn ($value) => $value !== null && $value !== []);
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

        $inner = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->select([
                'department_id',
                DB::raw('MAX(department_slug) as department_slug'),
                'category_id',
                DB::raw('MAX(category_slug) as category_slug'),
                'micro_category_id',
                DB::raw('MAX(micro_category_slug) as micro_category_slug'),
                DB::raw('MAX(type) as type'),
                'product_code',
                DB::raw('SUM(total_click) as total_click'),
                DB::raw('SUM(total_impression) as total_impression'),
                DB::raw('CASE WHEN SUM(total_impression) > 0 THEN ROUND(SUM(total_click) / SUM(total_impression), 6) ELSE 0 END as ctr'),
                DB::raw('ROUND(COALESCE(AVG(ctr), 0), 6) as ctr_average'),
            ])
            ->groupBy('department_id', 'category_id', 'micro_category_id', 'product_code');

        if ($search !== '') {
            $this->applySearchFilter($inner, $search);
        }

        $this->applyCatalogFilters($inner, $request);

        $query = TrackerDatabase::query()
            ->fromSub($inner, 'tracking_ctr_summaries')
            ->select('tracking_ctr_summaries.*');

        $this->applySortOrder($query, $sortKey, $sortDir);

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
}
