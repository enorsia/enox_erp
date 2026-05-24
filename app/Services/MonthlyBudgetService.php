<?php

namespace App\Services;

use App\Models\MonthlyBudget;
use App\Models\SalePlatform;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ManualPaginator;
use Illuminate\Pagination\Paginator;

class MonthlyBudgetService
{
    /**
     * Return a paginated list of monthly budgets, paginated by year/month GROUP
     * (not by individual record) so the tree structure is never split across pages.
     *
     * Total / perPage refer to distinct (year, month) pairs.
     * The collection inside the paginator contains ALL records for those pairs.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        $perPage     = 6; // year-month groups per page
        $currentPage = Paginator::resolveCurrentPage('page');

        // Step 1: find all distinct (year, month) pairs matching the filters, in display order.
        $allPairs = MonthlyBudget::filter($filters)
            ->selectRaw('year, month')
            ->distinct()
            ->orderByDesc('year')
            ->orderBy('month')
            ->get();  // lightweight – only two integer columns

        $total = $allPairs->count();

        // Step 2: slice to the pairs that belong to the current page.
        $currentPairs = $allPairs->slice(($currentPage - 1) * $perPage, $perPage)->values();

        // Step 3: load ALL budget records for those pairs so no group is ever split.
        $items = collect();
        if ($currentPairs->isNotEmpty()) {
            $query = MonthlyBudget::with('salePlatform')->filter($filters);
            $query->where(function ($q) use ($currentPairs) {
                foreach ($currentPairs as $pair) {
                    $q->orWhere(function ($inner) use ($pair) {
                        $inner->where('year', $pair->year)->where('month', $pair->month);
                    });
                }
            });
            $items = $query->orderByDesc('year')->orderBy('month')->orderBy('sale_platform_id')->get();
        }

        return (new ManualPaginator($items, $total, $perPage, $currentPage, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();
    }

    /**
     * Build grouped view data (year → month → platform) with pre-computed totals.
     * Supports unlimited nesting depth. Structural (ancestor) platforms that have no
     * budget of their own are rendered as virtual group headers so sub-platforms like
     * Germany / France / Italy are always shown nested correctly under Amazon EU.
     */
    public function buildViewGroups(LengthAwarePaginator $paginator): array
    {
        $months = config('constants.months', [
            1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
            7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
        ]);

        $allBudgets = $paginator->getCollection();

        // Load the full platform tree once (lightweight – only what we need for hierarchy)
        $allPlatforms = SalePlatform::select('id', 'name', 'parent_id')->get()->keyBy('id');

        $yearGroups = [];

        foreach ($allBudgets->groupBy('year')->sortKeysDesc() as $year => $yearBudgets) {
            $monthGroups = [];

            foreach ($yearBudgets->sortBy('month')->groupBy('month') as $monthNum => $monthBudgets) {
                $budgetByPlatformId   = $monthBudgets->keyBy('sale_platform_id');
                $platformIdsWithBudget = $budgetByPlatformId->keys()->all();

                // Walk each budgeted platform's ancestor chain; collect any ancestor
                // platforms that do NOT have a budget entry (structural / virtual nodes).
                $structuralIds = [];
                foreach ($platformIdsWithBudget as $pid) {
                    $parentId = $allPlatforms->get($pid)?->parent_id;
                    while ($parentId !== null) {
                        if (!in_array($parentId, $platformIdsWithBudget, true)
                            && !in_array($parentId, $structuralIds, true)) {
                            $structuralIds[] = $parentId;
                        }
                        $parentId = $allPlatforms->get($parentId)?->parent_id;
                    }
                }

                // All node IDs: real budget platforms + virtual ancestor platforms
                $allNodeIds = array_unique(array_merge($platformIdsWithBudget, $structuralIds));

                // Group node IDs by their parent (only when parent is also in allNodeIds)
                $childrenByParent = [];
                foreach ($allNodeIds as $nodeId) {
                    $platform = $allPlatforms->get($nodeId);
                    if (!$platform) {
                        continue;
                    }
                    $parentId = $platform->parent_id;
                    $key      = ($parentId !== null && in_array($parentId, $allNodeIds, true))
                        ? $parentId
                        : '__root__';
                    $childrenByParent[$key][] = $nodeId;
                }

                // Recursive closure: builds the entry array for a list of node IDs
                $buildEntries = function (array $nodeIds) use (&$buildEntries, $childrenByParent, $budgetByPlatformId, $allPlatforms): array {
                    $entries = [];
                    foreach ($nodeIds as $nodeId) {
                        $budget        = $budgetByPlatformId->get($nodeId);   // null for structural
                        $platform      = $allPlatforms->get($nodeId);
                        $isStructural  = $budget === null;
                        $directChildIds = $childrenByParent[$nodeId] ?? [];
                        $childEntries  = !empty($directChildIds) ? $buildEntries($directChildIds) : [];
                        $childrenTotal = (float) array_sum(array_map(fn($e) => $e['total'], $childEntries));
                        $ownBudget     = $isStructural ? 0.0 : (float) $budget->budget;

                        $entries[] = [
                            'platformId'   => $nodeId,
                            'platform'     => $platform,        // always the SalePlatform row
                            'budget'       => $budget,          // null for structural nodes
                            'isStructural' => $isStructural,
                            'childEntries' => $childEntries,
                            'hasChildren'  => !empty($childEntries),
                            'childSum'     => $childrenTotal,
                            'ownBudget'    => $ownBudget,
                            'total'        => $ownBudget + $childrenTotal,
                        ];
                    }
                    return $entries;
                };

                $rootEntries = $buildEntries($childrenByParent['__root__'] ?? []);

                $monthGroups[] = [
                    'monthNum'    => $monthNum,
                    'monthName'   => $months[$monthNum] ?? 'Month '.$monthNum,
                    'monthTotal'  => $monthBudgets->sum('budget'),
                    'rootEntries' => $rootEntries,
                ];
            }

            $yearGroups[] = [
                'year'        => $year,
                'yearTotal'   => $yearBudgets->sum('budget'),
                'monthGroups' => $monthGroups,
            ];
        }

        return $yearGroups;
    }

    /**
     * Return an un-paginated query for export (respects same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return MonthlyBudget::with('salePlatform')
            ->filter($filters)
            ->orderByDesc('year')
            ->orderBy('month')
            ->orderBy('sale_platform_id');
    }
}

