<?php

namespace App\Http\Controllers;

use App\Services\EcomActivityListService;
use App\Services\Shop\EcommerceDepartmentProductSalesExportService;
use App\Services\Shop\EcommerceDepartmentProductSalesService;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class EcommerceDepartmentProductSalesController extends Controller
{
    public const DEFAULT_PERIOD = '7d';

    /** @var array<string, string> */
    public const PERIOD_PRESETS = [
        'yesterday' => 'Yesterday',
        '7d' => '7d',
        '30d' => '30d',
    ];

    private const PER_PAGE = 25;

    /** @var list<string> */
    private const SORT_KEYS = ['department', 'product_code', 'total_qty', 'total_sale'];

    public function index(
        Request $request,
        EcommerceDepartmentProductSalesService $salesService,
    ): View {
        Gate::authorize('ecommerce.department_product_sales.index');

        $context = $this->listContext($request, $salesService);

        return view('ecommerce.department_product_sales.index', $context);
    }

    public function export(
        Request $request,
        EcommerceDepartmentProductSalesService $salesService,
        EcommerceDepartmentProductSalesExportService $exportService,
    ): BinaryFileResponse {
        Gate::authorize('ecommerce.department_product_sales.export');

        $context = $this->listContext($request, $salesService, forExport: true);

        return $exportService->download(
            $context['rows'],
            $context['filterSummary'],
            $context['dateFrom'],
            $context['dateTo'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function listContext(
        Request $request,
        EcommerceDepartmentProductSalesService $salesService,
        bool $forExport = false,
    ): array {
        $period = $this->resolvePeriod($request);
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );
        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();

        [$sortKey, $sortDir] = $this->resolveSort($request);
        $search = trim((string) $request->query('search', ''));
        $departmentIds = $this->departmentIdsFromRequest($request);

        $filterQuery = array_filter([
            'period' => $period !== self::DEFAULT_PERIOD ? $period : null,
            'date_from' => $period === 'custom' ? $fromDate : null,
            'date_to' => $period === 'custom' ? $toDate : null,
            'search' => $search !== '' ? $search : null,
            'department' => $departmentIds !== [] ? $departmentIds : null,
            'sort' => $sortKey !== 'total_sale' ? $sortKey : null,
            'dir' => $sortDir !== 'desc' ? $sortDir : null,
        ], fn ($value) => $value !== null && $value !== []);

        $catalogQuery = array_diff_key($filterQuery, array_flip(['period', 'date_from', 'date_to']));
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $nextQuery = EcomActivityListService::shiftedPeriodQuery($range, 1);

        $perPage = $forExport ? 50_000 : self::PER_PAGE;
        $page = $forExport ? 1 : max(1, $request->integer('page', 1));

        $connectionError = null;
        $paginator = null;
        $departmentOptions = [];

        try {
            $departmentOptions = $salesService->departmentFilterOptions($fromDate, $toDate);
            $paginator = $salesService->paginate(
                $fromDate,
                $toDate,
                $search,
                $departmentIds,
                $sortKey,
                $sortDir,
                $perPage,
                $page,
                $request->url(),
                $request->query(),
            );
        } catch (Throwable $exception) {
            $connectionError = 'Could not load sales from the Enorsia database replica. Check ENOX_DB_* settings.';
            report($exception);
        }

        $rows = $forExport
            ? ($paginator?->items() ?? [])
            : $paginator;

        $data = [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'periodPresets' => self::PERIOD_PRESETS,
            'dateFrom' => $fromDate,
            'dateTo' => $toDate,
            'rangeLabel' => TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
            'filterQuery' => $filterQuery,
            'filterFormAction' => route('admin.ecommerce.department-product-sales.index'),
            'resetUrl' => route('admin.ecommerce.department-product-sales.index'),
            'drawerResetUrl' => route('admin.ecommerce.department-product-sales.index', array_filter([
                'period' => $period !== self::DEFAULT_PERIOD ? $period : null,
                'date_from' => $period === 'custom' ? $fromDate : null,
                'date_to' => $period === 'custom' ? $toDate : null,
            ])),
            'prevUrl' => $prevQuery ? route('admin.ecommerce.department-product-sales.index', $prevQuery + $catalogQuery) : null,
            'nextUrl' => $nextQuery ? route('admin.ecommerce.department-product-sales.index', $nextQuery + $catalogQuery) : null,
            'exportUrl' => route('admin.ecommerce.department-product-sales.export', $filterQuery),
            'periodPresetUrl' => fn (string $periodKey) => route(
                'admin.ecommerce.department-product-sales.index',
                ['period' => $periodKey] + $catalogQuery,
            ),
        ];

        if ($forExport) {
            return [
                'rows' => $rows,
                'filterSummary' => $this->buildFilterSummary($search, $departmentIds, $departmentOptions, $range),
                'dateFrom' => $fromDate,
                'dateTo' => $toDate,
            ];
        }

        return [
            'rows' => $paginator,
            'sortBy' => $sortKey,
            'sortDir' => $sortDir,
            'search' => $search,
            'departmentIds' => $departmentIds,
            'departmentOptions' => $departmentOptions,
            'connectionError' => $connectionError,
            'data' => $data,
        ];
    }

    private function resolvePeriod(Request $request): string
    {
        $period = trim((string) $request->query('period', self::DEFAULT_PERIOD));

        return in_array($period, [...array_keys(self::PERIOD_PRESETS), 'custom'], true)
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
            default => 'Date range',
        };
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveSort(Request $request): array
    {
        $sortKey = (string) $request->query('sort', 'total_sale');
        if (! in_array($sortKey, self::SORT_KEYS, true)) {
            $sortKey = 'total_sale';
        }

        $sortDir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [$sortKey, $sortDir];
    }

    /**
     * @return list<int>
     */
    private function departmentIdsFromRequest(Request $request): array
    {
        $ids = [];
        foreach ((array) $request->query('department', []) as $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === '__all__' || ! ctype_digit($value)) {
                continue;
            }
            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $departmentIds
     * @param  list<array{id: int, name: string}>  $departmentOptions
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $range
     */
    private function buildFilterSummary(
        string $search,
        array $departmentIds,
        array $departmentOptions,
        array $range,
    ): string {
        $parts = [
            'Dates: '.TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
        ];

        if ($search !== '') {
            $parts[] = 'Search: '.$search;
        }

        if ($departmentIds !== []) {
            $labelsById = collect($departmentOptions)->pluck('name', 'id');
            $labels = [];
            foreach ($departmentIds as $departmentId) {
                $labels[] = $labelsById[$departmentId] ?? (string) $departmentId;
            }
            $parts[] = 'Departments: '.implode(', ', $labels);
        }

        return implode(' · ', $parts);
    }
}