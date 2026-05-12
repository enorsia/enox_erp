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
        return DailySale::with('salePlatform')
            ->filter($filters)
            ->latest('date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
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

