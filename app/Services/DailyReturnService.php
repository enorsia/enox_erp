<?php

namespace App\Services;

use App\Models\DailyReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DailyReturnService
{
    /**
     * Return paginated, filtered list of daily returns.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return DailyReturn::with(['salePlatform', 'returnReasonType'])
            ->filter($filters)
            ->latest('date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
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

