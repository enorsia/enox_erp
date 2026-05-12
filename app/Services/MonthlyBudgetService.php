<?php

namespace App\Services;

use App\Models\MonthlyBudget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MonthlyBudgetService
{
    /**
     * Return paginated, filtered list of monthly budgets.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return MonthlyBudget::with('salePlatform')
            ->filter($filters)
            ->orderByDesc('year')
            ->orderBy('month')
            ->orderBy('sale_platform_id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Build grouped view data (year → month → platform) with pre-computed totals.
     * All heavy aggregation is done here so the blade template remains logic-free.
     */
    public function buildViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        $months     = config('constants.months', [
            1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
            7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
        ]);
        $allBudgets = $paginator->getCollection();
        $yearGroups = [];

        foreach ($allBudgets->groupBy('year')->sortKeysDesc() as $year => $yearBudgets) {
            $monthGroups = [];

            foreach ($yearBudgets->sortBy('month')->groupBy('month') as $monthNum => $monthBudgets) {
                $platformIds      = $monthBudgets->pluck('sale_platform_id')->toArray();

                $childBudgets = $monthBudgets->filter(
                    fn($b) => $b->salePlatform && $b->salePlatform->parent_id !== null
                              && in_array($b->salePlatform->parent_id, $platformIds)
                );
                $childIds         = $childBudgets->pluck('sale_platform_id')->toArray();
                $rootBudgets      = $monthBudgets->filter(fn($b) => !in_array($b->sale_platform_id, $childIds));
                $childrenByParent = $childBudgets->groupBy(fn($b) => $b->salePlatform->parent_id);

                $rootEntries = [];
                foreach ($rootBudgets as $budget) {
                    $children    = $childrenByParent->get($budget->sale_platform_id, collect());
                    $childSum    = $children->sum('budget');
                    $rootEntries[] = [
                        'budget'      => $budget,
                        'children'    => $children,
                        'childSum'    => $childSum,
                        'childCount'  => $children->count(),
                        'hasChildren' => $children->isNotEmpty(),
                        'total'       => $budget->budget + $childSum,
                    ];
                }

                $monthGroups[] = [
                    'monthNum'    => $monthNum,
                    'monthName'   => $months[$monthNum] ?? 'Month '.$monthNum,
                    'monthTotal'  => $monthBudgets->sum('budget'),
                    'rootEntries' => $rootEntries,
                ];
            }

            $yearGroups[] = [
                'year'      => $year,
                'yearTotal' => $yearBudgets->sum('budget'),
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

