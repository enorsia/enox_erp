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
            ->filter($filters)
            ->latest('date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Build grouped view data (year → month → platform) with pre-computed totals.
     * All heavy aggregation is done here so the blade template remains logic-free.
     */
    public function buildViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, array $salePlatforms): array
    {
        $monthsMap      = config('constants.months', [
            1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
            7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
        ]);
        $platformLookup = collect($salePlatforms)->keyBy('id');
        $allReturns     = $paginator->getCollection();
        $yearGroups     = [];

        foreach ($allReturns->groupBy(fn($r) => optional($r->date)->year ?? 0)->sortKeysDesc() as $year => $yearReturns) {
            $monthGroups = [];

            foreach ($yearReturns->sortBy('date')->groupBy(fn($r) => optional($r->date)->month ?? 0)->sortKeys() as $monthNum => $monthReturns) {
                $platformGroups = [];

                foreach ($monthReturns->groupBy(fn($r) => $r->salePlatform?->parent_id ?? ('p'.$r->sale_platform_id)) as $groupKey => $groupReturns) {
                    $parentId = is_numeric($groupKey) ? (int) $groupKey : null;
                    $platformGroups[] = [
                        'parentPlatform' => $parentId ? $platformLookup->get($parentId) : null,
                        'returns'        => $groupReturns->map(function ($return) {
                            $return->hasGenderBreakdown = (
                                ($return->number_of_male_returns   ?? 0) +
                                ($return->number_of_female_returns ?? 0) +
                                ($return->number_of_kids_returns   ?? 0)
                            ) > 0;
                            return $return;
                        }),
                    ];
                }

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

