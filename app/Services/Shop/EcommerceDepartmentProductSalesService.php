<?php

namespace App\Services\Shop;

use App\Support\Enox;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
class EcommerceDepartmentProductSalesService
{
    /** @var list<string> */
    public const DEPARTMENT_ORDER = ['Women', 'Men', 'Girls', 'Boys'];

    /** @var array<string, string> */
    public const SORT_COLUMNS = [
        'department' => 'department_name',
        'product_code' => 'product_code',
        'total_qty' => 'total_qty',
        'total_sale' => 'total_sale',
    ];

    /**
     * @param  list<int>  $departmentIds
     */
    public function paginate(
        string $fromDate,
        string $toDate,
        string $search,
        array $departmentIds,
        string $sortKey,
        string $sortDir,
        int $perPage,
        int $page,
        string $path,
        array $query,
    ): LengthAwarePaginator {
        $queryBuilder = $this->aggregatedQuery($fromDate, $toDate, $search, $departmentIds);
        $this->applySort($queryBuilder, $sortKey, $sortDir);

        $total = (clone $queryBuilder)->count();
        $rows = $queryBuilder
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): array => $this->mapRow($row))
            ->all();

        return new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $path, 'query' => $query],
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function departmentFilterOptions(string $fromDate, string $toDate): array
    {
        $styleDepartments = $this->styleDepartmentSubquery();

        return Enox::table('daily_sales_summary as dss')
            ->whereBetween('dss.order_date', [$fromDate, $toDate])
            ->join('ecommerce_products as ep', 'ep.id', '=', 'dss.product_id')
            ->joinSub($styleDepartments, 'pd_map', function ($join): void {
                $join->on('pd_map.style_design_id', '=', 'ep.style_design_id');
            })
            ->join('lookup_names as dept', 'dept.id', '=', 'pd_map.department_id')
            ->select('dept.id', 'dept.name')
            ->distinct()
            ->orderBy('dept.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        return $this->sortDepartmentsByCatalogOrder($options);
    }

    /**
     * @param  list<int>  $departmentIds
     */
    private function aggregatedQuery(
        string $fromDate,
        string $toDate,
        string $search,
        array $departmentIds,
    ): Builder {
        $qtySql = ShopDailySalesMetrics::TOTAL_QTY_SQL;
        $saleSql = ShopDailySalesMetrics::TOTAL_SALE_SQL;
        $styleDepartments = $this->styleDepartmentSubquery();

        $query = Enox::table('daily_sales_summary as dss')
            ->whereBetween('dss.order_date', [$fromDate, $toDate])
            ->join('ecommerce_products as ep', 'ep.id', '=', 'dss.product_id')
            ->leftJoinSub($styleDepartments, 'pd_map', function ($join): void {
                $join->on('pd_map.style_design_id', '=', 'ep.style_design_id');
            })
            ->leftJoin('lookup_names as dept', 'dept.id', '=', 'pd_map.department_id')
            ->groupBy('dss.product_code', 'pd_map.department_id', 'dept.name')
            ->selectRaw('COALESCE(dept.name, ?) as department_name', ['—'])
            ->selectRaw('pd_map.department_id as department_id')
            ->selectRaw('dss.product_code as product_code')
            ->selectRaw("SUM({$qtySql}) as total_qty")
            ->selectRaw("SUM({$saleSql}) as total_sale")
            ->havingRaw("SUM({$qtySql}) > 0 OR SUM({$saleSql}) > 0");

        if ($search !== '') {
            $like = '%'.$this->escapeLike(mb_strtolower($search)).'%';
            $query->whereRaw('LOWER(dss.product_code) LIKE ?', [$like]);
        }

        if ($departmentIds !== []) {
            $query->whereIn('pd_map.department_id', array_map(intval(...), $departmentIds));
        }

        return $query;
    }

    private function styleDepartmentSubquery(): Builder
    {
        return Enox::table('product_details')
            ->selectRaw('style_design_id')
            ->selectRaw('MIN(department_id) as department_id')
            ->groupBy('style_design_id');
    }

    private function applySort(Builder $query, string $sortKey, string $sortDir): void
    {
        $column = self::SORT_COLUMNS[$sortKey] ?? self::SORT_COLUMNS['total_sale'];
        $direction = $sortDir === 'asc' ? 'asc' : 'desc';
        $departmentOrderSql = $this->departmentOrderSqlExpression();

        // Primary: full department blocks (Women → Men → Girls → Boys), never mixed with metrics.
        $departmentDirection = ($sortKey === 'department' && $direction === 'desc') ? 'desc' : 'asc';
        $query->orderByRaw(sprintf('%s %s', $departmentOrderSql, $departmentDirection));

        if ($sortKey !== 'department') {
            $query->orderBy($column, $direction);
        }

        if ($sortKey !== 'product_code') {
            $query->orderBy('product_code', 'asc');
        }
    }

    private function departmentOrderSqlExpression(): string
    {
        $quoted = implode(', ', array_map(
            fn (string $name): string => "'".str_replace("'", "''", $name)."'",
            self::DEPARTMENT_ORDER,
        ));

        return "CASE WHEN department_name = '—' THEN 999 ELSE FIELD(department_name, {$quoted}) END";
    }

    /**
     * @param  list<array{id: int, name: string}>  $options
     * @return list<array{id: int, name: string}>
     */
    private function sortDepartmentsByCatalogOrder(array $options): array
    {
        usort($options, function (array $a, array $b): int {
            $rankA = $this->departmentCatalogRank($a['name']);
            $rankB = $this->departmentCatalogRank($b['name']);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $options;
    }

    private function departmentCatalogRank(string $name): int
    {
        foreach (self::DEPARTMENT_ORDER as $index => $orderedName) {
            if (strcasecmp($name, $orderedName) === 0) {
                return $index;
            }
        }

        return 999;
    }

    private function mapRow(object $row): array
    {
        return [
            'department_name' => (string) ($row->department_name ?? '—'),
            'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
            'product_code' => (string) ($row->product_code ?? ''),
            'total_qty' => (int) ($row->total_qty ?? 0),
            'total_sale' => (float) ($row->total_sale ?? 0),
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}