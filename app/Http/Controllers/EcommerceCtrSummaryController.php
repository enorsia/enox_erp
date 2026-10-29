<?php

namespace App\Http\Controllers;

use App\Models\TrackingCtrCategory;
use App\Models\TrackingCtrSummary;
use App\Services\EcommerceCtrSummaryExportService;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcommerceCtrSummaryController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<string> Tie-break order after the column the user clicked */
    private const SORT_SEQUENCE = [
        'ctr',
        'ctr_average',
        'click',
        'impression',
        'sold_7_days',
        'total_sold',
    ];

    /** @var array<string, string> Sort key => SQL order expression */
    private const SORT_COLUMNS = [
        'ctr' => 'ctr',
        'ctr_average' => 'ctr_average',
        'click' => 'total_click',
        'impression' => 'total_impression',
        'sold_7_days' => 'sort_sold_7_days',
        'total_sold' => 'sort_total_sold',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('ecommerce.ctr_summary.index');

        $context = $this->listContext($request);

        $summaries = (clone $context['query'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $summaries->setCollection(
            $summaries->getCollection()->map(fn (TrackingCtrSummary $row) => $this->mapRow($row)),
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

    public function export(Request $request, EcommerceCtrSummaryExportService $exportService): BinaryFileResponse
    {
        Gate::authorize('ecommerce.ctr_summary.export');

        $context = $this->listContext($request);

        $rows = (clone $context['query'])
            ->get()
            ->map(fn (TrackingCtrSummary $row) => $this->mapRow($row))
            ->all();

        return $exportService->download($rows, $context['filterSummary']);
    }

    /**
     * @return array{
     *     query: Builder<TrackingCtrSummary>,
     *     sortKey: string,
     *     sortDir: 'asc'|'desc',
     *     search: string,
     *     filterSummary: string,
     *     filterCatalog: array{departments: array<string, string>, categories: list<array{value: string, label: string, department: string}>},
     *     data: array<string, mixed>
     * }
     */
    private function listContext(Request $request): array
    {
        [$sortKey, $sortDir] = $this->resolveSort($request);
        $search = trim((string) $request->query('search', ''));
        $catalogFilters = $this->catalogFiltersFromRequest($request);

        $query = $this->summariesQuery($request, $sortKey, $sortDir);

        $filterQuery = array_filter([
            'sort' => $sortKey,
            'dir' => $sortDir,
            'search' => $search !== '' ? $search : null,
            'department' => $catalogFilters['departments'] !== [] ? $catalogFilters['departments'] : null,
            'category' => $catalogFilters['categories'] !== [] ? $catalogFilters['categories'] : null,
        ]);

        $resetUrl = route('admin.ecommerce.ctr-summary.index');

        $data = [
            'filterFormAction' => route('admin.ecommerce.ctr-summary.index'),
            'resetUrl' => $resetUrl,
            'exportUrl' => route('admin.ecommerce.ctr-summary.export', $filterQuery),
        ];

        return [
            'query' => $query,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'search' => $search,
            'filterCatalog' => $this->filterCatalog(),
            'filterSummary' => $this->buildFilterSummary($search, $catalogFilters),
            'data' => $data,
        ];
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
            if ($value === '' || $value === '__all__' || ! str_contains($value, ':')) {
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
    private function filterCatalog(): array
    {
        $rows = TrackingCtrSummary::query()
            ->select('department_id', 'department_slug', 'category_id', 'category_slug')
            ->distinct()
            ->orderBy('department_slug')
            ->orderBy('category_slug')
            ->get();

        $departments = [];
        $categories = [];

        foreach ($rows as $row) {
            $departmentId = (int) ($row->department_id ?? 0);
            if ($departmentId <= 0) {
                continue;
            }

            $categoryId = (int) $row->category_id;
            $departmentKey = (string) $departmentId;
            $departmentLabel = $this->formatLabel($row->department_slug, $departmentId, true);
            if ($departmentLabel === '—') {
                continue;
            }

            $categoryLabel = $this->formatLabel($row->category_slug, $categoryId);

            $departments[$departmentKey] = $departmentLabel;
            $categories[] = [
                'value' => $departmentKey.':'.$categoryId,
                'label' => $departmentLabel.' -> '.$categoryLabel,
                'department' => $departmentKey,
            ];
        }

        natcasesort($departments);

        usort($categories, fn (array $a, array $b) => strnatcasecmp($a['label'], $b['label']));

        return [
            'departments' => $departments,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array{departments: list<string>, categories: list<string>}  $catalogFilters
     */
    private function buildFilterSummary(string $search, array $catalogFilters): string
    {
        $parts = [];

        if ($search !== '') {
            $parts[] = 'Search: '.$search;
        }

        if ($catalogFilters['departments'] !== []) {
            $catalog = $this->filterCatalog();
            $labels = [];
            foreach ($catalogFilters['departments'] as $departmentId) {
                $labels[] = $catalog['departments'][$departmentId] ?? $departmentId;
            }
            $parts[] = 'Departments: '.implode(', ', $labels);
        }

        if ($catalogFilters['categories'] !== []) {
            $catalog ??= $this->filterCatalog();
            $labelsByValue = collect($catalog['categories'])->pluck('label', 'value');
            $labels = [];
            foreach ($catalogFilters['categories'] as $value) {
                $labels[] = $labelsByValue[$value] ?? $value;
            }
            $parts[] = 'Categories: '.implode(', ', $labels);
        }

        return $parts === [] ? 'All rows' : implode(' · ', $parts);
    }

    /**
     * @return Builder<TrackingCtrSummary>
     */
    private function summariesQuery(Request $request, string $sortKey, string $sortDir): Builder
    {
        $search = trim((string) $request->query('search', ''));
        $sevenDayFrom = TrackerTime::localNow()->subDays(6)->startOfDay()->toDateString();

        return TrackingCtrSummary::query()
            ->select('tracking_ctr_summaries.*')
            ->addSelect([
                'sort_total_sold' => TrackerDatabase::table('tracking_daily_products')
                    ->selectRaw('COALESCE(SUM(sold_qty), 0)')
                    ->whereColumn('tracking_daily_products.product_code', 'tracking_ctr_summaries.product_code'),
                'sort_sold_7_days' => TrackerDatabase::table('tracking_daily_products')
                    ->selectRaw('COALESCE(SUM(CASE WHEN metric_date >= ? THEN sold_qty ELSE 0 END), 0)', [$sevenDayFrom])
                    ->whereColumn('tracking_daily_products.product_code', 'tracking_ctr_summaries.product_code'),
            ])
            ->when($search !== '', fn ($query) => $this->applySearchFilter($query, $search))
            ->tap(fn ($query) => $this->applyCatalogFilters($query, $request))
            ->tap(fn ($query) => $this->applySortOrder($query, $sortKey, $sortDir));
    }

    /**
     * @param  Builder<TrackingCtrSummary>  $query
     */
    private function applyCatalogFilters($query, Request $request): void
    {
        $catalogFilters = $this->catalogFiltersFromRequest($request);

        if ($catalogFilters['departments'] !== []) {
            $query->whereIn(
                'department_id',
                array_map(intval(...), $catalogFilters['departments']),
            );
        }

        if ($catalogFilters['categories'] === []) {
            return;
        }

        $query->where(function ($inner) use ($catalogFilters) {
            foreach ($catalogFilters['categories'] as $token) {
                [$departmentId, $categoryId] = explode(':', $token, 2);
                $inner->orWhere(function ($pair) use ($departmentId, $categoryId) {
                    $pair->where('department_id', (int) $departmentId)
                        ->where('category_id', (int) $categoryId);
                });
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(TrackingCtrSummary $row): array
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
            'sold_7_days' => (int) ($row->sort_sold_7_days ?? 0),
            'total_sold' => (int) ($row->sort_total_sold ?? 0),
        ];
    }

    /**
     * @param  Builder<TrackingCtrSummary>  $query
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
     * @param  Builder<TrackingCtrSummary>  $query
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

        $query->orderBy('tracking_ctr_summaries.id');
    }

    private function formatDepartmentColumn(TrackingCtrSummary $row): string
    {
        $label = $this->formatLabel($row->department_slug, $row->department_id, true);

        if ($label !== '—') {
            return $label;
        }

        return $this->formatCtrTypeLabel($row->type);
    }

    private function formatCategoryColumn(TrackingCtrSummary $row): string
    {
        $categoryLabel = $this->formatLabel($row->category_slug, $row->category_id);
        $microLabel = $this->formatMicroCategoryColumn($row);

        if ($microLabel === '—') {
            return $categoryLabel;
        }

        return $categoryLabel.' -> '.$microLabel;
    }

    private function formatMicroCategoryColumn(TrackingCtrSummary $row): string
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
