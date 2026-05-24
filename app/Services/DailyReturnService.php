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
        return DailyReturn::with(['salePlatform.parent.parent', 'returnReasonType'])
            // Join 3 levels to allow platform-hierarchy ordering within each date
            ->join('sale_platforms as sp',       'sp.id',   '=', 'daily_returns.sale_platform_id')
            ->leftJoin('sale_platforms as sp_p', 'sp_p.id', '=', 'sp.parent_id')
            ->leftJoin('sale_platforms as sp_g', 'sp_g.id', '=', 'sp_p.parent_id')
            ->select('daily_returns.*')
            ->filter($filters)
            // PRIMARY: date DESC — keeps all records for the same date together for pagination
            ->orderByDesc('daily_returns.date')
            // SECONDARY: platform hierarchy order within each date
            ->orderByRaw('COALESCE(sp_g.sort_order, sp_p.sort_order, sp.sort_order)')
            ->orderByRaw('COALESCE(sp_p.sort_order, sp.sort_order, 0)')
            ->orderBy('sp.sort_order')
            ->orderByDesc('daily_returns.id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Build date-wise view groups for the index page.
     *
     * Structure returned:
     *   dateGroups[]
     *     date / dateFormatted / totals / entries (for edit-btn)
     *     rootGroups[]
     *       rootName  — level-1 (root) platform name
     *       subGroups[]
     *         subName  — level-2 platform name, or NULL when entries are root/level-2 owned
     *         entries  — Collection of DailyReturn models
     *
     * Hierarchy rule:
     *   • Record at depth 0 (root)  → rootGroup only,  subName = null
     *   • Record at depth 1 (mid)   → rootGroup only,  subName = null
     *   • Record at depth 2+ (leaf) → rootGroup + subGroup, subName = level-2 name
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
            $rootGroupsMap = [];
            $rootOrderList = [];

            foreach ($dateReturns as $ret) {
                $plat = $ret->salePlatform;

                // Build ancestor chain: [root, level-2?, leaf]  (index 0 = topmost)
                $ancestors = [];
                $cur = $plat;
                while ($cur) {
                    array_unshift($ancestors, $cur);
                    $cur = $cur->parent ?? null;
                }

                $root = $ancestors[0] ?? null;
                $mid  = count($ancestors) >= 3 ? $ancestors[1] : null;

                $rootKey = $root ? ('r' . $root->id) : 'r_unknown';
                $subKey  = $mid  ? ('s' . $mid->id)  : 'direct';

                if (!isset($rootGroupsMap[$rootKey])) {
                    $rootGroupsMap[$rootKey] = [
                        'rootName' => $root?->name ?? '—',
                        'subMap'   => [],
                        'subOrder' => [],
                    ];
                    $rootOrderList[] = $rootKey;
                }

                if (!isset($rootGroupsMap[$rootKey]['subMap'][$subKey])) {
                    $rootGroupsMap[$rootKey]['subMap'][$subKey] = [
                        'subName' => $mid?->name,
                        'entries' => [],
                    ];
                    $rootGroupsMap[$rootKey]['subOrder'][] = $subKey;
                }

                $rootGroupsMap[$rootKey]['subMap'][$subKey]['entries'][] = $ret;
            }

            $rootGroups = [];
            foreach ($rootOrderList as $rk) {
                $rg = $rootGroupsMap[$rk];
                $subGroups = [];
                foreach ($rg['subOrder'] as $sk) {
                    $sg = $rg['subMap'][$sk];
                    $subGroups[] = [
                        'subName' => $sg['subName'],
                        'entries' => collect($sg['entries']),
                    ];
                }
                $rootGroups[] = [
                    'rootName'  => $rg['rootName'],
                    'subGroups' => $subGroups,
                ];
            }

            $dateGroups[] = [
                'date'           => $date,
                'dateFormatted'  => \Carbon\Carbon::parse($date)->format('d M Y'),
                'totalReturns'   => $dateReturns->sum('number_of_returns'),
                'totalReturnQty' => $dateReturns->sum('number_of_return_quantities'),
                'rootGroups'     => $rootGroups,
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
            'entries.*.return_amount'                      => 'nullable|numeric|min:0',
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
            'entries.*.return_amount'                      => 'nullable|numeric|min:0',
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
                DailyReturn::updateOrCreate(
                    [
                        'sale_platform_id'      => $data['sale_platform_id'],
                        'date'                  => $data['date'],
                        'return_reason_type_id' => $data['return_reason_type_id'],
                    ],
                    $data
                );
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
            'return_amount'                      => 'nullable|numeric|min:0',
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

        if (!isset($data['return_amount']) || $data['return_amount'] === '' || $data['return_amount'] === null) {
            $data['return_amount'] = 0;
        }

        return $data;
    }
}
