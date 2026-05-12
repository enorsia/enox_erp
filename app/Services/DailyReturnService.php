<?php

namespace App\Services;

use App\Models\DailyReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DailyReturnService
{
    /**
     * Return paginated, filtered list of daily returns.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return DailyReturn::with(['salePlatform', 'returnReasonType'])
            // Join 3 levels to order by platform hierarchy (keeps parent+child groups together)
            ->join('sale_platforms as sp',   'sp.id',   '=', 'daily_returns.sale_platform_id')
            ->leftJoin('sale_platforms as sp_p', 'sp_p.id', '=', 'sp.parent_id')
            ->leftJoin('sale_platforms as sp_g', 'sp_g.id', '=', 'sp_p.parent_id')
            ->select('daily_returns.*')
            ->filter($filters)
            ->orderByRaw('COALESCE(sp_g.sort_order, sp_p.sort_order, sp.sort_order)')
            ->orderByRaw('COALESCE(sp_p.sort_order, sp.sort_order, 0)')
            ->orderBy('sp.sort_order')
            ->orderByDesc('daily_returns.date')
            ->orderByDesc('daily_returns.id')
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

        $allReturns = $paginator->getCollection();
        $yearGroups = [];

        foreach ($allReturns->groupBy(fn($r) => optional($r->date)->year ?? 0)->sortKeysDesc() as $year => $yearReturns) {
            $monthGroups = [];

            foreach ($yearReturns->sortBy('date')->groupBy(fn($r) => optional($r->date)->month ?? 0)->sortKeys() as $monthNum => $monthReturns) {
                $platformGroups = [];

                foreach ($monthReturns->groupBy(fn($r) => $r->salePlatform?->parent_id ?? ('p'.$r->sale_platform_id)) as $groupKey => $groupReturns) {
                    $parentId = is_numeric($groupKey) ? (int) $groupKey : null;

                    // Use parent's DFS index for groups, own index for standalone roots
                    $representativeId = $parentId ?? $groupReturns->first()->sale_platform_id;
                    $dfsIndex         = $platformDfsIndex[$representativeId] ?? PHP_INT_MAX;

                    $platformGroups[] = [
                        '_dfsIndex'      => $dfsIndex,
                        'headerName'     => $parentId
                            ? ($platformLookup->get($parentId, [])['name'] ?? '—')
                            : ($groupReturns->first()->salePlatform?->name ?? '—'),
                        'parentPlatform' => $parentId ? $platformLookup->get($parentId) : null,
                        'returns'        => $groupReturns
                            // Sort child platforms within a group by their DFS order
                            ->sortBy(fn($r) => $platformDfsIndex[$r->sale_platform_id] ?? PHP_INT_MAX)
                            ->map(function ($return) {
                                // Pre-compute all boolean flags so blade stays logic-free
                                $return->hasGenderBreakdown = (
                                    ($return->number_of_male_returns   ?? 0) +
                                    ($return->number_of_female_returns ?? 0) +
                                    ($return->number_of_kids_returns   ?? 0)
                                ) > 0;
                                return $return;
                            }),
                    ];
                }

                // Sort platform groups by DFS order (same sequence as SalePlatformExport)
                usort($platformGroups, fn($a, $b) => $a['_dfsIndex'] <=> $b['_dfsIndex']);
                foreach ($platformGroups as &$pg) { unset($pg['_dfsIndex']); }
                unset($pg);

                $monthGroups[] = [
                    'monthNum'          => $monthNum,
                    'monthName'         => $monthsMap[$monthNum] ?? (string) $monthNum,
                    'year'              => $year,
                    'monthTotalReturns' => $monthReturns->sum('number_of_returns'),
                    'platformGroups'    => $platformGroups,
                ];
            }

            $yearGroups[] = [
                'year'             => $year,
                'yearTotalReturns' => $yearReturns->sum('number_of_returns'),
                'monthGroups'      => $monthGroups,
            ];
        }

        return $yearGroups;
    }

    /**
     * Return an un-paginated query for export (respects the same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return DailyReturn::with(['salePlatform', 'returnReasonType'])
            ->filter($filters)
            ->latest('date')
            ->latest('id');
    }

    /**
     * Validation rules shared by store and update.
     */
    public function storeRules(): array
    {
        return [
            'sale_platform_id'                   => 'required|exists:sale_platforms,id',
            'return_reason_type_id'              => 'required|exists:return_reason_types,id',
            'date'                               => 'required|date',
            'number_of_returns'                  => 'required|integer|min:0',
            'number_of_return_quantities'        => 'required|integer|min:0',
            'number_of_male_returns'             => 'nullable|integer|min:0',
            'number_of_female_returns'           => 'nullable|integer|min:0',
            'number_of_kids_returns'             => 'nullable|integer|min:0',
            'number_of_male_return_quantities'   => 'nullable|integer|min:0',
            'number_of_female_return_quantities' => 'nullable|integer|min:0',
            'number_of_kids_return_quantities'   => 'nullable|integer|min:0',
        ];
    }

    /**
     * Create a new daily return record.
     */
    public function create(array $validated): DailyReturn
    {
        return DailyReturn::create($this->normaliseNullables($validated));
    }

    /**
     * Update an existing daily return record.
     */
    public function update(DailyReturn $dailyReturn, array $validated): DailyReturn
    {
        $dailyReturn->update($this->normaliseNullables($validated));
        return $dailyReturn;
    }

    /**
     * Delete a daily return record.
     */
    public function delete(DailyReturn $dailyReturn): void
    {
        $dailyReturn->delete();
    }

    // ── Private helpers ───────────────────────────────────────────

    private function normaliseNullables(array $data): array
    {
        $nullableInts = [
            'number_of_male_returns', 'number_of_female_returns', 'number_of_kids_returns',
            'number_of_male_return_quantities', 'number_of_female_return_quantities', 'number_of_kids_return_quantities',
        ];

        foreach ($nullableInts as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        return $data;
    }
}

