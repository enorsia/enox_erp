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
     * Build date-wise view groups for the index page.
     * Entries within each date are sub-grouped by their parent platform.
     */
    public function buildDateViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        $dateGroups = [];

        foreach (
            $paginator->getCollection()
                ->groupBy(fn($r) => optional($r->date)->format('Y-m-d') ?? '')
                ->sortKeysDesc()
            as $date => $dateReturns
        ) {
            $platformGroups = [];
            foreach (
                $dateReturns->groupBy(fn($r) => $r->salePlatform?->parent_id ?? ('r_' . $r->sale_platform_id))
                as $gKey => $gReturns
            ) {
                $first     = $gReturns->first();
                $isChild   = is_numeric($gKey);
                $groupName = $isChild
                    ? ($first->salePlatform?->parent?->name ?? '—')
                    : ($first->salePlatform?->name ?? '—');

                $platformGroups[] = [
                    'groupName' => $groupName,
                    'isChild'   => $isChild,
                    'entries'   => $gReturns->values(),
                ];
            }

            $dateGroups[] = [
                'date'           => $date,
                'dateFormatted'  => \Carbon\Carbon::parse($date)->format('d M Y'),
                'totalReturns'   => $dateReturns->sum('number_of_returns'),
                'totalReturnQty' => $dateReturns->sum('number_of_return_quantities'),
                'platformGroups' => $platformGroups,
                'entries'        => $dateReturns->values(),
            ];
        }

        return $dateGroups;
    }

    /**
     * Load all daily return records for a specific date.
     */
    public function getByDate(string $date): \Illuminate\Database\Eloquent\Collection
    {
        return DailyReturn::with(['salePlatform', 'returnReasonType'])
            ->whereDate('date', $date)
            ->get();
    }

    /**
     * Validation rules for bulk creation via the entries[] array.
     */
    public function bulkStoreRules(): array
    {
        return [
            'date'                                          => 'required|date',
            'entries'                                       => 'required|array|min:1',
            'entries.*.sale_platform_id'                   => 'required|exists:sale_platforms,id',
            'entries.*.return_reason_type_id'              => 'required|exists:return_reason_types,id',
            'entries.*.number_of_returns'                  => 'required|integer|min:0',
            'entries.*.number_of_return_quantities'        => 'required|integer|min:0',
            'entries.*.number_of_male_returns'             => 'nullable|integer|min:0',
            'entries.*.number_of_female_returns'           => 'nullable|integer|min:0',
            'entries.*.number_of_kids_returns'             => 'nullable|integer|min:0',
            'entries.*.number_of_male_return_quantities'   => 'nullable|integer|min:0',
            'entries.*.number_of_female_return_quantities' => 'nullable|integer|min:0',
            'entries.*.number_of_kids_return_quantities'   => 'nullable|integer|min:0',
        ];
    }

    /**
     * Validation rules for bulk update via the entries[] array.
     */
    public function bulkUpdateRules(): array
    {
        return [
            'date'                                          => 'required|date',
            'entries'                                       => 'present|array',
            'entries.*.sale_platform_id'                   => 'required_with:entries|exists:sale_platforms,id',
            'entries.*.return_reason_type_id'              => 'required_with:entries|exists:return_reason_types,id',
            'entries.*.number_of_returns'                  => 'required_with:entries|integer|min:0',
            'entries.*.number_of_return_quantities'        => 'required_with:entries|integer|min:0',
            'entries.*.number_of_male_returns'             => 'nullable|integer|min:0',
            'entries.*.number_of_female_returns'           => 'nullable|integer|min:0',
            'entries.*.number_of_kids_returns'             => 'nullable|integer|min:0',
            'entries.*.number_of_male_return_quantities'   => 'nullable|integer|min:0',
            'entries.*.number_of_female_return_quantities' => 'nullable|integer|min:0',
            'entries.*.number_of_kids_return_quantities'   => 'nullable|integer|min:0',
        ];
    }

    /**
     * Bulk-create daily return records for a given date.
     */
    public function bulkCreate(string $date, array $entries): array
    {
        $created = [];
        foreach ($entries as $entry) {
            $entry['date'] = $date;
            $created[]     = DailyReturn::create($this->normaliseNullables($entry));
        }
        return $created;
    }

    /**
     * Sync all entries for a date:
     *  – Delete records whose IDs appear in $deleteIds.
     *  – Update records that have an 'id' key.
     *  – Create records that have no 'id' key.
     */
    public function syncForDate(string $date, array $entries, array $deleteIds = []): void
    {
        if (!empty($deleteIds)) {
            DailyReturn::where('date', $date)->whereIn('id', $deleteIds)->delete();
        }

        foreach ($entries as $entry) {
            $entry['date'] = $date;
            $data          = $this->normaliseNullables($entry);

            if (!empty($data['id'])) {
                $id = (int) $data['id'];
                unset($data['id']);
                DailyReturn::where('id', $id)->where('date', $date)->update($data);
            } else {
                unset($data['id']);
                DailyReturn::create($data);
            }
        }
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

