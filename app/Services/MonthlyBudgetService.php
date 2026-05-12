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
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Return an un-paginated query for export (respects same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return MonthlyBudget::with('salePlatform')
            ->filter($filters)
            ->latest('id');
    }
}

