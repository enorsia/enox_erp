<?php

namespace App\Services;

use App\Models\DailySale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class DailySaleService
{
    /**
     * Return paginated, filtered list of daily sales.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return DailySale::with(['salePlatform'])
            // Join 3 levels to order by platform hierarchy (same sequence as SalePlatformExport)
            ->join('sale_platforms as sp',   'sp.id',   '=', 'daily_sales.sale_platform_id')
            ->leftJoin('sale_platforms as sp_p', 'sp_p.id', '=', 'sp.parent_id')
            ->leftJoin('sale_platforms as sp_g', 'sp_g.id', '=', 'sp_p.parent_id')
            ->select('daily_sales.*')
            ->filter($filters)
            // Root-level sort_order first → keeps all children of the same parent together
            ->orderByRaw('COALESCE(sp_g.sort_order, sp_p.sort_order, sp.sort_order)')
            ->orderByRaw('COALESCE(sp_p.sort_order, sp.sort_order, 0)')
            ->orderBy('sp.sort_order')
            ->orderByDesc('daily_sales.date')
            ->orderByDesc('daily_sales.id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Build grouped view data (year → month → platform) with pre-computed totals.
     * All heavy aggregation is done here so the blade template remains logic-free.
     */
    public function buildViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, array $salePlatforms): array
    {
        $monthsMap = config('constants.months', [
            1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
            7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
        ]);

        $platformLookup = collect($salePlatforms)->keyBy('id');

        // DFS position index built from the already-sorted getParentOptions output
        $platformDfsIndex = array_flip(array_column($salePlatforms, 'id'));

        $allSales   = $paginator->getCollection();
        $yearGroups = [];

        foreach ($allSales->groupBy(fn($s) => optional($s->date)->year ?? 0)->sortKeysDesc() as $year => $yearSales) {
            $monthGroups = [];

            foreach ($yearSales->sortBy('date')->groupBy(fn($s) => optional($s->date)->month ?? 0)->sortKeys() as $monthNum => $monthSales) {
                $platformGroups = [];

                foreach ($monthSales->groupBy(fn($s) => $s->salePlatform?->parent_id ?? ('p'.$s->sale_platform_id)) as $groupKey => $groupSales) {
                    $parentId = is_numeric($groupKey) ? (int) $groupKey : null;

                    // Use parent's DFS index for groups, own index for standalone roots
                    $representativeId = $parentId ?? $groupSales->first()->sale_platform_id;
                    $dfsIndex         = $platformDfsIndex[$representativeId] ?? PHP_INT_MAX;

                    $platformGroups[] = [
                        '_dfsIndex'      => $dfsIndex,
                        'headerName'     => $parentId
                            ? ($platformLookup->get($parentId, [])['name'] ?? '—')
                            : ($groupSales->first()->salePlatform?->name ?? '—'),
                        'parentPlatform' => $parentId ? $platformLookup->get($parentId) : null,
                        'sales'          => $groupSales
                            // Sort child platforms within a group by their DFS order
                            ->sortBy(fn($s) => $platformDfsIndex[$s->sale_platform_id] ?? PHP_INT_MAX)
                            ->map(function ($sale) {
                                // Pre-compute all boolean flags so blade stays logic-free
                                $sale->hasGenderBreakdown = (
                                    ($sale->number_of_male_orders   ?? 0) +
                                    ($sale->number_of_female_orders ?? 0) +
                                    ($sale->number_of_kids_orders   ?? 0)
                                ) > 0;
                                return $sale;
                            }),
                    ];
                }

                // Sort platform groups by DFS order (same sequence as SalePlatformExport)
                usort($platformGroups, fn($a, $b) => $a['_dfsIndex'] <=> $b['_dfsIndex']);
                foreach ($platformGroups as &$pg) { unset($pg['_dfsIndex']); }
                unset($pg);

                $monthGroups[] = [
                    'monthNum'         => $monthNum,
                    'monthName'        => $monthsMap[$monthNum] ?? (string) $monthNum,
                    'year'             => $year,
                    'monthTotalSales'  => $monthSales->sum('sales'),
                    'monthTotalSpent'  => $monthSales->sum('spent'),
                    'monthTotalOrders' => $monthSales->sum('number_of_orders'),
                    'platformGroups'   => $platformGroups,
                ];
            }

            $yearGroups[] = [
                'year'            => $year,
                'yearTotalSales'  => $yearSales->sum('sales'),
                'yearTotalOrders' => $yearSales->sum('number_of_orders'),
                'monthGroups'     => $monthGroups,
            ];
        }

        return $yearGroups;
    }

    /**
     * Return an un-paginated query for export (respects the same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return DailySale::with('salePlatform')
            ->filter($filters)
            ->latest('date')
            ->latest('id');
    }

    /**
     * Validation rules for creating a daily sale.
     */
    public function storeRules(): array
    {
        return [
            'sale_platform_id'          => 'required|exists:sale_platforms,id',
            'date'                       => 'required|date',
            'spent'                      => 'required|numeric|min:0',
            'sales'                      => 'required|numeric|min:0',
            'number_of_orders'           => 'required|integer|min:0',
            'number_of_quantities'       => 'required|integer|min:0',
            'number_of_male_orders'      => 'nullable|integer|min:0',
            'number_of_female_orders'    => 'nullable|integer|min:0',
            'number_of_kids_orders'      => 'nullable|integer|min:0',
            'number_of_male_quantities'  => 'nullable|integer|min:0',
            'number_of_female_quantities'=> 'nullable|integer|min:0',
            'number_of_kids_quantities'  => 'nullable|integer|min:0',
        ];
    }

    /**
     * Additional uniqueness rule for store (prevents duplicate platform+date combos).
     */
    public function uniqueRule(?int $ignoreId = null): array
    {
        $rule = Rule::unique('daily_sales')->where(function ($q) {
            // combined with sale_platform_id validated separately
        });

        return [
            'sale_platform_id' => [
                'required',
                'exists:sale_platforms,id',
                Rule::unique('daily_sales', 'sale_platform_id')
                    ->where(fn($q) => $q->where('date', request('date')))
                    ->ignore($ignoreId),
            ],
        ];
    }

    /**
     * Create a new daily sale record.
     */
    public function create(array $validated): DailySale
    {
        return DailySale::create($this->normaliseNullables($validated));
    }

    /**
     * Update an existing daily sale record.
     */
    public function update(DailySale $dailySale, array $validated): DailySale
    {
        $dailySale->update($this->normaliseNullables($validated));
        return $dailySale;
    }

    /**
     * Delete a daily sale record.
     */
    public function delete(DailySale $dailySale): void
    {
        $dailySale->delete();
    }

    // ── Private helpers ───────────────────────────────────────────

    private function normaliseNullables(array $data): array
    {
        $nullableInts = [
            'number_of_male_orders', 'number_of_female_orders', 'number_of_kids_orders',
            'number_of_male_quantities', 'number_of_female_quantities', 'number_of_kids_quantities',
        ];

        foreach ($nullableInts as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        return $data;
    }
}

